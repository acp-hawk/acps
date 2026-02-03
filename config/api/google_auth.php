<?php
/**
 * ACPS Google Auth Web Handler
 * Allows re-authentication directly from the dashboard.
 */

session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
try { $dotenv = Dotenv\Dotenv::createImmutable(realpath(__DIR__ . '/../../')); $dotenv->safeLoad(); } catch (Exception $e) {}
$credentialsPath = "../../config/google/credentials.json";
$tokenPath = "../../config/google/token.json";

$action = $_GET['action'] ?? '';

if (!file_exists($credentialsPath)) {
    die("Error: credentials.json not found.");
}

$creds = json_decode(file_get_contents($credentialsPath), true);
$config = $creds['installed'] ?? null;
if (!$config) die("Error: Invalid credentials format.");

$client_id = $config['client_id'];
$client_secret = $config['client_secret'];
$redirect_uri = 'http://localhost/config/api/google_auth.php?action=callback'; // Must match Console

// Scopes
$scopes = [
    'https://www.googleapis.com/auth/gmail.send',
    'https://www.googleapis.com/auth/drive.file',
    'https://www.googleapis.com/auth/userinfo.email'
];

if ($action === 'start') {
    // 1. Redirect to Google
    $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => implode(' ', $scopes),
        'access_type' => 'offline',
        'prompt' => 'consent' // Force refresh token
    ]);
    header("Location: $authUrl");
    exit;
}

if ($action === 'callback') {
    $code = $_GET['code'] ?? '';
    if (empty($code)) die("Error: No code returned.");

    // 2. Exchange Code
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CAINFO, dirname(__DIR__) . '/cacert.pem');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $code,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ]));

    $response = curl_exec($ch);
    $tokenData = json_decode($response, true);
    curl_close($ch);

    if (isset($tokenData['error'])) {
        die("Error exchanging code: " . ($tokenData['error_description'] ?? $tokenData['error']));
    }

    // 3. Save Token
    $tokenData['created'] = time();
    file_put_contents($tokenPath, json_encode($tokenData, JSON_PRETTY_PRINT));

    // 4. Redirect back to Dashboard
    header("Location: ../index.php?auth=success");
    exit;
}


