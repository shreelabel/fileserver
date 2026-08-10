<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../database/connection.php';
require __DIR__ . '/../services/StorageService.php';

$pdo = getPDO();
$user = currentUser();
if (!$user) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isAdmin = false;
try { $role = $pdo->query("SELECT role FROM users WHERE id=".(int)$user['id'])->fetchColumn(); $isAdmin = ($role==='admin'); } catch(Exception $e){}

try {
if ($action === 'list') {
    $folderId = $_GET['folder_id'] ?? null;
    $search = trim($_GET['q'] ?? '');
    $filter = $_GET['filter'] ?? 'all';
    $sort = $_GET['sort'] ?? 'name';
    $order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    $view = $_GET['view'] ?? 'grid';
    $dateFilter = $_GET['dateFilter'] ?? 'all';

    $allowedSort = ['name'=>'name','size'=>'size','date'=>'created_at','type'=>'extension','modified'=>'updated_at'];
    $sortCol = $allowedSort[$sort] ?? 'name';

    // folders
    $fSql = "SELECT f.*, 'folder' as item_type, (SELECT COUNT(*) FROM files WHERE folder_id=f.id AND deleted_at IS NULL) as file_count FROM folders f WHERE f.deleted_at IS NULL AND f.created_by=? AND ";
    $fParams = [$user['id']];
    if ($folderId === null || $folderId === '' || $folderId === 'root') { $fSql .= "f.parent_id IS NULL"; }
    else { $fSql .= "f.parent_id=?"; $fParams[] = $folderId; }
    if ($search !== '') { $fSql .= " AND f.name LIKE ?"; $fParams[] = "%$search%"; }
    if ($dateFilter === 'today') { $fSql .= " AND date(f.created_at) >= date('now')"; }
    else if ($dateFilter === '7days') { $fSql .= " AND f.created_at >= datetime('now', '-7 days')"; }
    else if ($dateFilter === '30days') { $fSql .= " AND f.created_at >= datetime('now', '-30 days')"; }
    $fSql .= " ORDER BY f.name ASC";
    $stmt = $pdo->prepare($fSql);
    $stmt->execute($fParams);
    $folders = $stmt->fetchAll();
    foreach($folders as &$f){ $f['is_starred'] = isStarred($pdo,$user['id'],null,$f['id']); }

    // files
    $sql = "SELECT f.*, 'file' as item_type, CASE WHEN sf.id IS NOT NULL THEN 1 ELSE 0 END as is_starred FROM files f LEFT JOIN starred_files sf ON sf.file_id=f.id AND sf.user_id=? WHERE f.deleted_at IS NULL AND f.uploaded_by=? AND ";
    $params = [$user['id'],$user['id']];
    if ($folderId === null || $folderId === '' || $folderId === 'root') $sql .= "f.folder_id IS NULL";
    else { $sql .= "f.folder_id=?"; $params[]=$folderId; }
    if ($search !== '') { $sql .= " AND f.name LIKE ?"; $params[]="%$search%"; }
    if ($dateFilter === 'today') { $sql .= " AND date(f.created_at) >= date('now')"; }
    else if ($dateFilter === '7days') { $sql .= " AND f.created_at >= datetime('now', '-7 days')"; }
    else if ($dateFilter === '30days') { $sql .= " AND f.created_at >= datetime('now', '-30 days')"; }
    // filter
    $filterMap = [
        'images'=>["mime_type LIKE ?","image/%"],
        'pdf'=>["extension=?","pdf"],
        'documents'=>["extension IN (?,?,?,?)", ["doc","docx","txt","rtf"]],
        'excel'=>["extension IN (?,?)", ["xls","xlsx"]],
        'video'=>["mime_type LIKE ?","video/%"],
        'zip'=>["extension IN (?,?)", ["zip","rar"]],
    ];
    if (isset($filterMap[$filter])) {
        $cond = $filterMap[$filter][0];
        $val = $filterMap[$filter][1];
        if (is_array($val)) { $sql .= " AND $cond"; foreach($val as $v) $params[]=$v; }
        else { $sql .= " AND $cond"; $params[]=$val; }
    }
    $sql .= " ORDER BY $sortCol $order";
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    $files=$stmt->fetchAll();

    echo json_encode(['folders'=>$folders,'files'=>$files]);
    exit;
}

if ($action === 'create_folder') {
    $name = trim($_POST['name'] ?? '');
    $parent = $_POST['parent_id'] ?? null;
    if ($parent === 'root' || $parent === '') $parent = null;
    if ($name === '') throw new Exception('Folder name required');
    // prevent duplicate
    $stmt=$pdo->prepare("INSERT INTO folders (parent_id,name,created_by) VALUES (?,?,?)");
    $stmt->execute([$parent,$name,$user['id']]);
    $id=$pdo->lastInsertId();
    // physical dir via adapter
    $svc=new StorageService();
    // Build path from folder hierarchy
    $path = buildFolderPath($pdo, $id);
    $prefix = getStoragePrefix($pdo, $user['id']);
    $fullPath = trim($prefix . '/' . $path, '/');
    $svc->adapter()->makeDirectory($fullPath);
    logActivity($pdo,'create','folder',$id,$name,"Created folder");
    echo json_encode(['ok'=>true,'id'=>$id]);
    exit;
}

if ($action === 'upload') {
    $folderId = $_POST['folder_id'] ?? null;
    if ($folderId === 'root' || $folderId === '') $folderId=null;
    if (empty($_FILES['files'])) throw new Exception('No files');
    $svc=new StorageService();
    $cfg=require __DIR__.'/../config/storage.php';
    $uploaded=[];
    foreach($_FILES['files']['name'] as $idx=>$origName){
        $tmp=$_FILES['files']['tmp_name'][$idx];
        $size=$_FILES['files']['size'][$idx];
        $err=$_FILES['files']['error'][$idx];
        if($err!==UPLOAD_ERR_OK) continue;
        if($size > $cfg['max_upload_size']) throw new Exception("$origName exceeds max size");
        $ext=strtolower(pathinfo($origName,PATHINFO_EXTENSION));
        $mime=mime_content_type($tmp) ?: 'application/octet-stream';
        // storage_path: build folder path + uuid
        $folderPath = $folderId ? buildFolderPath($pdo,$folderId) : '';
        $prefix = getStoragePrefix($pdo, $user['id']);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/','_',$origName);
        $uuid = bin2hex(random_bytes(6));
        $storagePath = trim($prefix . '/' . $folderPath . '/' . $uuid . '_' . $safeName, '/');
        $svc->storeUploadedFile(['tmp_name'=>$tmp], $storagePath);
        $stmt=$pdo->prepare("INSERT INTO files (folder_id,name,original_name,extension,mime_type,size,storage_provider,storage_path,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$folderId,$origName,$origName,$ext,$mime,$size,$svc->driver(),$storagePath,$user['id']]);
        $fid=$pdo->lastInsertId();
        logActivity($pdo,'upload','file',$fid,$origName,"Uploaded to ".($folderPath?:'Home'));
        $uploaded[]=['id'=>$fid,'name'=>$origName];
    }
    echo json_encode(['ok'=>true,'uploaded'=>$uploaded]);
    exit;
}

if ($action === 'rename') {
    $id=$_POST['id'] ?? 0; $type=$_POST['type'] ?? 'file'; $newName=trim($_POST['name'] ?? '');
    if($newName==='') throw new Exception('Name required');
    if($type==='folder'){
        $pdo->prepare("UPDATE folders SET name=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND created_by=?")->execute([$newName,$id,$user['id']]);
        logActivity($pdo,'rename','folder',$id,$newName,'Renamed folder');
    } else {
        $pdo->prepare("UPDATE files SET name=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND uploaded_by=?")->execute([$newName,$id,$user['id']]);
        logActivity($pdo,'rename','file',$id,$newName,'Renamed file');
    }
    echo json_encode(['ok'=>true]); exit;
}

if ($action === 'move') {
    $id=$_POST['id'] ?? 0; $type=$_POST['type'] ?? 'file'; $target=$_POST['target_folder_id'] ?? null;
    if($target==='root'||$target==='') $target=null;
    if($type==='folder'){
        $pdo->prepare("UPDATE folders SET parent_id=? WHERE id=?")->execute([$target,$id]);
    } else {
        $row=$pdo->query("SELECT * FROM files WHERE id=$id")->fetch();
        // move physical if local
        if($row){
            $svc=new StorageService();
            $oldPath=$row['storage_path'];
            $folderPath=$target?buildFolderPath($pdo,$target):'';
            $prefix = getStoragePrefix($pdo, $row['uploaded_by']);
            $base=basename($oldPath);
            $newPath=trim($prefix . '/' . $folderPath.'/'.$base,'/');
            if($svc->driver()==='local' && $oldPath!==$newPath){
                $svc->adapter()->move($oldPath,$newPath);
                $pdo->prepare("UPDATE files SET folder_id=?, storage_path=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$target,$newPath,$id]);
            } else {
                $pdo->prepare("UPDATE files SET folder_id=? WHERE id=?")->execute([$target,$id]);
            }
        }
    }
    logActivity($pdo,'move',$type,$id,'','Moved');
    echo json_encode(['ok'=>true]); exit;
}

if ($action === 'copy') {
    $id=$_POST['id'] ?? 0;
    $row=$pdo->prepare("SELECT * FROM files WHERE id=?"); $row->execute([$id]); $f=$row->fetch();
    if(!$f) throw new Exception('File not found');
    $svc=new StorageService();
    $newPath = dirname($f['storage_path']).'/copy_'.bin2hex(random_bytes(4)).'_'.basename($f['storage_path']);
    $svc->adapter()->copy($f['storage_path'],$newPath);
    $pdo->prepare("INSERT INTO files (folder_id,name,original_name,extension,mime_type,size,storage_provider,storage_path,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$f['folder_id'],'Copy of '.$f['name'],$f['original_name'],$f['extension'],$f['mime_type'],$f['size'],$svc->driver(),$newPath,$user['id']]);
    logActivity($pdo,'copy','file',$id,$f['name'],'Copied');
    echo json_encode(['ok'=>true]); exit;
}

if ($action === 'delete') {
    $id=$_POST['id'] ?? 0; $type=$_POST['type'] ?? 'file';
    if(!$isAdmin) {
        http_response_code(403); echo json_encode(['error'=>'Only Admin can delete']); exit;
    }
    if($type==='folder') $pdo->prepare("UPDATE folders SET deleted_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
    else $pdo->prepare("UPDATE files SET deleted_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
    logActivity($pdo,'delete',$type,$id,'','Moved to trash');
    echo json_encode(['ok'=>true]); exit;
}

if ($action === 'restore') {
    $id=$_POST['id'] ?? 0; $type=$_POST['type'] ?? 'file';
    if(!$isAdmin) {
        http_response_code(403); echo json_encode(['error'=>'Only Admin can restore']); exit;
    }
    if($type==='folder') $pdo->prepare("UPDATE folders SET deleted_at=NULL WHERE id=?")->execute([$id]);
    else $pdo->prepare("UPDATE files SET deleted_at=NULL WHERE id=?")->execute([$id]);
    logActivity($pdo,'restore',$type,$id,'','Restored');
    echo json_encode(['ok'=>true]); exit;
}

if ($action === 'permanent_delete') {
    $id=$_POST['id'] ?? 0; $type=$_POST['type'] ?? 'file';
    if(!$isAdmin) {
        http_response_code(403); echo json_encode(['error'=>'Only Admin can delete permanently']); exit;
    }
    $svc=new StorageService();
    if($type==='folder'){
        $path=buildFolderPath($pdo,$id);
        $prefix = getStoragePrefix($pdo, $user['id']);
        $svc->adapter()->deleteDirectory(trim($prefix . '/' . $path, '/'));
        $pdo->prepare("DELETE FROM folders WHERE id=?")->execute([$id]);
    } else {
        $row=$pdo->query("SELECT storage_path FROM files WHERE id=$id")->fetch();
        if($row) $svc->adapter()->delete($row['storage_path']);
        $pdo->prepare("DELETE FROM files WHERE id=?")->execute([$id]);
    }
    logActivity($pdo,'permanent_delete',$type,$id,'','Permanently deleted');
    echo json_encode(['ok'=>true]); exit;
}

if ($action === 'star') {
    $id=$_POST['id'] ?? 0; $type=$_POST['type'] ?? 'file';
    $fileId = $type==='file' ? $id : null;
    $folderId = $type==='folder' ? $id : null;
    $exists=$pdo->prepare("SELECT id FROM starred_files WHERE user_id=? AND (file_id=? OR folder_id=?)");
    // simpler: check
    $chk=$pdo->prepare("SELECT id FROM starred_files WHERE user_id=? AND ".($type==='file'?"file_id=?":"folder_id=?"));
    $chk->execute([$user['id'],$id]);
    if($chk->fetch()){
        $pdo->prepare("DELETE FROM starred_files WHERE user_id=? AND ".($type==='file'?"file_id=?":"folder_id=?"))->execute([$user['id'],$id]);
        $starred=false;
    } else {
        $pdo->prepare("INSERT INTO starred_files (user_id,file_id,folder_id) VALUES (?,?,?)")->execute([$user['id'],$fileId,$folderId]);
        $starred=true;
    }
    logActivity($pdo,$starred?'star':'unstar',$type,$id,'','');
    echo json_encode(['ok'=>true,'starred'=>$starred]); exit;
}

if ($action === 'trash_list') {
    $files=$pdo->query("SELECT *, 'file' as item_type FROM files WHERE deleted_at IS NOT NULL AND uploaded_by={$user['id']} ORDER BY deleted_at DESC")->fetchAll();
    $folders=$pdo->query("SELECT *, 'folder' as item_type FROM folders WHERE deleted_at IS NOT NULL AND created_by={$user['id']} ORDER BY deleted_at DESC")->fetchAll();
    echo json_encode(['files'=>$files,'folders'=>$folders]); exit;
}
if ($action === 'starred_list') {
    $files=$pdo->query("SELECT f.*, 'file' as item_type FROM files f JOIN starred_files s ON s.file_id=f.id WHERE s.user_id={$user['id']} AND f.deleted_at IS NULL")->fetchAll();
    $folders=$pdo->query("SELECT fo.*, 'folder' as item_type FROM folders fo JOIN starred_files s ON s.folder_id=fo.id WHERE s.user_id={$user['id']} AND fo.deleted_at IS NULL")->fetchAll();
    echo json_encode(['files'=>$files,'folders'=>$folders]); exit;
}
if ($action === 'recent') {
    $logs=$pdo->query("SELECT * FROM activity_logs WHERE user_id={$user['id']} ORDER BY created_at DESC LIMIT 20")->fetchAll();
    $files=$pdo->query("SELECT * FROM files WHERE deleted_at IS NULL AND uploaded_by={$user['id']} ORDER BY updated_at DESC LIMIT 12")->fetchAll();
    echo json_encode(['logs'=>$logs,'files'=>$files]); exit;
}
if ($action === 'stats') {
    $totalFiles=$pdo->query("SELECT COUNT(*) FROM files WHERE deleted_at IS NULL AND uploaded_by={$user['id']}")->fetchColumn();
    $totalFolders=$pdo->query("SELECT COUNT(*) FROM folders WHERE deleted_at IS NULL AND created_by={$user['id']}")->fetchColumn();
    $used=$pdo->query("SELECT COALESCE(SUM(size),0) FROM files WHERE deleted_at IS NULL AND uploaded_by={$user['id']}")->fetchColumn();
    $userRow = $pdo->query("SELECT quota_gb FROM users WHERE id={$user['id']}")->fetch();
    $quota = intval($userRow['quota_gb']) * 1024 * 1024 * 1024;
    // real counts by type for interactive chart (fixes mock data issue)
    $types = ['images'=>0,'pdf'=>0,'docs'=>0,'excel'=>0,'zip'=>0,'other'=>0];
    $rows=$pdo->query("SELECT extension, mime_type, COUNT(*) as cnt FROM files WHERE deleted_at IS NULL AND uploaded_by={$user['id']} GROUP BY extension, mime_type")->fetchAll();
    foreach($rows as $r){
        $ext=strtolower($r['extension']??''); $mime=strtolower($r['mime_type']??''); $cnt=intval($r['cnt']);
        if(strpos($mime,'image/')===0 || in_array($ext,['jpg','jpeg','png','webp','gif','bmp','svg'])) $types['images']+=$cnt;
        elseif($ext==='pdf') $types['pdf']+=$cnt;
        elseif(in_array($ext,['doc','docx','txt','rtf'])) $types['docs']+=$cnt;
        elseif(in_array($ext,['xls','xlsx','csv'])) $types['excel']+=$cnt;
        elseif(in_array($ext,['zip','rar','7z'])) $types['zip']+=$cnt;
        else $types['other']+=$cnt;
    }
    echo json_encode(['totalFiles'=>$totalFiles,'totalFolders'=>$totalFolders,'used'=>$used,'quota'=>$quota,'byType'=>$types]); exit;
}
if ($action === 'breadcrumb') {
    $id=$_GET['folder_id'] ?? null;
    if($id==='root'||$id==''||$id===null) { echo json_encode(['trail'=>[]]); exit; }
    $trail=[]; $cur=$id;
    while($cur){
        $stmt=$pdo->prepare("SELECT id,name,parent_id FROM folders WHERE id=?"); $stmt->execute([$cur]); $row=$stmt->fetch();
        if(!$row) break;
        array_unshift($trail,$row);
        $cur=$row['parent_id'];
    }
    echo json_encode(['trail'=>$trail]); exit;
}

http_response_code(400); echo json_encode(['error'=>'unknown action']);
} catch(Exception $e){ http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }

function buildFolderPath(PDO $pdo, $folderId): string {
    $parts=[]; $cur=$folderId;
    while($cur){
        $stmt=$pdo->prepare("SELECT name,parent_id FROM folders WHERE id=?"); $stmt->execute([$cur]); $r=$stmt->fetch();
        if(!$r) break;
        array_unshift($parts, preg_replace('/[^a-zA-Z0-9._-]/','_',$r['name']));
        $cur=$r['parent_id'];
    }
    return implode('/',$parts);
}
function getStoragePrefix(PDO $pdo, $userId): string {
    $stmt=$pdo->prepare("SELECT name FROM users WHERE id=?"); $stmt->execute([$userId]);
    $uName=$stmt->fetchColumn() ?: 'User_'.$userId;
    $uNameSafe = preg_replace('/[^a-zA-Z0-9._-]/','_', $uName);
    return $uNameSafe;
}
function isStarred(PDO $pdo, $uid,$fid,$folderId){
    if($fid) $sql="SELECT 1 FROM starred_files WHERE user_id=? AND file_id=?";
    else $sql="SELECT 1 FROM starred_files WHERE user_id=? AND folder_id=?";
    $st=$pdo->prepare($sql); $st->execute([$uid,$fid?:$folderId]);
    return (bool)$st->fetchColumn();
}
