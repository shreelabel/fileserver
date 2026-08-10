<?php
require_once __DIR__ . '/StorageAdapter.php';

/**
 * FTP / FTPS Storage Adapter — Phase 1 stub, ready for Phase 2 activation
 * All FTP logic stays inside this class. UI talks only via StorageService.
 * Config: host, port, username, password, root, secure (true for FTPS), passive
 */
class FtpStorageAdapter implements StorageAdapter {
    private array $config;
    private $conn = null;

    public function __construct(array $config = []) {
        $this->config = array_merge([
            'host' => '',
            'port' => 21,
            'username' => '',
            'password' => '',
            'root' => '/',
            'secure' => false, // true = FTPS (ftp_ssl_connect)
            'passive' => true,
            'timeout' => 30,
        ], $config);
    }

    private function connect() {
        if ($this->conn) return $this->conn;
        $host = $this->config['host'];
        $port = intval($this->config['port'] ?? 21);
        if (!$host) throw new Exception('FTP host not configured - set in Settings → Storage → FTP');
        $func = $this->config['secure'] ? 'ftp_ssl_connect' : 'ftp_connect';
        if (!function_exists($func)) throw new Exception('PHP FTP extension not enabled (php_ftp.dll on XAMPP)');
        $this->conn = @$func($host, $port, $this->config['timeout']);
        if (!$this->conn) throw new Exception("FTP connect failed to $host:$port");
        if (!@ftp_login($this->conn, $this->config['username'], $this->config['password'])) {
            throw new Exception('FTP login failed - check username/password');
        }
        ftp_pasv($this->conn, (bool)$this->config['passive']);
        return $this->conn;
    }

    private function remotePath(string $path): string {
        $root = rtrim($this->config['root'], '/');
        return $root . '/' . ltrim($path, '/');
    }

    public function put(string $path, string $contents): string {
        $tmp = tempnam(sys_get_temp_dir(), 'ftp_');
        file_put_contents($tmp, $contents);
        $result = $this->putFile($path, $tmp);
        unlink($tmp);
        return $result;
    }

    private function putFile(string $path, string $localFile): string {
        $conn = $this->connect();
        $remote = $this->remotePath($path);
        $dir = dirname($remote);
        $this->ensureDir($dir);
        if (!ftp_put($conn, $remote, $localFile, FTP_BINARY)) throw new Exception("FTP put failed: $remote");
        return $path;
    }

    public function get(string $path): string {
        $conn = $this->connect();
        $tmp = tempnam(sys_get_temp_dir(), 'ftp_');
        if (!ftp_get($conn, $tmp, $this->remotePath($path), FTP_BINARY)) throw new Exception("FTP get failed: $path");
        $data = file_get_contents($tmp); unlink($tmp); return $data;
    }

    public function exists(string $path): bool {
        $conn = $this->connect();
        $dir = dirname($this->remotePath($path));
        $base = basename($this->remotePath($path));
        $list = @ftp_nlist($conn, $dir);
        return $list && in_array($base, array_map('basename', $list));
    }

    public function delete(string $path): bool { return ftp_delete($this->connect(), $this->remotePath($path)); }
    public function move(string $from, string $to): bool { return ftp_rename($this->connect(), $this->remotePath($from), $this->remotePath($to)); }
    public function copy(string $from, string $to): bool {
        // FTP has no native copy — download + upload
        $tmp = tempnam(sys_get_temp_dir(), 'ftp_');
        ftp_get($this->connect(), $tmp, $this->remotePath($from), FTP_BINARY);
        $result = ftp_put($this->connect(), $this->remotePath($to), $tmp, FTP_BINARY);
        unlink($tmp); return $result;
    }
    public function size(string $path): int { $s = ftp_size($this->connect(), $this->remotePath($path)); return $s === -1 ? 0 : $s; }
    public function stream(string $path) {
        // Return temp stream for download
        $tmp = tempnam(sys_get_temp_dir(), 'ftp_');
        ftp_get($this->connect(), $tmp, $this->remotePath($path), FTP_BINARY);
        return fopen($tmp, 'rb'); // caller will fpassthru
    }
    public function makeDirectory(string $path): bool { return $this->ensureDir($this->remotePath($path)); }
    private function ensureDir(string $remoteDir): bool {
        $conn = $this->connect();
        $parts = explode('/', trim($remoteDir, '/'));
        $cur = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $cur .= '/' . $part;
            @ftp_mkdir($conn, $cur);
        }
        return true;
    }
    public function deleteDirectory(string $path): bool {
        $conn = $this->connect();
        $remote = $this->remotePath($path);
        $list = @ftp_nlist($conn, $remote);
        if ($list) foreach ($list as $item) {
            $base = basename($item);
            if ($base === '.' || $base === '..') continue;
            @ftp_delete($conn, $item) || @ftp_rmdir($conn, $item);
        }
        return @ftp_rmdir($conn, $remote);
    }
    public function listContents(string $path): array {
        $list = @ftp_nlist($this->connect(), $this->remotePath($path));
        return $list ? array_map('basename', $list) : [];
    }
}
