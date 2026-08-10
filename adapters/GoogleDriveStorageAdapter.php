<?php
require_once __DIR__ . '/StorageAdapter.php';
require_once __DIR__ . '/../database/connection.php';

/**
 * Google Drive Storage Adapter using pure PHP cURL
 */
class GoogleDriveStorageAdapter implements StorageAdapter {
    private array $config;
    private $pdo;
    private $cache = [];

    public function __construct(array $config = []) {
        $this->config = $config;
        $this->pdo = getPDO();
        $this->checkToken();
    }

    private function checkToken() {
        if (empty($this->config['refresh_token'])) {
            throw new Exception("Google Drive not connected. Please connect via Settings.");
        }
        $expiry = $this->config['token_expiry'] ?? 0;
        if (time() >= ($expiry - 300) || empty($this->config['access_token'])) {
            $this->refreshToken();
        }
    }

    private function refreshToken() {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $this->config['refresh_token'],
            'grant_type' => 'refresh_token'
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Google Drive token refresh failed.");
        }

        $data = json_decode($response, true);
        $this->config['access_token'] = $data['access_token'];
        $this->config['token_expiry'] = time() + ($data['expires_in'] ?? 3599);

        // Update DB
        $stmt = $this->pdo->prepare("SELECT config_json FROM storage_configs WHERE provider = 'google_drive'");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            $dbConfig = json_decode($row['config_json'], true) ?: [];
            $dbConfig['access_token'] = $this->config['access_token'];
            $dbConfig['token_expiry'] = $this->config['token_expiry'];
            $this->pdo->prepare("UPDATE storage_configs SET config_json = ? WHERE provider = 'google_drive'")
                 ->execute([json_encode($dbConfig)]);
        }
    }

    private function apiRequest($method, $url, $headers = [], $body = null) {
        $ch = curl_init($url);
        $allHeaders = array_merge([
            'Authorization: Bearer ' . $this->config['access_token']
        ], $headers);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 && $httpCode !== 404) {
            throw new Exception("Google Drive API Error ($httpCode): " . $response);
        }
        return ['code' => $httpCode, 'body' => $response];
    }

    private function resolveDirectoryId(string $path): string {
        $path = trim($path, '/');
        if (empty($path)) {
            return !empty($this->config['folder_id']) ? $this->config['folder_id'] : 'root';
        }

        if (isset($this->cache[$path])) return $this->cache[$path];

        $parts = explode('/', $path);
        $currentParent = !empty($this->config['folder_id']) ? $this->config['folder_id'] : 'root';
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= ($currentPath ? '/' : '') . $part;
            $q = sprintf("name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                str_replace("'", "\\'", $part), $currentParent);
            
            $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($q) . '&fields=files(id)';
            $res = $this->apiRequest('GET', $url);
            $data = json_decode($res['body'], true);
            
            if (!empty($data['files'])) {
                $currentParent = $data['files'][0]['id'];
            } else {
                $body = json_encode([
                    'name' => $part,
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'parents' => [$currentParent]
                ]);
                $cRes = $this->apiRequest('POST', 'https://www.googleapis.com/drive/v3/files', ['Content-Type: application/json'], $body);
                $cData = json_decode($cRes['body'], true);
                if (empty($cData['id'])) throw new Exception("Failed to create Google Drive folder: $part");
                $currentParent = $cData['id'];
            }
            $this->cache[$currentPath] = $currentParent;
        }
        return $currentParent;
    }

    private function getFileId(string $path): ?string {
        $path = trim($path, '/');
        if ($path === '') return null;
        $dir = dirname($path);
        $name = basename($path);
        if ($dir === '.') $dir = '';

        try {
            $parentId = $this->resolveDirectoryId($dir);
        } catch (Exception $e) {
            return null;
        }

        $q = sprintf("name = '%s' and '%s' in parents and mimeType != 'application/vnd.google-apps.folder' and trashed = false",
            str_replace("'", "\\'", $name), $parentId);
        $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($q) . '&fields=files(id,size)';
        $res = $this->apiRequest('GET', $url);
        $data = json_decode($res['body'], true);

        if (!empty($data['files'])) {
            return $data['files'][0]['id'];
        }
        return null;
    }

    public function put(string $path, string $contents): string {
        $dir = dirname($path);
        if ($dir === '.') $dir = '';
        $name = basename($path);
        
        $parentId = $this->resolveDirectoryId($dir);
        $boundary = 'foo_bar_baz_' . md5(uniqid());
        $mimeType = 'application/octet-stream';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($contents) ?: 'application/octet-stream';
        }

        $metadata = [
            'name' => $name,
            'parents' => [$parentId]
        ];

        $postBody = "--$boundary\r\n"
                  . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
                  . json_encode($metadata) . "\r\n"
                  . "--$boundary\r\n"
                  . "Content-Type: $mimeType\r\n\r\n"
                  . $contents . "\r\n"
                  . "--$boundary--";

        $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';
        $res = $this->apiRequest('POST', $url, [
            "Content-Type: multipart/related; boundary=$boundary"
        ], $postBody);

        $data = json_decode($res['body'], true);
        if (empty($data['id'])) throw new Exception("Google Drive upload failed.");
        return $path;
    }

    public function get(string $path): string {
        $id = $this->getFileId($path);
        if (!$id) throw new Exception("File not found on Google Drive: $path");
        $url = "https://www.googleapis.com/drive/v3/files/$id?alt=media";
        $res = $this->apiRequest('GET', $url);
        if ($res['code'] !== 200) throw new Exception("Failed to download from Google Drive.");
        return $res['body'];
    }

    public function exists(string $path): bool {
        return $this->getFileId($path) !== null;
    }

    public function delete(string $path): bool {
        $id = $this->getFileId($path);
        if (!$id) return false;
        $url = "https://www.googleapis.com/drive/v3/files/$id";
        $res = $this->apiRequest('DELETE', $url);
        return $res['code'] === 204 || $res['code'] === 200;
    }

    public function move(string $from, string $to): bool {
        // Requires updating 'parents' array and 'name'. Complex to do robustly here, but we can do simple rename if in same folder.
        // Or download + upload. Let's do download + upload for simplicity.
        $this->put($to, $this->get($from));
        $this->delete($from);
        return true;
    }

    public function copy(string $from, string $to): bool {
        $this->put($to, $this->get($from));
        return true;
    }

    public function size(string $path): int {
        $id = $this->getFileId($path);
        if (!$id) return 0;
        $url = "https://www.googleapis.com/drive/v3/files/$id?fields=size";
        $res = $this->apiRequest('GET', $url);
        $data = json_decode($res['body'], true);
        return intval($data['size'] ?? 0);
    }

    public function stream(string $path) {
        $contents = $this->get($path);
        $tmp = tmpfile();
        fwrite($tmp, $contents);
        fseek($tmp, 0);
        return $tmp;
    }

    public function makeDirectory(string $path): bool {
        $this->resolveDirectoryId($path);
        return true;
    }

    public function deleteDirectory(string $path): bool {
        try {
            $id = $this->resolveDirectoryId($path);
            if ($id && $id !== 'root' && $id !== ($this->config['folder_id'] ?? '')) {
                $url = "https://www.googleapis.com/drive/v3/files/$id";
                $this->apiRequest('DELETE', $url);
                return true;
            }
        } catch (Exception $e) {}
        return false;
    }

    public function listContents(string $path): array {
        $id = $this->resolveDirectoryId($path);
        $q = sprintf("'%s' in parents and trashed = false", $id);
        $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($q) . '&fields=files(name)';
        $res = $this->apiRequest('GET', $url);
        $data = json_decode($res['body'], true);
        
        $list = [];
        if (!empty($data['files'])) {
            foreach ($data['files'] as $f) {
                $list[] = $f['name'];
            }
        }
        return $list;
    }
}
