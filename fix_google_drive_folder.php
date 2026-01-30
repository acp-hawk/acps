<?php
/**
 * ACPS90 - Google Drive Folder ID Fixer
 * Creates a new parent folder in Google Drive and updates .env
 * Uses OAUTH2 authentication (personal account with storage quota)
 * 
 * Usage: php fix_google_drive_folder.php
 */

$base_dir = __DIR__;
$envPath = $base_dir . '/.env';

// Load .env to get OAuth credentials
$env_lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env_vars = [];
foreach ($env_lines as $line) {
    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
        list($key, $value) = explode('=', $line, 2);
        $env_vars[trim($key)] = trim($value, '"\'');
    }
}

$client_id = $env_vars['GC_CLIENT_ID'] ?? null;
$client_secret = $env_vars['GC_CLIENT_SECRET'] ?? null;
$refresh_token = $env_vars['GC_REFRESH_TOKEN'] ?? null;

if (!$client_id || !$client_secret || !$refresh_token) {
    die("❌ ERROR: Missing OAuth credentials in .env\n");
}

echo "🔄 Refreshing OAuth2 token...\n";

// Get new access token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'refresh_token',
    'refresh_token' => $refresh_token,
    'client_id' => $client_id,
    'client_secret' => $client_secret
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token_data = json_decode($response, true);

if ($http_code !== 200 || !isset($token_data['access_token'])) {
    echo "❌ ERROR: Token refresh failed (HTTP $http_code)\n";
    echo "Response: " . json_encode($token_data, JSON_PRETTY_PRINT) . "\n";
    die();
}

$access_token = $token_data['access_token'];
echo "✅ OAuth2 token obtained successfully\n";

// --- CALL GOOGLE API TO CREATE FOLDER ---
$folder_name = "ACPS_Photos";
echo "🔄 Creating Google Drive folder: $folder_name\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/drive/v3/files?supportsAllDrives=true');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json",
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => $folder_name,
    'mimeType' => 'application/vnd.google-apps.folder'
]));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code !== 200) {
    echo "❌ ERROR: Google Drive API failed (HTTP $http_code)\n";
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    die();
}

if (!isset($result['id'])) {
    echo "❌ ERROR: No folder ID returned\n";
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    die();
}

$new_folder_id = $result['id'];
echo "✅ Folder created successfully!\n";
echo "📁 Folder Name: " . $result['name'] . "\n";
echo "🆔 Folder ID: $new_folder_id\n";

// --- SET PERMISSIONS TO ANYONE WITH LINK ---
echo "🔄 Setting permissions to 'anyone with link'...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files/$new_folder_id/permissions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json",
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'role' => 'reader',
    'type' => 'anyone'
]));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo "⚠️ WARNING: Failed to set permissions (HTTP $http_code)\n";
} else {
    echo "✅ Permissions set to 'anyone with link'!\n";
}

echo "🔗 Link: https://drive.google.com/drive/folders/$new_folder_id\n";

// --- UPDATE .ENV FILE ---
echo "\n🔄 Updating .env file...\n";

$env_content = file_get_contents($envPath);

// Replace the old folder ID with the new one
$old_line = 'GOOGLE_DRIVE_FOLDER_ID="1mJvdKEWWH8RiWBHVNYHbEc5oqUNogUam"';
$new_line = "GOOGLE_DRIVE_FOLDER_ID=\"$new_folder_id\"";

if (strpos($env_content, $old_line) === false) {
    // If old line not found, search for any GOOGLE_DRIVE_FOLDER_ID line
    $env_content = preg_replace(
        '/GOOGLE_DRIVE_FOLDER_ID=".+?"/',
        "GOOGLE_DRIVE_FOLDER_ID=\"$new_folder_id\"",
        $env_content
    );
} else {
    $env_content = str_replace($old_line, $new_line, $env_content);
}

file_put_contents($envPath, $env_content);
echo "✅ .env file updated successfully!\n";

// --- VERIFY ---
$verify = parse_ini_file($envPath);
if (isset($verify['GOOGLE_DRIVE_FOLDER_ID'])) {
    $updated_id = trim($verify['GOOGLE_DRIVE_FOLDER_ID'], '"\'');
    if ($updated_id === $new_folder_id) {
        echo "✅ Verification successful! New folder ID is now active.\n";
        echo "\n🎉 All done! Google Drive uploads will now use the new folder.\n";
    } else {
        echo "⚠️  WARNING: Verification failed. Got: $updated_id, Expected: $new_folder_id\n";
    }
} else {
    echo "⚠️  WARNING: Could not verify .env update\n";
}

?>
