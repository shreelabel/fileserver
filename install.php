<?php
// Shree Label File Server - Web Installer (No manual config edit needed)
session_start();
$configPath = __DIR__ . '/config/database.php';
$isInstalled = false;
$msg = $err = "";

// Check if already installed (try connecting)
function tryConnect($host,$port,$db,$user,$pass){
    try{
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if($db){
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo2 = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $pdo2->query("SELECT 1");
        }
        return [true, ""];
    } catch(Exception $e){ return [false, $e->getMessage()]; }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $host = trim($_POST['host']??'127.0.0.1');
    $port = trim($_POST['port']??'3306');
    $db   = trim($_POST['database']??'file_server');
    $user = trim($_POST['username']??'root');
    $pass = $_POST['password']??'';
    $admin_name = trim($_POST['admin_name']??'Admin User');
    $admin_email = strtolower(trim($_POST['admin_email']??'admin@company.com'));
    $admin_pass = $_POST['admin_pass']??'admin123';

    list($ok,$error) = tryConnect($host,$port,$db,$user,$pass);
    if(!$ok){
        $err = "Connection failed: $error";
    } else {
        // Write config/database.php via UI
        $cfgContent = "<?php\nreturn [\n    'connection' => 'mysql',\n    'mysql' => [\n        'host' => ".var_export($host,true).",\n        'port' => ".var_export($port,true).",\n        'database' => ".var_export($db,true).",\n        'username' => ".var_export($user,true).",\n        'password' => ".var_export($pass,true).",\n        'charset' => 'utf8mb4',\n    ],\n    'sqlite' => ['path' => __DIR__ . '/../database/file_server.sqlite'],\n];\n";
        file_put_contents($configPath, $cfgContent);

        // Now create tables using connection.php logic
        try{
            require __DIR__ . '/database/connection.php';
            $pdo = getPDO(); // will create DB & tables & seed
            // Ensure admin user as per installer
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?)");
            $stmt->execute([$admin_email]);
            $exists = $stmt->fetch();
            if($exists){
                $pdo->prepare("UPDATE users SET password=?, name=?, role='admin', quota_gb=100 WHERE LOWER(email)=LOWER(?)")->execute([$hash,$admin_name,$admin_email]);
            } else {
                $pdo->prepare("INSERT INTO users (name,email,password,role,quota_gb) VALUES (?,?,?,?,?)")->execute([$admin_name,$admin_email,$hash,'admin',100]);
            }
            // Ensure upload folder
            @mkdir(__DIR__.'/upload',0775,true);
            @mkdir(__DIR__.'/storage/tmp',0775,true);
            file_put_contents(__DIR__.'/upload/.gitkeep','keep');
            $msg = "✅ Database setup successful! Admin: $admin_email / $admin_pass";
            $isInstalled = true;
        } catch(Exception $e){
            $err = "Config saved but table creation failed: ".$e->getMessage();
        }
    }
}

// Try to detect existing config
$current = file_exists($configPath) ? include $configPath : null;
$curHost = $current['mysql']['host'] ?? '127.0.0.1';
$curPort = $current['mysql']['port'] ?? '3306';
$curDb = $current['mysql']['database'] ?? 'file_server';
$curUser = $current['mysql']['username'] ?? 'root';

// Auto-detect install path and login link
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$baseUrl = $protocol . '://' . $hostName . ($scriptDir ? $scriptDir . '/' : '/');
$loginUrl = $baseUrl . 'index.php';
$installPath = __DIR__;
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install - Shree Label File Server</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>*{font-family:Inter,sans-serif}</style>
</head>
<body class="bg-[#f6f7f9] min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-[720px] bg-white rounded-[24px] shadow-2xl overflow-hidden border">
  <div class="bg-gradient-to-br from-slate-900 to-indigo-900 text-white p-8">
    <div class="w-12 h-12 rounded-xl bg-white/15 flex items-center justify-center mb-4"><i class="bi bi-database text-xl"></i></div>
    <h1 class="text-2xl font-extrabold">Shree Label File Server — Installer</h1>
    <p class="text-indigo-200 text-sm mt-2">UI দিয়ে Database setup করুন — কোনো manual file edit লাগবে না। XAMPP ও Hostinger দুই জায়গায় কাজ করবে।</p>
    <div class="mt-3 space-y-2">
      <div class="text-xs bg-white/10 rounded-lg px-3 py-2 flex justify-between"><span>Install Path:</span> <code class="bg-black/20 px-2 py-0.5 rounded"><?=htmlspecialchars($installPath)?></code></div>
      <div class="text-xs bg-white/10 rounded-lg px-3 py-2 flex justify-between"><span>Detected URL:</span> <code class="bg-black/20 px-2 py-0.5 rounded"><?=htmlspecialchars($baseUrl)?></code></div>
      <div class="text-xs bg-emerald-500/20 border border-emerald-400/30 rounded-lg px-3 py-2">Login Link: <a href="<?=htmlspecialchars($loginUrl)?>" class="underline font-bold"><?=htmlspecialchars($loginUrl)?></a> <span class="opacity-70">(auto detected)</span></div>
    </div>
  </div>
  <div class="p-8">
    <?php if($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm mb-4"><?=$msg?><br>
      <div class="mt-2 p-2 bg-white rounded-lg border text-xs">Install Path: <code><?=htmlspecialchars($installPath)?></code><br>Login URL: <a href="<?=htmlspecialchars($loginUrl)?>" class="text-indigo-600 underline font-bold"><?=htmlspecialchars($loginUrl)?></a></div>
      <a href="<?=htmlspecialchars($loginUrl)?>" class="inline-block mt-3 bg-indigo-600 text-white px-5 py-2 rounded-xl text-sm font-bold">→ Go to Login Now</a> | <a href="fix-admin.php" class="underline">Fix Admin</a></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-4"><?=htmlspecialchars($err)?></div><?php endif; ?>

    <form method="POST" class="space-y-4">
      <h3 class="font-bold">Database (MySQL)</h3>
      <div class="grid md:grid-cols-2 gap-3">
        <div><label class="text-xs font-semibold">Host</label><input name="host" value="<?=htmlspecialchars($curHost)?>" placeholder="127.0.0.1 or localhost (Hostinger: localhost)" class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" required></div>
        <div><label class="text-xs font-semibold">Port</label><input name="port" value="<?=htmlspecialchars($curPort)?>" placeholder="3306" class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm" required></div>
        <div><label class="text-xs font-semibold">Database Name</label><input name="database" value="<?=htmlspecialchars($curDb)?>" placeholder="file_server or u123456789_fileserver" class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm" required></div>
        <div><label class="text-xs font-semibold">Username</label><input name="username" value="<?=htmlspecialchars($curUser)?>" placeholder="root (Hostinger: u123456...)" class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm" required></div>
        <div class="md:col-span-2"><label class="text-xs font-semibold">Password</label><input name="password" type="password" placeholder="XAMPP: leave empty, Hostinger: DB password" class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm"></div>
      </div>
      <div class="text-xs text-slate-500 bg-slate-50 border rounded-xl p-3">XAMPP: host=127.0.0.1, user=root, pass=(empty), db=file_server<br>Hostinger: host=localhost, user/db = hPanel → Databases → Management থেকে copy করুন</div>

      <h3 class="font-bold pt-2">Admin Account</h3>
      <div class="grid md:grid-cols-2 gap-3">
        <div><label class="text-xs font-semibold">Admin Name</label><input name="admin_name" value="Admin User" class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm" required></div>
        <div><label class="text-xs font-semibold">Admin Email</label><input name="admin_email" value="admin@company.com" class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm" required></div>
        <div class="md:col-span-2"><label class="text-xs font-semibold">Admin Password</label><input name="admin_pass" value="admin123" type="password" class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm" required></div>
      </div>

      <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 font-semibold mt-2">Test & Install →</button>
      <div class="flex gap-2 text-xs justify-center">
        <a href="index.php" class="text-slate-500 hover:underline">Skip → Go to App</a>
        <span class="text-slate-300">|</span>
        <a href="fix-login.php" class="text-indigo-600 hover:underline">Fix Login</a>
      </div>
    </form>
    <div class="mt-6 text-xs text-slate-400 text-center">Upload folder: <code>file-server/upload/</code> (inside project) — auto created<br>After install, delete <code>install.php</code> for security on Hostinger</div>
  </div>
</div>
</body>
</html>
