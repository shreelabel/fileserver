<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../database/connection.php';
try {
    $pdo = getPDO();
} catch(Exception $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'DB connection failed: '.$e->getMessage().' - start MySQL in XAMPP or check config/database.php']);
    exit;
}
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'login') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    if($email===''||$pass===''){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Email and password required']); exit; }
    // case-insensitive lookup to avoid user typo
    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        // helpful hint: show which DB we checked
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        http_response_code(401);
        echo json_encode(['ok'=>false,'error'=>'Invalid email or password']);
        exit;
    }
    if (password_verify($pass, $user['password'])) {
        // optional rehash if algorithm changed
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newHash, $user['id']]);
        }
        $_SESSION['user'] = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email']];
        logActivity($pdo,'login','auth',$user['id'],$user['email'],'User logged in');
        echo json_encode(['ok'=>true,'user'=>$_SESSION['user']]);
    } else {
        http_response_code(401);
        echo json_encode(['ok'=>false,'error'=>'Invalid email or password']);
    }
    exit;
}
if ($action === 'logout') {
    $u = $_SESSION['user'] ?? null;
    if ($u) try{ logActivity($pdo,'logout','auth',$u['id'],$u['email'],'User logged out'); }catch(Exception $e){}
    session_destroy();
    echo json_encode(['ok'=>true]);
    exit;
}
if ($action === 'me') {
    echo json_encode(['user'=> $_SESSION['user'] ?? null, 'driver'=> $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)]);
    exit;
}
http_response_code(400);
echo json_encode(['error'=>'Unknown action']);
