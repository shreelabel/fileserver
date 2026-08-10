<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../database/connection.php';
$pdo=getPDO();
$user=currentUser();
if(!$user){ http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
$role=$pdo->query("SELECT role FROM users WHERE id={$user['id']}")->fetchColumn();
$isAdmin=($role==='admin');

$action=$_GET['action'] ?? $_POST['action'] ?? '';

if($action==='get'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    // read from config file + DB overrides
    $cfg=require __DIR__.'/../config/storage.php';
    // merge DB active provider if exists
    try{
        $rows=$pdo->query("SELECT provider,is_active,config_json FROM storage_configs")->fetchAll();
        foreach($rows as $r){
            if(isset($cfg['drivers'][$r['provider']])){
                $j=json_decode($r['config_json'],true)?:[];
                $cfg['drivers'][$r['provider']]=array_merge($cfg['drivers'][$r['provider']],$j);
                $cfg['drivers'][$r['provider']]['is_active']=$r['is_active'];
                if($r['is_active']) $cfg['driver']=$r['provider'];
            }
        }
    }catch(Exception $e){}
    echo json_encode(['config'=>$cfg]);
    exit;
}

if($action==='save' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $provider=$_POST['provider'] ?? 'local';
    $is_active=intval($_POST['is_active']??0);
    $fields=['client_id','client_secret','redirect_uri','folder_id','endpoint','api_key','secret','bucket','region','root','path','host','port','username','password','secure','passive'];
    $data=[]; foreach($fields as $f) if(isset($_POST[$f])) $data[$f]=$_POST[$f];
    try{
        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_configs (id INT AUTO_INCREMENT PRIMARY KEY, provider VARCHAR(30) NOT NULL UNIQUE, config_json TEXT NULL, is_active TINYINT NOT NULL DEFAULT 0, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $existing = $pdo->prepare("SELECT config_json FROM storage_configs WHERE provider=?");
        $existing->execute([$provider]);
        $row = $existing->fetch();
        if ($row) {
            $existingData = json_decode($row['config_json'], true) ?: [];
            $data = array_merge($existingData, $data);
        }

        if($is_active){
            $pdo->exec("UPDATE storage_configs SET is_active=0");
        }
        $stmt=$pdo->prepare("INSERT INTO storage_configs (provider,config_json,is_active) VALUES (?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json), is_active=VALUES(is_active)");
        $stmt->execute([$provider, json_encode($data), $is_active?1:0]);
        logActivity($pdo,'update','storage',0,$provider,"Storage config saved");
        echo json_encode(['ok'=>true]);
    }catch(Exception $e){ http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
    exit;
}
if($action==='test_ftp' && $_SERVER['REQUEST_METHOD']==='POST'){
    $host=$_POST['host']??''; $port=intval($_POST['port']??21); $userFtp=$_POST['username']??''; $passFtp=$_POST['password']??'';
    if(!$host){ echo json_encode(['ok'=>false,'error'=>'Host required']); exit; }
    if(!function_exists('ftp_connect')){ echo json_encode(['ok'=>false,'error'=>'PHP FTP extension not enabled (enable php_ftp.dll in php.ini)']); exit; }
    $conn=@ftp_connect($host,$port,10);
    if(!$conn){ echo json_encode(['ok'=>false,'error'=>"Cannot connect to $host:$port"]); exit; }
    if(!@ftp_login($conn,$userFtp,$passFtp)){ echo json_encode(['ok'=>false,'error'=>'Login failed - check username/password']); exit; }
    ftp_close($conn);
    echo json_encode(['ok'=>true]); exit;
}
if($action==='test_google_drive' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    require_once __DIR__ . '/../adapters/GoogleDriveStorageAdapter.php';
    try {
        $cfg = require __DIR__.'/../config/storage.php';
        $gdConfig = $cfg['drivers']['google_drive'];
        $row = $pdo->query("SELECT config_json FROM storage_configs WHERE provider='google_drive'")->fetch();
        if($row) $gdConfig = array_merge($gdConfig, json_decode($row['config_json'], true) ?: []);
        
        if (empty($gdConfig['refresh_token'])) {
            echo json_encode(['ok'=>false, 'error'=>'Not Connected. Please click Connect Google Drive.']);
            exit;
        }

        $adapter = new GoogleDriveStorageAdapter($gdConfig);
        $adapter->listContents('');
        echo json_encode(['ok'=>true]);
    } catch(Exception $e) {
        echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}
if($action==='test_hostinger' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    require_once __DIR__ . '/../adapters/HostingerStorageAdapter.php';
    try {
        $cfg = require __DIR__.'/../config/storage.php';
        $s3Config = $cfg['drivers']['hostinger'];
        $fields=['endpoint','api_key','secret','bucket','region','root'];
        foreach($fields as $f) if(isset($_POST[$f])) $s3Config[$f]=$_POST[$f];
        
        $adapter = new HostingerStorageAdapter($s3Config);
        $adapter->listContents('');
        echo json_encode(['ok'=>true]);
    } catch(Exception $e) {
        echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}
if($action==='set_active' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $provider=$_POST['provider']??'local';
    $pdo->exec("UPDATE storage_configs SET is_active=0");
    $pdo->prepare("INSERT INTO storage_configs (provider,config_json,is_active) VALUES (?, '{}', 1) ON DUPLICATE KEY UPDATE is_active=1")->execute([$provider]);
    echo json_encode(['ok'=>true]); exit;
}
if($action==='save_general' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $name = $_POST['app_name'] ?? '';
    if ($name) {
        $cfgFile = __DIR__.'/../config/config.php';
        $content = file_get_contents($cfgFile);
        $content = preg_replace("/'app_name'\s*=>\s*'.*?'/", "'app_name' => '".addslashes($name)."'", $content);
        file_put_contents($cfgFile, $content);
    }
    echo json_encode(['ok'=>true]);
    exit;
}
if($action==='google_auth_url' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $cfg = require __DIR__.'/../config/storage.php';
    $gdConfig = $cfg['drivers']['google_drive'];
    $row = $pdo->query("SELECT config_json FROM storage_configs WHERE provider='google_drive'")->fetch();
    if($row) $gdConfig = array_merge($gdConfig, json_decode($row['config_json'], true) ?: []);

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'client_id' => $gdConfig['client_id'],
        'redirect_uri' => $gdConfig['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/drive',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state
    ]);
    echo json_encode(['ok'=>true, 'url'=>$url]);
    exit;
}
http_response_code(400); echo json_encode(['error'=>'unknown']);
