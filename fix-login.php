<?php
// FIX-LOGIN - MySQL ONLY version - open http://localhost/file-server/fix-login.php
error_reporting(E_ALL); ini_set('display_errors',1);
require __DIR__ . '/database/connection.php';
echo '<style>body{font-family:system-ui;padding:24px;max-width:800px;margin:auto}code{background:#f1f5f9;padding:2px 6px;border-radius:6px}a{color:#4f46e5}table{border-collapse:collapse}th,td{border:1px solid #e2e8f0;padding:6px 10px;text-align:left}</style>';

try { $pdo = getPDO(); } catch(Exception $e){
    die("<h2 style='color:red'>❌ MySQL Connection Failed</h2><pre>".htmlspecialchars($e->getMessage())."</pre>
    <h3>XAMPP-এ MySQL চালু করুন:</h3>
    <ol>
      <li>XAMPP Control Panel খুলুন → MySQL <b>Start</b> করুন (green হতে হবে)</li>
      <li>যদি Port 3306 busy দেখায়: XAMPP → MySQL → Config → my.ini → port 3307 করুন, আর config/database.php-এ port 3307 দিন</li>
      <li>phpMyAdmin খুলুন: http://localhost/phpmyadmin → file_server DB আছে কিনা দেখুন</li>
      <li>যদি DB না থাকে: phpMyAdmin → Import → database/mysql_schema.sql upload করুন</li>
      <li>তারপর এই পেজ reload করুন</li>
    </ol>
    <p>Config: <code>config/database.php</code> → username=root, password=(খালি) - XAMPP default</p>");
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "<h2>✅ MySQL Connected: <span style='color:green'>$driver</span></h2>";
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "<p>Database: <b>".htmlspecialchars($dbName)."</b> (should be file_server)</p>";

$rows = $pdo->query("SELECT id,name,email,password,created_at FROM users")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Users (".count($rows).")</h3><table><tr><th>id</th><th>name</th><th>email</th><th>password</th></tr>";
foreach($rows as $r){ 
    $ok = password_verify('admin123',$r['password'])?"<span style='color:green'>✓ admin123 OK</span>":"<span style='color:red'>✗ NOT admin123</span>";
    echo "<tr><td>{$r['id']}</td><td>".htmlspecialchars($r['name'])."</td><td>".htmlspecialchars($r['email'])."</td><td><code>".htmlspecialchars(substr($r['password'],0,35))."...</code> $ok</td></tr>";
}
if(!count($rows)) echo "<tr><td colspan=4 style='color:red'>No users! Click reset below.</td></tr>";
echo "</table>";

if(isset($_GET['reset'])){
    $email='admin@company.com';
    $hash=password_hash('admin123', PASSWORD_DEFAULT);
    $exists=$pdo->prepare("SELECT id FROM users WHERE email=?"); $exists->execute([$email]); $row=$exists->fetch();
    if($row){
        $pdo->prepare("UPDATE users SET password=?, name='Admin User' WHERE email=?")->execute([$hash,$email]);
        echo "<p style='background:#dcfce7;padding:12px;border-radius:10px'><b>✓ Reset done:</b> $email → <b>admin123</b></p>";
    } else {
        $pdo->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)")->execute(['Admin User',$email,$hash]);
        echo "<p style='background:#dcfce7;padding:12px;border-radius:10px'><b>✓ Created:</b> $email / admin123</p>";
    }
    echo "<p><a href='index.php' style='background:#111;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none'>→ Go to Login</a></p>";
} else {
    echo "<p><a href='?reset=1' style='background:#111;color:#fff;padding:12px 18px;border-radius:10px;text-decoration:none;display:inline-block;margin-top:14px'>🔧 Reset admin@company.com → admin123</a></p>";
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['test_email'])){
    $e=trim($_POST['test_email']); $p=$_POST['test_pass'];
    $stmt=$pdo->prepare("SELECT * FROM users WHERE LOWER(email)=LOWER(?)"); $stmt->execute([$e]); $u=$stmt->fetch();
    $result = ($u && password_verify($p,$u['password']))?"<b style='color:green'>✓ SUCCESS - password matches</b>":"<b style='color:red'>✗ FAIL</b>";
    echo "<hr><p>Test for ".htmlspecialchars($e).": $result</p>";
    if($u) echo "<p>Found user id {$u['id']}, hash starts: ".htmlspecialchars(substr($u['password'],0,20))."</p>";
    else echo "<p style='color:red'>No user found with that email (case-insensitive search)</p>";
}
?>
<hr>
<form method="post" style="margin-top:12px">
  <h4>Test credentials</h4>
  <input name="test_email" value="admin@company.com" style="padding:8px;border:1px solid #ccc;border-radius:8px">
  <input name="test_pass" value="admin123" style="padding:8px;border:1px solid #ccc;border-radius:8px">
  <button style="padding:8px 14px;background:#4f46e5;color:#fff;border:none;border-radius:8px;cursor:pointer">Test</button>
</form>
<div style="margin-top:18px;background:#f8fafc;border:1px solid #e2e8f0;padding:14px;border-radius:12px">
<b>MySQL Setup (XAMPP):</b>
<ol style="font-size:14px;line-height:1.6">
  <li>XAMPP → MySQL Start (must be green)</li>
  <li>http://localhost/phpmyadmin → <b>file_server</b> DB দেখুন, না থাকলে Import → <code>database/mysql_schema.sql</code></li>
  <li>এই পেজে Reset বাটন চাপুন</li>
  <li>http://localhost/file-server/ → admin@company.com / admin123</li>
</ol>
</div>
