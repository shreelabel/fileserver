<?php
// Advanced chunked upload for 2GB+ files
session_start();
header('Content-Type: application/json');
require __DIR__.'/../database/connection.php';
require __DIR__.'/../services/StorageService.php';
$pdo=getPDO();
$user=currentUser();
if(!$user){ http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$action=$_POST['action'] ?? $_GET['action'] ?? 'upload_chunk';

if($action==='init'){
    $filename=preg_replace('/[^a-zA-Z0-9._-]/','_', $_POST['filename']??'file');
    $totalSize=intval($_POST['totalSize']??0);
    $totalChunks=intval($_POST['totalChunks']??1);
    $folderId=$_POST['folder_id']??null; if($folderId==='root'||$folderId==='') $folderId=null;
    // quota check
    $used=$pdo->query("SELECT COALESCE(SUM(size),0) FROM files WHERE uploaded_by={$user['id']} AND deleted_at IS NULL")->fetchColumn();
    $quotaRow=$pdo->query("SELECT quota_gb FROM users WHERE id={$user['id']}")->fetchColumn();
    $quotaBytes=$quotaRow*1024*1024*1024;
    if($used + $totalSize > $quotaBytes){ http_response_code(400); echo json_encode(['error'=>"Quota exceeded: {$quotaRow}GB limit"]); exit; }
    $uploadId=bin2hex(random_bytes(8));
    $tmpDir=__DIR__.'/../storage/tmp/'.$uploadId;
    mkdir($tmpDir,0775,true);
    file_put_contents($tmpDir.'/meta.json', json_encode(['filename'=>$filename,'totalSize'=>$totalSize,'totalChunks'=>$totalChunks,'folderId'=>$folderId,'user'=>$user['id']]));
    echo json_encode(['ok'=>true,'uploadId'=>$uploadId]);
    exit;
}

if($action==='upload_chunk'){
    $uploadId=$_POST['uploadId']??'';
    $chunkIndex=intval($_POST['chunkIndex']??0);
    $tmpDir=__DIR__.'/../storage/tmp/'.$uploadId;
    if(!is_dir($tmpDir)){ http_response_code(404); echo json_encode(['error'=>'Upload session not found']); exit; }
    if(empty($_FILES['chunk'])){ http_response_code(400); echo json_encode(['error'=>'No chunk']); exit; }
    $dest=$tmpDir."/chunk_$chunkIndex";
    move_uploaded_file($_FILES['chunk']['tmp_name'], $dest);
    echo json_encode(['ok'=>true,'chunk'=>$chunkIndex]);
    exit;
}

if($action==='complete'){
    $uploadId=$_POST['uploadId']??'';
    $tmpDir=__DIR__.'/../storage/tmp/'.$uploadId;
    $meta=json_decode(file_get_contents($tmpDir.'/meta.json'),true);
    $filename=$meta['filename']; $folderId=$meta['folderId'];
    $totalChunks=$meta['totalChunks'];
    // verify all chunks present
    for($i=0;$i<$totalChunks;$i++) if(!file_exists("$tmpDir/chunk_$i")){ http_response_code(400); echo json_encode(['error'=>"Missing chunk $i"]); exit; }
    // assemble to storage
    require __DIR__.'/../config/storage.php'; // not needed
    $svc=new StorageService();
    // build storage path
    $folderPath='';
    if($folderId){
        $parts=[]; $cur=$folderId;
        while($cur){ $st=$pdo->prepare("SELECT name,parent_id FROM folders WHERE id=?"); $st->execute([$cur]); $r=$st->fetch(); if(!$r) break; array_unshift($parts, preg_replace('/[^a-zA-Z0-9._-]/','_',$r['name'])); $cur=$r['parent_id']; }
        $folderPath=implode('/',$parts);
    }
    $safe=preg_replace('/[^a-zA-Z0-9._-]/','_',$filename);
    $uuid=bin2hex(random_bytes(6));
    $storagePath=trim($folderPath.'/'.$uuid.'_'.$safe,'/');
    $full=__DIR__.'/../storage/local/'.$storagePath;
    $dir=dirname($full); if(!is_dir($dir)) mkdir($dir,0775,true);
    $out=fopen($full,'wb');
    for($i=0;$i<$totalChunks;$i++){ $in=fopen("$tmpDir/chunk_$i",'rb'); stream_copy_to_stream($in,$out); fclose($in); }
    fclose($out);
    $size=filesize($full);
    $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    $mime=mime_content_type($full) ?: 'application/octet-stream';
    $pdo->prepare("INSERT INTO files (folder_id,name,original_name,extension,mime_type,size,storage_provider,storage_path,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$folderId?:null,$filename,$filename,$ext,$mime,$size,$svc->driver(),$storagePath,$user['id']]);
    $fid=$pdo->lastInsertId();
    logActivity($pdo,'upload','file',$fid,$filename,"Chunked upload {$size} bytes");
    // cleanup
    array_map('unlink', glob("$tmpDir/chunk_*")); unlink("$tmpDir/meta.json"); rmdir($tmpDir);
    echo json_encode(['ok'=>true,'id'=>$fid,'size'=>$size]);
    exit;
}
http_response_code(400); echo json_encode(['error'=>'unknown']);
