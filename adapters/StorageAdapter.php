<?php
interface StorageAdapter {
    public function put(string $path, string $contents): string; // returns storage_file_id / path
    public function get(string $path): string; // returns file contents
    public function exists(string $path): bool;
    public function delete(string $path): bool;
    public function move(string $from, string $to): bool;
    public function copy(string $from, string $to): bool;
    public function size(string $path): int;
    public function stream(string $path); // resource / stream for download
    public function makeDirectory(string $path): bool;
    public function deleteDirectory(string $path): bool;
    public function listContents(string $path): array;
}
