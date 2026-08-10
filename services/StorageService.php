<?php
require_once __DIR__ . '/../adapters/LocalStorageAdapter.php';
require_once __DIR__ . '/../adapters/GoogleDriveStorageAdapter.php';
require_once __DIR__ . '/../adapters/HostingerStorageAdapter.php';
require_once __DIR__ . '/../adapters/FtpStorageAdapter.php';

class StorageService {
    private StorageAdapter $adapter;
    private string $driver;

    public function __construct() {
        $cfg = require __DIR__ . '/../config/storage.php';
        
        try {
            require_once __DIR__ . '/../database/connection.php';
            $pdo = getPDO();
            $rows = $pdo->query("SELECT provider,is_active,config_json FROM storage_configs")->fetchAll();
            foreach($rows as $r) {
                if(isset($cfg['drivers'][$r['provider']])) {
                    $j = json_decode($r['config_json'], true) ?: [];
                    $cfg['drivers'][$r['provider']] = array_merge($cfg['drivers'][$r['provider']], $j);
                    $cfg['drivers'][$r['provider']]['is_active'] = $r['is_active'];
                    if ($r['is_active']) $cfg['driver'] = $r['provider'];
                }
            }
        } catch(Exception $e) {}

        $this->driver = $cfg['driver'];
        switch ($this->driver) {
            case 'google_drive':
                $this->adapter = new GoogleDriveStorageAdapter($cfg['drivers']['google_drive']);
                break;
            case 'hostinger':
                $this->adapter = new HostingerStorageAdapter($cfg['drivers']['hostinger']);
                break;
            case 'ftp':
                $this->adapter = new FtpStorageAdapter($cfg['drivers']['ftp']);
                break;
            case 'local':
            default:
                $this->adapter = new LocalStorageAdapter($cfg['drivers']['local']['root']);
                break;
        }
    }

    public function adapter(): StorageAdapter { return $this->adapter; }
    public function driver(): string { return $this->driver; }

    // High-level helpers - frontend never touches adapter directly
    public function storeUploadedFile(array $file, string $destPath): string {
        if ($this->adapter instanceof LocalStorageAdapter) {
            $tmp = $file['tmp_name'];
            $stream = fopen($tmp, 'rb');
            $result = $this->adapter->putStream($destPath, $stream);
            fclose($stream);
            return $result;
        }
        return $this->adapter->put($destPath, file_get_contents($file['tmp_name']));
    }

    public function download(string $storagePath) {
        return $this->adapter->stream($storagePath);
    }
}
