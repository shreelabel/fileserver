<?php
require_once __DIR__ . '/StorageAdapter.php';

class LocalStorageAdapter implements StorageAdapter {
    private string $root;

    public function __construct(string $root) {
        $this->root = rtrim($root, '/');
        if (!is_dir($this->root)) {
            mkdir($this->root, 0775, true);
        }
    }

    private function full(string $path): string {
        $path = ltrim($path, '/');
        // prevent path traversal
        $path = str_replace(['..', "\0"], '', $path);
        return $this->root . '/' . $path;
    }

    public function put(string $path, string $contents): string {
        $full = $this->full($path);
        $dir = dirname($full);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        file_put_contents($full, $contents);
        return $path;
    }

    public function putStream(string $path, $sourceStream): string {
        $full = $this->full($path);
        $dir = dirname($full);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $dest = fopen($full, 'wb');
        stream_copy_to_stream($sourceStream, $dest);
        fclose($dest);
        return $path;
    }

    public function get(string $path): string {
        return file_get_contents($this->full($path));
    }

    public function exists(string $path): bool {
        return file_exists($this->full($path));
    }

    public function delete(string $path): bool {
        $full = $this->full($path);
        if (is_file($full)) return unlink($full);
        return false;
    }

    public function move(string $from, string $to): bool {
        $a = $this->full($from); $b = $this->full($to);
        $dir = dirname($b);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        return rename($a, $b);
    }

    public function copy(string $from, string $to): bool {
        $a = $this->full($from); $b = $this->full($to);
        $dir = dirname($b);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        return copy($a, $b);
    }

    public function size(string $path): int {
        return filesize($this->full($path));
    }

    public function stream(string $path) {
        return fopen($this->full($path), 'rb');
    }

    public function makeDirectory(string $path): bool {
        $full = $this->full($path);
        if (is_dir($full)) return true;
        return mkdir($full, 0775, true);
    }

    public function deleteDirectory(string $path): bool {
        $full = $this->full($path);
        if (!is_dir($full)) return false;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        return rmdir($full);
    }

    public function listContents(string $path): array {
        $full = $this->full($path);
        if (!is_dir($full)) return [];
        return array_values(array_diff(scandir($full), ['.', '..']));
    }

    public function absolutePath(string $path): string {
        return $this->full($path);
    }
}
