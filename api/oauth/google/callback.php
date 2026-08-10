<?php
session_start();
require __DIR__ . '/../../../database/connection.php';

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    die("Google OAuth Error: " . htmlspecialchars($error));
}
if (!$code) {
    die("No code provided.");
}

// CSRF validation
$sessionState = $_SESSION['oauth_state'] ?? '';
if (!$state || $state !== $sessionState) {
    die("Invalid OAuth state. CSRF protection triggered.");
}
unset($_SESSION['oauth_state']);

$pdo = getPDO();

// Fetch current config
$cfg = require __DIR__ . '/../../../config/storage.php';
$gdConfig = $cfg['drivers']['google_drive'];

$stmt = $pdo->prepare("SELECT config_json FROM storage_configs WHERE provider = 'google_drive'");
$stmt->execute();
$row = $stmt->fetch();
if ($row) {
    $dbConfig = json_decode($row['config_json'], true) ?: [];
    $gdConfig = array_merge($gdConfig, $dbConfig);
}

$clientId = $gdConfig['client_id'] ?? '';
$clientSecret = $gdConfig['client_secret'] ?? '';
$redirectUri = $gdConfig['redirect_uri'] ?? '';

if (!$clientId || !$clientSecret) {
    die("Google Drive Client ID and Secret are not configured. Please save them in Settings first.");
}

// Exchange code for token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = [
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenData = json_decode($response, true);

if ($httpCode !== 200 || isset($tokenData['error'])) {
    die("Error fetching token: " . htmlspecialchars($tokenData['error_description'] ?? $tokenData['error'] ?? $response));
}

// We successfully got tokens. Save them.
$accessToken = $tokenData['access_token'] ?? '';
$refreshToken = $tokenData['refresh_token'] ?? $gdConfig['refresh_token'] ?? ''; // keep old if no new one
$expiresIn = $tokenData['expires_in'] ?? 3599;

$gdConfig['access_token'] = $accessToken;
$gdConfig['refresh_token'] = $refreshToken;
$gdConfig['token_expiry'] = time() + $expiresIn;

// Update DB
$pdo->prepare("INSERT INTO storage_configs (provider, config_json, is_active) VALUES ('google_drive', ?, 0) 
               ON DUPLICATE KEY UPDATE config_json = VALUES(config_json)")
    ->execute([json_encode($gdConfig)]);

// Redirect back to app
header("Location: ../../../index.php?toast=" . urlencode('Google Drive Connected Successfully!'));
exit;
