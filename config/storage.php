<?php
// Storage config - Phase 1: local active, 2GB upload support
return [
    // driver: local | google_drive | hostinger | s3
    'driver' => getenv('STORAGE_DRIVER') ?: 'local',

    // Advanced: 2GB per file, chunked upload 5MB/chunk
    'max_upload_size' => 2 * 1024 * 1024 * 1024, // 2 GB
    'chunk_size' => 5 * 1024 * 1024, // 5 MB per chunk
    'allowed_extensions' => null, // null = allow all

    'drivers' => [
        'local' => [
            'root' => __DIR__ . '/../upload', // <-- Project er under e upload folder
            'base_url' => null,
            'label' => 'Local Storage (XAMPP) - /upload',
        ],
        'google_drive' => [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'http://localhost/file-server/api/oauth/google/callback.php',
            'folder_id' => '',
            'label' => 'Google Drive',
        ],
        'hostinger' => [
            'enabled' => false,
            'endpoint' => '',
            'api_key' => '',
            'secret' => '',
            'bucket' => '',
            'root' => '/storage',
            'label' => 'Hostinger / S3 Compatible',
        ],
        's3' => [
            'enabled' => false,
            'endpoint' => '',
            'key' => '',
            'secret' => '',
            'bucket' => '',
            'region' => 'ap-south-1',
            'label' => 'AWS S3 / Any S3',
        ],
        'ftp' => [
            'enabled' => false,
            'host' => '',
            'port' => 21,
            'username' => '',
            'password' => '',
            'root' => '/',
            'secure' => false,
            'passive' => true,
            'label' => 'FTP / FTPS Server',
        ],
    ],
];
