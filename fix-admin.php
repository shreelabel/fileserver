<?php
require __DIR__ . '/database/connection.php';
$pdo=getPDO();

// Auto-patch missing columns (role, quota_gb, status) if DB was created before patch
function addColumnIfMissing($pdo, $table, $column, $def){
    try { $pdo->query("SELECT $column FROM $table LIMIT 1"); } catch(Exception $e){
        try { $pdo->exec("ALTER TABLE $table ADD COLUMN $column $def"); echo "Added column $table.$column<br>"; } catch(Exception $e2){ echo "Failed to add $column: ".htmlspecialchars($e2->getMessage())."<br>"; }
    }
}
addColumnIfMissing($pdo,'users','role',"VARCHAR(20) NOT NULL DEFAULT 'user'");
addColumnIfMissing($pdo,'users','quota_gb',"INT NOT NULL DEFAULT 10");
addColumnIfMissing($pdo,'users','status',"VARCHAR(20) NOT NULL DEFAULT 'active'");

// Also ensure storage_configs and user_permissions tables
try { $pdo->exec("CREATE TABLE IF NOT EXISTS storage_configs (id INT AUTO_INCREMENT PRIMARY KEY, provider VARCHAR(30) NOT NULL UNIQUE, config_json TEXT NULL, is_active TINYINT NOT NULL DEFAULT 0, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, can_upload TINYINT NOT NULL DEFAULT 1, can_download TINYINT NOT NULL DEFAULT 1, can_share TINYINT NOT NULL DEFAULT 1, can_delete TINYINT NOT NULL DEFAULT 0, can_create_folder TINYINT NOT NULL DEFAULT 1, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}

$pdo->exec("UPDATE users SET role='admin' WHERE email='admin@company.com'");
$pdo->exec("UPDATE users SET quota_gb=100 WHERE email='admin@company.com'");
echo "Fixed: admin@company.com is now admin with 100GB<br>";
$rows=$pdo->query("SELECT id,name,email,role,quota_gb FROM users")->fetchAll();
echo "<pre>"; print_r($rows); echo "</pre>";
echo "<a href='index.php'>Go to App &rarr;</a>";
