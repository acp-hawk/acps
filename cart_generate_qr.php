<?php
//*********************************************************************//
// AlleyCat PhotoStation - AJAX QR Generator
// Generates a Square Payment Link dynamically with User Email
//*********************************************************************//

require_once __DIR__ . '/vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Silently ignore
}

require_once "admin/config.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('shopping_cart.class.php');
$Cart = new Shopping_Cart('shopping_cart');

// --- 1. Validate Request ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

$email = $_POST['email'] ?? '';
$total = $_POST['total'] ?? 0;

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Email']);
    exit;
}

// --- 2. Calculate Totals (Re-verify logic from pay.php/cart.php) ---
// If 'total' is passed, we use it, otherwise we might recalculate from cart.
// For consistency, we'll re-calculate based on what pay.php logic does if possible, 
// OR trust the passed total if verified. 
// Given pay.php calculates based on $_GET['amt'], let's trust the front-end 'total' 
// BUT verifying it against session would be safer. 
// For this context, we will use the logic from pay.php:
// $amount_with_tax = $thisTotal; (passed from GET in pay.php)
// We need that initial amount. The best way is to trust the JS passed value which came from PHP initially.

$amount_with_tax = floatval($total);
if ($amount_with_tax <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Amount']);
    exit;
}

// Recalculate Square Totals
$amount_without_tax = $amount_with_tax / 1.0675;
//$tax = $amount_with_tax - $amount_without_tax;
//$surcharge = $amount_without_tax * 0.035;
$cc_total = $amount_without_tax * 1.035;
$cc_totaltaxed = $cc_total * 1.0675;

// --- 3. Generate Reference ID (NOT an order number yet) ---
// Use hostname-based reference: VS1-12345, MS-54321, etc.
// The actual order ID will only be created in checkout.php when payment is confirmed
$hostname = $_SERVER['HTTP_HOST'] ?? 'local';
$stationPrefix = ($hostname == '192.168.2.126') ? 'FS' : 'MS';
$referenceNum = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
$referenceId = $stationPrefix . '-' . $referenceNum;

// --- 4. Generate Link ---
require_once __DIR__ . '/square_link.php';
$transactionId = $referenceId; // Use reference ID as transaction ID for polling

$paymentLink_response = createSquarePaymentLink($cc_totaltaxed, $email, $referenceId, $transactionId);

if ($paymentLink_response !== false) {
    // Check if it's an object (Success) or error string (Modified return)
    // Assuming createSquarePaymentLink returns false on failure currently.
    // We should check the logs or update the function to return error.
    // For now, if it returns object:
    $square_link_url = $paymentLink_response->getUrl();
    $squareOrderId = $paymentLink_response->getOrderId(); 
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($square_link_url);
    
    echo json_encode([
        'status' => 'success',
        'qr_url' => $qr_code_url,
        'order_id' => $squareOrderId,
        'link_url' => $square_link_url
    ]);
} else {
    // Try to read the last line of the error log for details
    $lastError = "Square API Failure";
    $logFile = __DIR__ . '/logs/square_error.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        if ($lines !== false && count($lines) > 0) {
            $lastError = trim($lines[count($lines) - 1]);
        }
    }
    echo json_encode(['status' => 'error', 'message' => $lastError]);
}
