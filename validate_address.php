<?php
//*********************************************************************//
// AlleyCat PhotoStation : AJAX Address Validation API
// Handles USPS address validation requested by checkout_mailing.php JS.
//
// FIXED: Validation logic now strictly requires matches.code='31' (Exact Match)
//        and additionalInfo.DPVConfirmation='Y' (Confirmed Deliverable)
//*********************************************************************//

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

header('Content-Type: application/json');

// Load Composer autoloader and .env
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    if (class_exists('Dotenv\\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->safeLoad();
        } catch (Exception $e) {
            error_log('Failed to load .env: ' . $e->getMessage());
        }
    }
}

// Load USPS credentials from environment
define('USPS_CLIENT_ID', $_ENV['USPS_CLIENT_ID'] ?? getenv('USPS_CLIENT_ID') ?: '');
define('USPS_CLIENT_SECRET', $_ENV['USPS_CLIENT_SECRET'] ?? getenv('USPS_CLIENT_SECRET') ?: '');
define('USPS_TOKEN_URL', 'https://apis.usps.com/oauth2/v3/token');
define('USPS_VALIDATE_URL', 'https://apis.usps.com/addresses/v3/address');

// Simple file-based token cache (needs proper security/write access)
define('USPS_TOKEN_CACHE', __DIR__ . '/usps_token_cache.txt');


// --- USPS Helper Functions ---

/**
 * Fetches or returns a cached USPS OAuth token.
 */
function getUSPSToken() {
    // Check cache
    if (file_exists(USPS_TOKEN_CACHE)) {
        $data = json_decode(file_get_contents(USPS_TOKEN_CACHE), true);
        // Check if token is still valid (60 seconds buffer)
        if ($data && isset($data['token']) && $data['expiresAt'] > time() + 60) {
            return $data['token'];
        }
    }

    // Fetch new token
    $payload = json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => USPS_CLIENT_ID,
        'client_secret' => USPS_CLIENT_SECRET
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents(USPS_TOKEN_URL, false, $context);
    
    if ($response === false) {
        error_log("USPS Token Fetch Error: Request failed.");
        return false;
    }
    
    $result = json_decode($response, true);

    if (isset($result['access_token'])) {
        $token = $result['access_token'];
        $expiresIn = $result['expires_in'];
        
        // Cache the new token
        $cacheData = [
            'token' => $token,
            'expiresAt' => time() + $expiresIn - 60 // Cache with 60s buffer
        ];
        @file_put_contents(USPS_TOKEN_CACHE, json_encode($cacheData), LOCK_EX);
        
        return $token;
    }

    error_log("USPS Token Fetch Error: " . print_r($result, true));
    return false;
}

/**
 * Validates the provided address against the USPS API, implementing strict checks.
 */
function validateUSPSAddress($street, $city, $state, $zip) {
    $token = getUSPSToken();
    if ($token === false) {
        return ['status' => 'error', 'message' => 'Failed to connect to USPS API (Authentication).'];
    }

    $params = http_build_query([
        'streetAddress' => $street,
        'city' => $city,
        'state' => $state,
        'ZIPCode' => $zip
    ]);

    $url = USPS_VALIDATE_URL . '?' . $params;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
                        "Authorization: Bearer {$token}\r\n",
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['status' => 'error', 'message' => 'Failed to connect to USPS API (Validation Request).'];
    }

    $result = json_decode($response, true);
    
    // --- 1. Check for Top-Level API Errors ---
    if (isset($result['error'])) {
        // Parse the USPS error and return friendly message
        $errorCode = $result['error']['code'] ?? '';
        $errorMessage = $result['error']['message'] ?? '';
        
        // Map common USPS errors to friendly messages
        if (strpos($errorMessage, 'String filll is too long') !== false || 
            strpos($errorMessage, 'maximum allowed') !== false) {
            return [
                'status' => 'error', 
                'message' => 'Street address is too long. Please use abbreviations (St, Ave, Blvd, Apt, etc.)'
            ];
        }
        
        if (strpos($errorMessage, 'does not match') !== false || 
            strpos($errorMessage, 'regex') !== false) {
            return [
                'status' => 'error', 
                'message' => 'Address contains invalid characters. Please use letters, numbers, spaces, and common punctuation only.'
            ];
        }
        
        if (strpos($errorMessage, 'ECMA 262') !== false) {
            return [
                'status' => 'error', 
                'message' => 'Address format is invalid. Please check for special characters or unusual formatting.'
            ];
        }
        
        // Generic fallback
        return [
            'status' => 'error', 
            'message' => 'Unable to validate address. Please check the format and try again.'
        ];
    }
    
    // --- 2. Check for Exact Match (code 31 required by TypeScript logic) ---
    $isExactMatch = false;
    if (isset($result['matches']) && is_array($result['matches'])) {
        foreach ($result['matches'] as $match) {
            // Check for the strict code 31 (Exact Match)
            if (isset($match['code']) && $match['code'] === '31') {
                $isExactMatch = true;
                break;
            }
        }
    }
    
    if (!$isExactMatch) {
        $details = $result['matches'] ?? [];
        return [
            'status' => 'error', 
            'message' => 'We couldn\'t find an exact match for this address. Please check the street address, city, and state for typos.',
            'details' => $details // Include details for debugging/frontend
        ];
    }
    
    // --- 3. Check for DPV Deliverable ('Y' strictly required by TypeScript logic) ---
    $dpvConfirmation = $result['additionalInfo']['DPVConfirmation'] ?? 'N';
    
    if ($dpvConfirmation !== 'Y') {
        return [
            'status' => 'error', 
            'message' => 'This address is not deliverable by USPS. Please verify the address is complete and correct.',
            'dpvConfirmation' => $dpvConfirmation // Include status for debugging/frontend
        ];
    }
    
    // --- 4. Success: Return validated/formatted data ---
    $address = $result['address'];
    $info = $result['additionalInfo'];
    
    return [
        'status' => 'success',
        'valid' => true,
        'validatedAddress' => [
            'street' => $address['streetAddress'] ?? $street,
            'city' => $address['city'] ?? $city,
            'state' => $address['state'] ?? $state,
            'zipCode' => $address['ZIPCode'] ?? $zip,
            'zipPlus4' => $address['ZIPPlus4'] ?? null
        ],
        'metadata' => [
            'business' => ($info['business'] ?? 'N') === 'Y',
            'vacant' => ($info['vacant'] ?? 'N') === 'Y',
            'cmra' => ($info['DPVCMRA'] ?? 'N') === 'Y'
        ]
    ];
}


// --- Main Logic for AJAX Request ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Data is coming from jQuery AJAX post: txtAddr, txtCity, txtState, txtZip
$street = trim($_POST['txtAddr'] ?? '');
$city   = trim($_POST['txtCity'] ?? '');
$state  = trim($_POST['txtState'] ?? '');
$zip    = trim($_POST['txtZip'] ?? '');

if (empty($street) || empty($city) || empty($state) || empty($zip)) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all address fields.']);
    exit;
}

$validationResult = validateUSPSAddress($street, $city, $state, $zip);

echo json_encode($validationResult);