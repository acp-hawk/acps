<?php
// Debug script to access Google Drive folder
// Target Folder: 1mJvdKEWWH8RiWBHVNYHbEc5oqUNogUam

$tokenPath = __DIR__ . '/config/google/token.json';
if (!file_exists($tokenPath)) {
    die("Error: Token file not found at $tokenPath\n");
}

$tokenData = json_decode(file_get_contents($tokenPath), true);
$accessToken = $tokenData['access_token'] ?? null;

if (!$accessToken) {
    die("Error: No access token in file.\n");
}

function google_api_call($url, $method, $token, $payload = null) {
    $ch = curl_init($url);
    $headers = ["Authorization: Bearer $token", "Content-Type: application/json"];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($payload) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($payload) ? json_encode($payload) : $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['code' => 0, 'error' => $err];
    }
    return ['code' => $code, 'body' => json_decode($resp, true)];
}

$folderId = '1mJvdKEWWH8RiWBHVNYHbEc5oqUNogUam';
echo "Accessing Google Drive Folder: $folderId\n";

// 0. List ROOT files to verify token and scope visibility
echo "Checking ROOT visibility:\n";
$rootListUrl = "https://www.googleapis.com/drive/v3/files?q=" . urlencode("'root' in parents and trashed=false") . "&fields=files(id,name,mimeType)";
$rootResp = google_api_call($rootListUrl, 'GET', $accessToken);
if ($rootResp['code'] === 200) {
    $files = $rootResp['body']['files'] ?? [];
    echo "Root contains " . count($files) . " items visible to this app.\n";
    foreach ($files as $f) echo " - {$f['name']} ({$f['id']})\n";
} else {
    echo "Root check failed: {$rootResp['code']}\n";
}
echo "----------------------------------------\n";

// 1. Get folder metadata to verify access
$metaUrl = "https://www.googleapis.com/drive/v3/files/$folderId?fields=id,name,mimeType,parents&supportsAllDrives=true";
$metaResp = google_api_call($metaUrl, 'GET', $accessToken);

if ($metaResp['code'] === 200) {
    $name = $metaResp['body']['name'];
    echo "SUCCESS: Found folder '$name'\n";
    echo "----------------------------------------\n";
    
    // 2. List contents
    $query = "'$folderId' in parents and trashed = false";
    $listUrl = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($query) . "&fields=files(id,name,mimeType,size)&supportsAllDrives=true&includeItemsFromAllDrives=true";
    $listResp = google_api_call($listUrl, 'GET', $accessToken);
    
    if ($listResp['code'] === 200) {
        $files = $listResp['body']['files'] ?? [];
        echo "Found " . count($files) . " items:\n";
        foreach ($files as $file) {
            echo " - [{$file['mimeType']}] {$file['name']} ({$file['id']})\n";
        }
    } else {
        echo "Error listing files: Code {$listResp['code']}\n";
        print_r($listResp['body']);
    }
} else {
    echo "FAILED to access folder. Code: {$metaResp['code']}\n";
    print_r($metaResp['body']);
    if (isset($metaResp['error'])) echo "Curl Error: " . $metaResp['error'] . "\n";
}
