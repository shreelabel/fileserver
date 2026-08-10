<?php
session_start();
require __DIR__ . '/../database/connection.php';
require __DIR__ . '/../services/StorageService.php';
$user=currentUser();
if(!$user){ http_response_code(401); exit('Unauthorized'); }
$id=$_GET['id'] ?? 0;
$inline = isset($_GET['preview']);
$pdo=getPDO();
$stmt=$pdo->prepare("SELECT * FROM files WHERE id=? AND deleted_at IS NULL");
$stmt->execute([$id]);
$file=$stmt->fetch();
if(!$file){ http_response_code(404); exit('File not found'); }
if($file['uploaded_by'] != $user['id']){ http_response_code(403); exit('Forbidden'); }

$svc=new StorageService();
$adapter=$svc->adapter();
if(!$adapter->exists($file['storage_path'])){ http_response_code(404); exit('File missing on storage'); }

$mime=$file['mime_type'] ?: 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Length: '.$file['size']);
if($inline){
    header('Content-Disposition: inline; filename="'.addslashes($file['name']).'"');
} else {
    header('Content-Disposition: attachment; filename="'.addslashes($file['name']).'"');
    // log download
    logActivity($pdo,'download','file',$file['id'],$file['name'],'Downloaded');
}
header('X-Content-Type-Options: nosniff');
$stream=$adapter->stream($file['storage_path']);
fpassthru($stream);
fclose($stream);
