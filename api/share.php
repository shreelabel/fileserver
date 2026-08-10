<?php
session_start();
header('Content-Type: application/json');
require __DIR__.'/../database/connection.php';
$pdo=getPDO();
$user=currentUser();
if(!$user){ http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$action=$_GET['action'] ?? $_POST['action'] ?? '';

if($action==='create' && $_SERVER['REQUEST_METHOD']==='POST'){
    $file_id=$_POST['file_id']??null; $folder_id=$_POST['folder_id']??null;
    $share_with=$_POST['share_with']??null; // email
    $permission=$_POST['permission']??'viewer'; // viewer, editor, download
    if(!$file_id && !$folder_id){ http_response_code(400); echo json_encode(['error'=>'file_id or folder_id required']); exit; }
    $share_with_id=null;
    if($share_with){
        $st=$pdo->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?)"); $st->execute([$share_with]); $u=$st->fetch();
        if(!$u){ http_response_code(404); echo json_encode(['error'=>'User not found: '.$share_with]); exit; }
        $share_with_id=$u['id'];
    }
    $token=bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO file_shares (file_id,folder_id,shared_by,shared_with,share_token,permission) VALUES (?,?,?,?,?,?)")->execute([$file_id?:null,$folder_id?:null,$user['id'],$share_with_id,$token,$permission]);
    logActivity($pdo,'share', $file_id?'file':'folder', $file_id?:$folder_id, $share_with?:'link', "Shared as $permission");
    echo json_encode(['ok'=>true,'token'=>$token,'link'=>"http://localhost/file-server/api/share.php?action=view&token=$token"]);
    exit;
}
if($action==='list'){
    $uid=$user['id'];
    $rows=$pdo->query("SELECT fs.*, u.email as shared_with_email, f.name as file_name, fo.name as folder_name FROM file_shares fs LEFT JOIN users u ON u.id=fs.shared_with LEFT JOIN files f ON f.id=fs.file_id LEFT JOIN folders fo ON fo.id=fs.folder_id WHERE fs.shared_by=$uid OR fs.shared_with=$uid ORDER BY fs.created_at DESC")->fetchAll();
    echo json_encode(['shares'=>$rows]); exit;
}
if($action==='shared_with_me'){
    $uid=$user['id'];
    $files=$pdo->query("SELECT f.*, fs.permission, fs.created_at as shared_at, u.name as shared_by_name FROM files f JOIN file_shares fs ON fs.file_id=f.id WHERE fs.shared_with=$uid AND f.deleted_at IS NULL")->fetchAll();
    $folders=$pdo->query("SELECT fo.*, fs.permission, fs.created_at as shared_at, u.name as shared_by_name FROM folders fo JOIN file_shares fs ON fs.folder_id=fo.id WHERE fs.shared_with=$uid AND fo.deleted_at IS NULL")->fetchAll();
    echo json_encode(['files'=>$files,'folders'=>$folders]); exit;
}
if($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST'){
    $id=intval($_POST['id']);
    $pdo->prepare("DELETE FROM file_shares WHERE id=? AND (shared_by=? OR shared_with=?)")->execute([$id,$user['id'],$user['id']]);
    echo json_encode(['ok'=>true]); exit;
}
http_response_code(400); echo json_encode(['error'=>'unknown']);
