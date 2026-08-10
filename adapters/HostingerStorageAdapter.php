<?php
require_once __DIR__ . '/StorageAdapter.php';

/**
 * Hostinger / S3-Compatible Storage Adapter using pure PHP (AWS Signature V4)
 */
class HostingerStorageAdapter implements StorageAdapter {
    private array $config;

    public function __construct(array $config = []) {
        $this->config = $config;
        if (empty($this->config['region'])) {
            $this->config['region'] = 'us-east-1'; // default AWS/S3 region
        }
    }

    private function getEndpoint(string $path = '', string $query = ''): string {
        $endpoint = rtrim($this->config['endpoint'] ?? '', '/');
        $bucket = $this->config['bucket'] ?? '';
        $path = ltrim($path, '/');
        
        $url = $endpoint . '/' . $bucket . '/' . $path;
        if ($query) {
            $url .= '?' . $query;
        }
        return $url;
    }

    private function signRequest($method, $url, $headers, $payload = '') {
        $accessKey = $this->config['api_key'] ?? '';
        $secretKey = $this->config['secret'] ?? '';
        $region = $this->config['region'] ?? 'us-east-1';
        $service = 's3';

        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        $path = $parsedUrl['path'] ?? '/';
        $query = $parsedUrl['query'] ?? '';

        // AWS requires sorted query parameters
        if ($query) {
            parse_str($query, $params);
            ksort($params);
            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        $headers['x-amz-date'] = $amzDate;
        $headers['Host'] = $host;
        
        $payloadHash = hash('sha256', $payload);
        $headers['x-amz-content-sha256'] = $payloadHash;

        $lowerHeaders = [];
        foreach ($headers as $k => $v) {
            $lowerHeaders[strtolower($k)] = trim($v);
        }
        ksort($lowerHeaders);
        
        $canonicalHeaders = "";
        $signedHeaders = "";
        foreach ($lowerHeaders as $k => $v) {
            $canonicalHeaders .= $k . ":" . $v . "\n";
            $signedHeaders .= $k . ";";
        }
        $signedHeaders = rtrim($signedHeaders, ";");

        // Canonical Request
        $canonicalRequest = "$method\n$path\n$query\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        // String to Sign
        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "$dateStamp/$region/$service/aws4_request";
        $stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        // Calculate Signature
        $kSecret = "AWS4" . $secretKey;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', "aws4_request", $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        $authorizationHeader = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";
        $headers['Authorization'] = $authorizationHeader;

        $flatHeaders = [];
        foreach ($headers as $k => $v) {
            $flatHeaders[] = "$k: $v";
        }
        return $flatHeaders;
    }

    private function apiRequest($method, $path, $payload = '', $extraHeaders = [], $query = '') {
        if (empty($this->config['api_key'])) {
            throw new Exception("Hostinger/S3 not configured properly.");
        }
        
        $root = trim($this->config['root'] ?? '', '/');
        // If it's a bucket operation (empty path), don't append root
        if ($path === '') {
            $remotePath = '';
        } else {
            $remotePath = $root ? ($root . '/' . ltrim($path, '/')) : ltrim($path, '/');
        }
        
        $url = $this->getEndpoint($remotePath, $query);
        $headers = $this->signRequest($method, $url, $extraHeaders, $payload);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($payload !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $httpCode, 'body' => $response];
    }

    public function put(string $path, string $contents): string {
        $mime = 'application/octet-stream';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($contents) ?: 'application/octet-stream';
        }
        
        $res = $this->apiRequest('PUT', $path, $contents, ['Content-Type' => $mime]);
        if ($res['code'] !== 200 && $res['code'] !== 201) {
            throw new Exception("S3 Upload Failed: " . $res['body']);
        }
        return $path;
    }

    public function get(string $path): string {
        $res = $this->apiRequest('GET', $path);
        if ($res['code'] !== 200) {
            throw new Exception("S3 Download Failed");
        }
        return $res['body'];
    }

    public function exists(string $path): bool {
        $res = $this->apiRequest('HEAD', $path);
        return $res['code'] === 200;
    }

    public function delete(string $path): bool {
        $res = $this->apiRequest('DELETE', $path);
        return $res['code'] === 204 || $res['code'] === 200;
    }

    public function move(string $from, string $to): bool {
        $this->copy($from, $to);
        $this->delete($from);
        return true;
    }

    public function copy(string $from, string $to): bool {
        $root = trim($this->config['root'] ?? '', '/');
        $remoteFrom = $root ? ($root . '/' . ltrim($from, '/')) : ltrim($from, '/');
        $bucket = $this->config['bucket'] ?? '';
        $sourcePath = '/' . $bucket . '/' . $remoteFrom;
        
        $res = $this->apiRequest('PUT', $to, '', ['x-amz-copy-source' => $sourcePath]);
        if ($res['code'] !== 200 && $res['code'] !== 201) {
            throw new Exception("S3 Copy Failed: " . $res['body']);
        }
        return true;
    }

    public function size(string $path): int {
        $root = trim($this->config['root'] ?? '', '/');
        $remotePath = $root ? ($root . '/' . ltrim($path, '/')) : ltrim($path, '/');
        $url = $this->getEndpoint($remotePath);
        $headers = $this->signRequest('HEAD', $url, [], '');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && preg_match('/Content-Length: (\d+)/i', $response, $matches)) {
            return intval($matches[1]);
        }
        return 0;
    }

    public function stream(string $path) {
        $contents = $this->get($path);
        $tmp = tmpfile();
        fwrite($tmp, $contents);
        fseek($tmp, 0);
        return $tmp;
    }

    public function makeDirectory(string $path): bool {
        // S3 uses virtual directories. Creating a zero-byte object with trailing slash.
        $this->put(rtrim($path, '/') . '/', '');
        return true;
    }

    public function deleteDirectory(string $path): bool {
        // We have to list objects with prefix and delete them.
        $root = trim($this->config['root'] ?? '', '/');
        $remotePath = $root ? ($root . '/' . ltrim($path, '/')) : ltrim($path, '/');
        $prefix = rtrim($remotePath, '/') . '/';
        
        $res = $this->apiRequest('GET', '', '', [], 'prefix=' . urlencode($prefix));
        if ($res['code'] === 200) {
            // Parse XML response to find keys
            if (preg_match_all('/<Key>(.*?)<\/Key>/s', $res['body'], $matches)) {
                foreach ($matches[1] as $key) {
                    // key includes root, so we strip root to get logical path for delete()
                    $logicalPath = $key;
                    if ($root !== '' && strpos($key, $root . '/') === 0) {
                        $logicalPath = substr($key, strlen($root) + 1);
                    }
                    $this->delete($logicalPath);
                }
            }
        }
        return true;
    }

    public function listContents(string $path): array {
        $root = trim($this->config['root'] ?? '', '/');
        $remotePath = $root ? ($root . '/' . ltrim($path, '/')) : ltrim($path, '/');
        $prefix = rtrim($remotePath, '/') . '/';
        if ($prefix === '/') $prefix = '';
        
        $res = $this->apiRequest('GET', '', '', [], 'prefix=' . urlencode($prefix));
        $list = [];
        if ($res['code'] === 200 && preg_match_all('/<Key>(.*?)<\/Key>/s', $res['body'], $matches)) {
            foreach ($matches[1] as $key) {
                $list[] = basename($key);
            }
        }
        return $list;
    }
}
