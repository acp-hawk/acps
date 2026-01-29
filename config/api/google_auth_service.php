<?php
// Service Account Authentication Helper
// Handles JWT generation and token exchange for Google APIs

function get_service_account_token() {
    $keyFile = __DIR__ . '/../../.gemini/alleycat-photo-6fc37f1db550.json';
    
    // Cache token to avoid frequent re-auth (if running in long process, though gmailer is usually one-shot)
    // For simple CLI script, we can just generating it.
    
    if (!file_exists($keyFile)) {
        throw new Exception("Service Account key file not found: $keyFile");
    }

    $keyData = json_decode(file_get_contents($keyFile), true);
    if (!$keyData) {
        throw new Exception("Invalid JSON in Service Account key file.");
    }

    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $payload = json_encode([
        'iss' => $keyData['client_email'],
        'sub' => $keyData['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/gmail.send'
    ]);

    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signatureInput = $base64Header . "." . $base64Payload;

    $privateKey = $keyData['private_key'];
    $signature = '';
    if (!openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
        throw new Exception("OpenSSL signing failed.");
    }
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $signatureInput . "." . $base64Signature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Token exchange failed ($httpCode): $response");
    }

    $tokenData = json_decode($response, true);
    return $tokenData['access_token'];
}
