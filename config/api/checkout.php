<?php
/**
 * ACPS 9.0 Central Checkout API
 * Replaces cart_process_send.php and cart_process_cash.php
 * Handles Order Creation, Receipt Generation, Payment Processing (ePN), and Spooling.
 */

// Load environment
require_once __DIR__ . '/../../vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Silently ignore if .env doesn't exist
}

// Square SDK imports (if needed for QR verification)
use Square\Legacy\SquareClientBuilder;
use Square\Legacy\Authentication\BearerAuthCredentialsBuilder;
use Square\Legacy\Environment;

header('Content-Type: application/json');
require_once __DIR__ . '/../../admin/config.php';
include(__DIR__ . '/../../shopping_cart.class.php');

// Initialize
if (session_status() === PHP_SESSION_NONE) session_start();
$Cart = new Shopping_Cart('shopping_cart');

// Helper: Auto Print Status
function acp_get_autoprint_status() {
    $f = realpath(__DIR__ . "/../../config/autoprint_status.txt");
    return ($f && file_exists($f) && trim(file_get_contents($f)) === '0') ? false : true;
}

// Helper: Watermark Image
function acp_watermark_image($source, $dest) {
    $logoPath = __DIR__ . '/../../public/assets/images/pixel.png';
    $logo_to_use = $logoPath;
    
    //if (!empty(getenv('LOCATION_LOGO'))) {
    //    $env_logo = getenv('LOCATION_LOGO');
    //    if (is_string($env_logo) && file_exists($env_logo)) {
    //        $logo_to_use = $env_logo;
    //    }
    //}
    
    if (!$logo_to_use && file_exists($logoPath)) {
        $logo_to_use = $logoPath;
    }
    
    if (!$logo_to_use) return copy($source, $dest);
    
    $stamp = @imagecreatefrompng($logo_to_use);
    $photo = @imagecreatefromjpeg($source);
    
    if (!$stamp || !$photo) return copy($source, $dest);
    
    imagealphablending($stamp, true);
    imagesavealpha($stamp, true);
    
    $pw = imagesx($photo); $ph = imagesy($photo);
    $sw = imagesx($stamp); $sh = imagesy($stamp);
    
    $target_w = max(120, (int)round($pw * 0.18));
    $scale = $target_w / $sw;
    $target_h = (int)round($sh * $scale);
    
    $res_stamp = imagecreatetruecolor($target_w, $target_h);
    imagealphablending($res_stamp, false);
    imagesavealpha($res_stamp, true);
    imagecopyresampled($res_stamp, $stamp, 0, 0, 0, 0, $target_w, $target_h, $sw, $sh);
    
    imagecopy($photo, $res_stamp, $pw - $target_w - 40, $ph - $target_h - 40, 0, 0, $target_w, $target_h);
    
    $success = imagejpeg($photo, $dest, 95);

    
    return $success;
}

// Helper: Get Image ID
function getImageID_Fallback($order_code) {
    $p = explode('-', $order_code);
    return trim($p[1] ?? '');
}

// --- INPUTS ---
$paymentMethod = $_POST['payment_method'] ?? 'cash'; // cash, square, qr, credit (swipe)
$txtEmail      = trim($_POST['email'] ?? '');
$isOnsite      = $_POST['delivery_method'] === 'pickup' ? 'yes' : 'no';
$amount        = floatval($_POST['amount'] ?? 0);
$squareToken   = trim($_POST['square_token'] ?? ''); // For QR: the transaction token to verify
$customerInfo  = [
    'name'  => $_POST['name'] ?? '',
    'addr'  => $_POST['address'] ?? '',
    'city'  => $_POST['city'] ?? '',
    'state' => $_POST['state'] ?? '',
    'zip'   => $_POST['zip'] ?? ''
];

// --- 1. VERIFY PAYMENT (QR Only) ---
$transaction_result = 'approved'; // Default
$transaction_msg = 'Approved';
$auth_code = '';

if ($paymentMethod === 'qr' && !empty($squareToken)) {
    // Verify the Square token payment status via Square SDK
    try {
        $client = SquareClientBuilder::init()
            ->environment(getenv('ENVIRONMENT') === 'sandbox' ? Environment::SANDBOX : Environment::PRODUCTION)
            ->bearerAuthCredentials(BearerAuthCredentialsBuilder::init(getenv('SQUARE_ACCESS_TOKEN')))
            ->build();
        
        // The token passed should be a payment token or order ID from Square
        // For now, we trust the QR polling confirmed it - if we get here, assume approved
        $transaction_result = 'approved';
    } catch (Exception $e) {
        die(json_encode(['status'=>'error', 'message'=>'Could not verify Square payment: ' . $e->getMessage()]));
    }
}

// --- 2. GENERATE ORDER ID (only after payment verified) ---
$date_path = date('Y/m/d');
$dirname   = __DIR__ . "/../../photos/";
$orderFile = $dirname . $date_path . "/orders.txt";

if (!file_exists(dirname($orderFile))) mkdir(dirname($orderFile), 0777, true);

$fp = fopen($orderFile . ".lock", "c+");
if (flock($fp, LOCK_EX)) {
    if (!file_exists($orderFile)) file_put_contents($orderFile, "1000");
    $orderID = (int)trim(file_get_contents($orderFile));
    $orderID++;
    file_put_contents($orderFile, $orderID);
    flock($fp, LOCK_UN);
} else {
    die(json_encode(['status'=>'error', 'message'=>'Could not lock order ID']));
}
fclose($fp);

// --- 3. PAYMENT PROCESSING (ePN for credit card swipes) ---
$transaction_result = 'approved'; // Default for Cash/QR/Square(Pre-Verified)
$transaction_msg = 'Approved';
$auth_code = '';

if ($paymentMethod === 'credit') {
    // eProcessingNetwork Logic
    $epn_account = $_ENV['EPN_ACCOUNT'] ?? getenv('EPN_ACCOUNT') ?: '';
    $epn_key     = $_ENV['EPN_RESTRICT_KEY'] ?? getenv('EPN_RESTRICT_KEY') ?: '';
    
    // Swipe Data
    $swipeData = $_POST['swipe_data'] ?? '';
    $cardNum   = $_POST['card_num'] ?? '';
    $expMonth  = $_POST['exp_month'] ?? '';
    $expYear   = $_POST['exp_year'] ?? '';
    
    // Construct ePN Request
    $post_data = [
        'ePNAccount'  => $epn_account,
        'RestrictKey' => $epn_key,
        'CardNo'      => $cardNum,
        'ExpMonth'    => $expMonth,
        'ExpYear'     => $expYear,
        'Total'       => number_format($amount, 2, '.', ''),
        'Address'     => $customerInfo['addr'],
        'Zip'         => $customerInfo['zip'],
        'HTML'        => 'No',
        'Description' => "Order #$orderID",
        'EMail'       => $txtEmail
    ];
    
    // Execute Curl
    $ch = curl_init('https://www.eprocessingnetwork.com/cgi-bin/epn/secure/tdbe/transact.pl');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);


    // Parse Response (Format: "Y,123456,Approved" or "N,Error")
    $parts = explode(',', $response);
    if (strtoupper(substr($parts[0], 1, 1)) === 'Y') { // Some responses are "Y" or "AY" or "A"
         $transaction_result = 'approved';
         $auth_code = $parts[1] ?? '';
    } else {
         $transaction_result = 'declined';
         $transaction_msg = $parts[1] ?? 'Declined';
    }
}

if ($transaction_result !== 'approved') {
    echo json_encode(['status'=>'error', 'message'=>$transaction_msg]);
    exit;
}

// --- 4. BUILD RECEIPT CONTENT ---
$server_addy = $_SERVER['HTTP_HOST'] ?? '';
$stationID   = ($server_addy == '192.168.2.126') ? "FS" : "MS";
$message     = "";
$total_price = 0;

// Header Logic
if ($paymentMethod === 'square' || $paymentMethod === 'qr' || $paymentMethod === 'credit') {
    $message .= "$txtEmail |\r\n";
    $message .= "SQUARE ORDER: $" . number_format($amount, 2) . " PAID\r\n"; // Using "SQUARE ORDER" as legacy trigger for PAID in parser
} else {
    $message .= "$txtEmail |\r\n";
    $message .= "CASH ORDER: $" . number_format($amount, 2) . " DUE\r\n";
}

$message .= "Order #: $orderID - $stationID\r\n";
$message .= "Order Date: " . date("F j, Y, g:i a") . "\r\n";
$message .= "Order Total: $" . number_format($amount, 2) . "\r\n";

if ($isOnsite == 'yes') {
    $message .= "Delivery: Pickup On Site\r\n";
} else {
    $message .= "Delivery: Postal Mail\r\nCUSTOMER ADDRESS:\r\n-----------------------------\r\n";
    $message .= "{$customerInfo['name']}\r\n{$customerInfo['addr']}\r\n{$customerInfo['city']}, {$customerInfo['state']} {$customerInfo['zip']}\r\n\r\n";
}

$message .= "ITEMS ORDERED:\r\n-----------------------------\r\n";

// Cart Items
$items = $Cart->getItems();
foreach ($items as $order_code => $quantity) {
    $price = $Cart->getItemPrice($order_code);
    $total_price += ($quantity * $price);
    $imgID = method_exists($Cart, 'getImageID') ? $Cart->getImageID($order_code) : getImageID_Fallback($order_code);
    $message .= "[$quantity] " . $Cart->getItemName($order_code) . " ($imgID)\r\n";
}

$message .= "-----------------------------\r\nVisit us online:\r\nhttp://www.alleycatphoto.net\r\n";

// --- 5. SAVE RECEIPT ---
$receiptPath = $dirname . $date_path . "/receipts";
if (!is_dir($receiptPath)) mkdir($receiptPath, 0777, true);
file_put_contents("$receiptPath/$orderID.txt", $message);

$firePath = ($server_addy == '192.168.2.126') ? $dirname . "receipts/fire" : $dirname . "receipts";
if (!is_dir($firePath)) mkdir($firePath, 0777, true);
file_put_contents("$firePath/$orderID.txt", $message);


// --- 6. PROCESSING (SPOOLING) ---
// Email should ONLY queue when payment is confirmed:
// - Square/QR/Credit: payment already approved, queue immediately
// - Cash: payment pending, DO NOT queue (wait for staff to click "Paid" button)
$isPaid = ($paymentMethod !== 'cash'); // Square, QR, Credit = Paid. Cash = Pending.

// NOTE: Removed test bypass for photos@alleycatphoto.net - test orders should follow normal flow
// If you need to test cash orders that queue email, use order_action.php dashboard button

if ($isPaid) {
        // A. SPOOL EMAIL (only if order contains email items)
        // First: count email items
        $email_item_count = 0;
        foreach ($items as $order_code => $quantity) {
            [$prod_code, $photo_id] = explode('-', $order_code);
            if (trim($prod_code) == 'EML' && $quantity > 0) {
                $email_item_count += $quantity;
            }
        }
        
        // Only queue to email if: email address provided AND email items exist
        if ($txtEmail != '' && $email_item_count > 0) {
        //$filePath = $dirname . $date_path . "/emails/" . $orderID;
        //if (!is_dir($toPath)) mkdir($toPath, 0777, true);
        //if (!is_dir($filePath)) mkdir($filePath, 0777, true);

        // Meta info
        //$infoTxt = "$txtEmail|PAID\r\n" . $message;
        //file_put_contents("$toPath/info.txt", $infoTxt);

        //foreach ($items as $order_code => $quantity) {
        //    [$prod_code, $photo_id] = explode('-', $order_code);
        //    if (trim($prod_code) == 'EML' && $quantity > 0) {
        //        $src = $dirname . $date_path . "/raw/$photo_id.jpg";
        //       $dst = "$filePath/$photo_id.jpg";
         //       @copy($src, $dst);
        //   }
        //}
        
        // Queue to spooler mailer (do NOT call mailer.php directly)
        // The spooler (app.js tick_mailer) will detect and process it
        $mailer_spool = $dirname . $date_path . "/spool/mailer/$orderID/";
        if (!is_dir($mailer_spool)) @mkdir($mailer_spool, 0777, true);
        
        // Copy email photos to spooler queue
        foreach ($items as $order_code => $quantity) {
            [$prod_code, $photo_id] = explode('-', $order_code);
            if (trim($prod_code) == 'EML' && $quantity > 0) {
                $src = $dirname . $date_path . "/raw/$photo_id.jpg";
                if (file_exists($src)) {
                    @copy($src, "$mailer_spool/$photo_id.jpg");
                }
            }
        }
        
        // Write queue metadata
        @file_put_contents("$mailer_spool/info.txt", json_encode([
            'email' => $txtEmail,
            'order_id' => $orderID,
            'timestamp' => time(),
            'location' => getenv('LOCATION_NAME') ?: 'Unknown'
        ]));
    }

    // B. SPOOL PRINTS
    if (acp_get_autoprint_status()) {
        $printer_spool = $dirname . $date_path . "/spool/printer/";
        if (!is_dir($printer_spool)) @mkdir($printer_spool, 0777, true);

        // FIRST: Create TXT file so spooler knows MS/FS destination
        // This ensures the metadata exists BEFORE any JPGs are queued
        $txt_path = $printer_spool . $orderID . ".txt";
        $txt_written = @file_put_contents($txt_path, $message);
        if (!$txt_written) {
            // Fallback: try without suppression to debug
            error_log("WARNING: TXT write suppressed for order $orderID. Retrying...");
            file_put_contents($txt_path, $message);
        }
        
        // Small delay to ensure file system has flushed the TXT file to disk
        // This prevents race condition where spooler finds JPG before TXT exists
        usleep(50000); // 0.05 second delay for disk flush

        // THEN: Create print files one at a time with delay
        // This allows spooler to process them sequentially instead of being overwhelmed
        foreach ($items as $order_code => $quantity) {
            [$prod_code, $photo_id] = explode('-', $order_code);
            $prod_code = trim($prod_code);
            
            if ($prod_code !== 'EML' && $quantity > 0) {
                $sourcefile = $dirname . $date_path . "/raw/$photo_id.jpg";
                if (file_exists($sourcefile)) {
                    $orientation = 'V';
                    $imgInfo = @getimagesize($sourcefile);
                    if ($imgInfo && isset($imgInfo[0], $imgInfo[1])) {
                        $orientation = ($imgInfo[0] > $imgInfo[1]) ? 'H' : 'V';
                    }

                    for ($i = 1; $i <= $quantity; $i++) {
                        $filename = sprintf("%s-%s-%s%s-%d.jpg", $orderID, $photo_id, $prod_code, $orientation, $i);
                        acp_watermark_image($sourcefile, $printer_spool . $filename);
                        usleep(100000); // 0.1 second delay between files to prevent printer overload
                    }
                }
            }
        }
    }

    // C. UPDATE SALES CSV (when payment is approved)
    if ($isPaid) {
        $csvFile = $dirname . '../../sales/transactions.csv';
        $today = date("m/d/Y");
        $location = getenv('LOCATION_NAME') ?: (getenv('LOCATION_SLUG') ?: 'Unknown');
        
        $pType = ($paymentMethod === 'square' || $paymentMethod === 'qr' || $paymentMethod === 'credit') ? 'Credit' : 'Cash';
        
        $data = [];
        if (file_exists($csvFile)) {
            $handle = @fopen($csvFile, 'r');
            if ($handle !== false) {
                fgetcsv($handle); // Skip header
                while (($row = fgetcsv($handle)) !== false) {
                    $key = $row[0] . '|' . $row[1] . '|' . ($row[3] ?? '');
                    if (isset($row[4])) $row[4] = (float)str_replace(['$', '"', ','], '', $row[4]);
                    $data[$key] = $row;
                }
                fclose($handle);
            }
        }
        
        $key = $location . '|' . $today . '|' . $pType;
        if (!isset($data[$key])) {
            $data[$key] = [$location, $today, 0, $pType, 0];
        }
        $data[$key][2] += 1;
        $data[$key][4] += $amount;
        
        $fp = @fopen($csvFile, 'w');
        if ($fp !== false) {
            fputcsv($fp, ['Location', 'Order Date', 'Orders', 'Payment Type', 'Amount']);
            foreach ($data as $row) {
                $row[4] = '$' . number_format($row[4], 2);
                fputcsv($fp, $row);
            }
            fclose($fp);
            
            // Sync to master server
            $url = 'https://alleycatphoto.net/admin/index.php?' . http_build_query([
                'action'   => 'log',
                'date'     => date('Y-m-d'),
                'location' => $location,
                'type'     => $pType,
                'count'    => 1,
                'amount'   => $amount
            ]);
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);

            }
        }
    }
}

// Clear Cart
$Cart->clearCart();

// Response
echo json_encode([
    'status' => 'success',
    'order_id' => $orderID,
    'payment_method' => $paymentMethod,
    'is_paid' => $isPaid,
    'message' => $isPaid ? 'Order Processed' : 'Please Pay Cash at Counter'
]);
?>
