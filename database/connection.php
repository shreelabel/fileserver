<?php
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = require __DIR__ . '/../config/database.php';
    $connPref = $cfg['connection'];

    // Try MySQL first if auto or mysql
    if ($connPref === 'mysql' || $connPref === 'auto') {
        $m = $cfg['mysql'];
        try {
            $dsn = "mysql:host={$m['host']};port={$m['port']};charset={$m['charset']}";
            $tmp = new PDO($dsn, $m['username'], $m['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $tmp->exec("CREATE DATABASE IF NOT EXISTS `{$m['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $dsn2 = "mysql:host={$m['host']};port={$m['port']};dbname={$m['database']};charset={$m['charset']}";
            $pdo = new PDO($dsn2, $m['username'], $m['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS folders (id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NULL, name VARCHAR(255) NOT NULL, created_by INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, deleted_at DATETIME NULL) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS files (id INT AUTO_INCREMENT PRIMARY KEY, folder_id INT NULL, name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, extension VARCHAR(20) NULL, mime_type VARCHAR(120) NULL, size BIGINT NOT NULL DEFAULT 0, storage_provider VARCHAR(30) NOT NULL DEFAULT 'local', storage_path VARCHAR(500) NOT NULL, storage_file_id VARCHAR(255) NULL, thumbnail_path VARCHAR(500) NULL, uploaded_by INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, deleted_at DATETIME NULL) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS starred_files (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, file_id INT NULL, folder_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, file_id, folder_id)) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS file_shares (id INT AUTO_INCREMENT PRIMARY KEY, file_id INT NULL, folder_id INT NULL, shared_by INT NOT NULL, shared_with INT NULL, share_token VARCHAR(64) NULL, permission VARCHAR(20) NOT NULL DEFAULT 'viewer', created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, action VARCHAR(50) NOT NULL, target_type VARCHAR(20) NOT NULL, target_id INT NULL, target_name VARCHAR(255) NULL, details TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS storage_connections (id INT AUTO_INCREMENT PRIMARY KEY, provider VARCHAR(30) NOT NULL, config_json TEXT NULL, is_active TINYINT NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS file_versions (id INT AUTO_INCREMENT PRIMARY KEY, file_id INT NOT NULL, version_no INT NOT NULL, storage_path VARCHAR(500) NOT NULL, size BIGINT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            // Patch existing DB - add missing columns if upgrading from old version
            try { $pdo->query("SELECT role FROM users LIMIT 1"); } catch(Exception $e){ try{ $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'"); }catch(Exception $ex){} }
            try { $pdo->query("SELECT quota_gb FROM users LIMIT 1"); } catch(Exception $e){ try{ $pdo->exec("ALTER TABLE users ADD COLUMN quota_gb INT NOT NULL DEFAULT 10"); }catch(Exception $ex){} }
            try { $pdo->query("SELECT status FROM users LIMIT 1"); } catch(Exception $e){ try{ $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'"); }catch(Exception $ex){} }
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS storage_configs (id INT AUTO_INCREMENT PRIMARY KEY, provider VARCHAR(30) NOT NULL UNIQUE, config_json TEXT NULL, is_active TINYINT NOT NULL DEFAULT 0, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, can_upload TINYINT NOT NULL DEFAULT 1, can_download TINYINT NOT NULL DEFAULT 1, can_share TINYINT NOT NULL DEFAULT 1, can_delete TINYINT NOT NULL DEFAULT 0, can_create_folder TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
            ensureSeed($pdo);
            return $pdo;
        } catch (Exception $e) {
            if ($connPref === 'mysql') throw $e;
            // log for debug and fall through to sqlite
            error_log("MySQL fallback: ".$e->getMessage());
        }
    }

    // SQLite fallback
    $path = $cfg['sqlite']['path'];
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $needInit = !file_exists($path);
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    if ($needInit) {
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        $pdo->exec($sql);
    } else {
        // ensure tables exist even if file existed from older version
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        // try exec but ignore duplicate errors
        try { $pdo->exec($sql); } catch(Exception $e) {}
    }
    ensureSeed($pdo);
    return $pdo;
}

function ensureSeed(PDO $pdo) {
    try {
        $cnt = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch(Exception $e){ return; }
    if ($cnt == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
        $stmt->execute(['Admin User', 'admin@company.com', $hash]);
        $adminId = $pdo->lastInsertId();
        $folders = ['Artwork','Plate Files','Customer Documents','Job Documents','Production Documents'];
        foreach ($folders as $f) {
            $stmt = $pdo->prepare("INSERT INTO folders (parent_id,name,created_by) VALUES (NULL,?,?)");
            $stmt->execute([$f, $adminId]);
        }
        $pdo->prepare("INSERT INTO activity_logs (user_id,action,target_type,target_name) VALUES (?,?,?,?)")->execute([$adminId,'create','system','File Server initialized']);
        // ensure admin role
        try { $pdo->exec("UPDATE users SET role='admin' WHERE id=$adminId"); } catch(Exception $e) {}
        try { $pdo->exec("UPDATE users SET role='admin' WHERE email='admin@company.com'"); } catch(Exception $e) {}
    } else {
        // ensure admin password is admin123 if user complains about invalid password - only if explicitly requested via fix-login.php
        // do not auto-reset to avoid overwriting custom passwords
    }
}

function currentUser(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user'] ?? null;
}
function requireAuth() {
    if (!currentUser()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
}
function logActivity(PDO $pdo, string $action, string $targetType, $targetId, string $targetName, string $details='') {
    $u = currentUser();
    if (!$u) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id,action,target_type,target_id,target_name,details) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$u['id'], $action, $targetType, $targetId, $targetName, $details]);
    } catch(Exception $e) {}
}
