<?php
/**
 * ACPS90 Google Auth Setup Tool v9.0
 * Run this from CLI: php auth_setup.php
 * This script generates the permanent token.json for GMailer.
 */

$credentialsPath = __DIR__ . '/config/google/credentials.json';
$tokenPath = __DIR__ . '/config/google/token.json';

if (!file_exists($credentialsPath)) {
    die("Error: config/google/credentials.json not found. Please rename your client_secret file and move it there.\n");
}

$creds = json_decode(file_get_contents($credentialsPath), true);
$config = $creds['installed'] ?? null;

if (!$config) {
    die("Error: Invalid credentials.json format. Ensure you are using the 'Desktop/Installed' credential type.\n");
}

$client_id = $config['client_id'];
$client_secret = $config['client_secret'];
$redirect_uri = 'http://localhost'; // Standard for desktop apps

// Scopes required for Gmail sending and Drive file management
$scopes = [
    'https://www.googleapis.com/auth/gmail.send',
    'https://www.googleapis.com/auth/drive.file'
];

// Step 1: Generate Auth URL
$authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => implode(' ', $scopes),
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

echo "1. Visit this URL in your browser:\n\n" . $authUrl . "\n\n";
echo "2. Log in as hawksnest@alleycatphoto.com\n";
echo "3. After approving, you will land on a 'This site can’t be reached' localhost page.\n";
echo "4. Copy the 'code' parameter from the URL bar (e.g., localhost/?code=4/0Af...) and paste it here:\n";

echo "\nEnter code: ";
$code = trim(fgets(STDIN));

if (empty($code)) die("Error: No code provided.\n");

// Step 2: Exchange Code for Token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

if (isset($tokenData['error'])) {
    die("Error exchanging code: " . ($tokenData['error_description'] ?? $tokenData['error']) . "\n");
}

// Step 3: Save Token
$tokenData['created'] = time();
file_put_contents($tokenPath, json_encode($tokenData, JSON_PRETTY_PRINT));

echo "\nSUCCESS! Permanent token saved to: config/google/token.json\n";
echo "Your GMailer script is now ready for autonomous operation.\n";