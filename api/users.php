<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../database/connection.php';
$pdo=getPDO();
$user=currentUser();
if(!$user){ http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
// check admin
$stmt=$pdo->prepare("SELECT role FROM users WHERE id=?"); $stmt->execute([$user['id']]); $role=$stmt->fetchColumn();
$isAdmin = ($role==='admin');

$action=$_GET['action'] ?? $_POST['action'] ?? '';

if($action==='list'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $users=$pdo->query("SELECT id,name,email,role,quota_gb,status,created_at FROM users ORDER BY id DESC")->fetchAll();
    // compute used per user
    foreach($users as &$u){
        $st=$pdo->prepare("SELECT COALESCE(SUM(size),0) FROM files WHERE uploaded_by=? AND deleted_at IS NULL"); $st->execute([$u['id']]);
        $u['used']=$st->fetchColumn();
    }
    echo json_encode(['users'=>$users]);
    exit;
}
if($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??'')); $pass=$_POST['password']??''; $role=$_POST['role']??'user'; $quota=intval($_POST['quota_gb']??10);
    if(!$name||!$email||!$pass) { http_response_code(400); echo json_encode(['error'=>'Name, email, password required']); exit; }
    $hash=password_hash($pass, PASSWORD_DEFAULT);
    try{
        $pdo->prepare("INSERT INTO users (name,email,password,role,quota_gb) VALUES (?,?,?,?,?)")->execute([$name,$email,$hash,$role,$quota]);
        $id=$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_permissions (user_id) VALUES (?)")->execute([$id]);
        
        // Create default folders for the new user
        $defaultFolders = ['Artwork','Plate Files','Customer Documents','Job Documents','Production Documents'];
        foreach ($defaultFolders as $f) {
            $pdo->prepare("INSERT INTO folders (parent_id,name,created_by) VALUES (NULL,?,?)")->execute([$f, $id]);
        }
        
        logActivity($pdo,'create','user',$id,$email,"Created $role with {$quota}GB");
        echo json_encode(['ok'=>true,'id'=>$id]);
    } catch(Exception $e){ http_response_code(400); echo json_encode(['error'=>$e->getMessage()]); }
    exit;
}
if($action==='update_quota'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $id=intval($_POST['id']); $quota=intval($_POST['quota_gb']);
    $pdo->prepare("UPDATE users SET quota_gb=? WHERE id=?")->execute([$quota,$id]);
    echo json_encode(['ok'=>true]); exit;
}
if($action==='update_role'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $id=intval($_POST['id']); $role=$_POST['role'];
    $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,$id]);
    echo json_encode(['ok'=>true]); exit;
}
if($action==='delete'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }
    $id=intval($_POST['id']);
    if($id==$user['id']){ http_response_code(400); echo json_encode(['error'=>'Cannot delete yourself']); exit;}
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]); exit;
}
if($action==='permissions'){
    $uid=intval($_GET['user_id'] ?? $user['id']);
    if($uid!=$user['id'] && !$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit;}
    $st=$pdo->prepare("SELECT * FROM user_permissions WHERE user_id=?"); $st->execute([$uid]); $p=$st->fetch();
    if(!$p){ $pdo->prepare("INSERT INTO user_permissions (user_id) VALUES (?)")->execute([$uid]); $p=['can_upload'=>1,'can_download'=>1,'can_share'=>1,'can_delete'=>0,'can_create_folder'=>1];}
    echo json_encode(['permissions'=>$p]); exit;
}
if($action==='save_permissions' && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); echo json_encode(['error'=>'Admin only']); exit;}
    $uid=intval($_POST['user_id']);
    $fields=['can_upload','can_download','can_share','can_delete','can_create_folder'];
    $vals=[]; foreach($fields as $f) $vals[$f]=isset($_POST[$f])?1:0;
    $pdo->prepare("UPDATE user_permissions SET can_upload=?,can_download=?,can_share=?,can_delete=?,can_create_folder=? WHERE user_id=?")->execute([$vals['can_upload'],$vals['can_download'],$vals['can_share'],$vals['can_delete'],$vals['can_create_folder'],$uid]);
    echo json_encode(['ok'=>true]); exit;
}
http_response_code(400); echo json_encode(['error'=>'unknown']);
