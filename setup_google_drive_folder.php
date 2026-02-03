<?php
/**
 * Setup Google Drive folder for ACPS gmailer
 * Run: php setup_google_drive_folder.php
 */

require_once __DIR__ . '/admin/config.php';

// Load token from token.json
$tokenPath = __DIR__ . '/config/google/token.json';
if (!file_exists($tokenPath)) {
    die("Error: config/google/token.json not found. Run auth_setup.php first.\n");
}

$tokenData = json_decode(file_get_contents($tokenPath), true);
$access_token = $tokenData['access_token'] ?? null;

if (!$access_token) {
    die("Error: No access_token in token.json\n");
}

echo "Using OAuth2 token for alleycat-photo project...\n\n";

// Check if folder already exists (look for "ACPS Photos" or "AlleyCAT Photos")
echo "Searching for existing ACPS photos folder in Google Drive...\n";

$search_query = urlencode("name='ACPS Photos' and trashed=false");
$search_url = "https://www.googleapis.com/drive/v3/files?q=" . $search_query;

$ch = curl_init($search_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    die("Curl error: $curl_error\n");
}

if ($code !== 200) {
    die("Error searching Google Drive: HTTP $code\nResponse: $response\n");
}

$result = json_decode($response, true);
$files = $result['files'] ?? [];

if (!empty($files)) {
    $folder = $files[0];
    echo "✅ Found existing folder: {$folder['name']} (ID: {$folder['id']})\n";
    $folder_id = $folder['id'];
} else {
    // Create new folder
    echo "Creating new 'ACPS Photos' folder in Google Drive...\n";
    
    $ch = curl_init("https://www.googleapis.com/drive/v3/files");
    $metadata = json_encode([
        'name' => 'ACPS Photos',
        'mimeType' => 'application/vnd.google-apps.folder'
    ]);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $metadata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code !== 200) {
        die("Error creating folder: HTTP $code\nResponse: $response\n");
    }
    
    $result = json_decode($response, true);
    $folder_id = $result['id'];
    echo "✅ Created new folder with ID: $folder_id\n";
}

// Set permissions to "anyone with link"
echo "Setting permissions to 'anyone with link' for folder: $folder_id\n";
$ch = curl_init("https://www.googleapis.com/drive/v3/files/$folder_id/permissions");
$perm_data = json_encode([
    'role' => 'reader',
    'type' => 'anyone'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $perm_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
]);
$perm_response = curl_exec($ch);
$perm_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($perm_code !== 200) {
    echo "⚠️ Warning: Failed to set permissions (HTTP $perm_code). Response: $perm_response\n";
} else {
    echo "✅ Permissions set to 'anyone with link' successfully!\n";
}

// Update .env file
$env_file = __DIR__ . '/.env';
$env_content = file_get_contents($env_file);

// Replace GOOGLE_DRIVE_FOLDER_ID
$env_content = preg_replace(
    '/GOOGLE_DRIVE_FOLDER_ID="[^"]*"/',
    'GOOGLE_DRIVE_FOLDER_ID="' . $folder_id . '"',
    $env_content
);

file_put_contents($env_file, $env_content);

echo "\n✅ Updated .env with GOOGLE_DRIVE_FOLDER_ID=\"$folder_id\"\n";
echo "Google Drive folder ready for email uploads!\n";
?>
