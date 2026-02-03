<?php
// ACPS API - Order Action Endpoint (PAID/VOID/EMAIL)
// Location: /config/api/order_action.php (Phasing out legacy /admin/admin_cash_order_action.php)

header('Content-Type: application/json');
require_once __DIR__ . '/../../admin/config.php';

ini_set('memory_limit', '-1');
set_time_limit(0);
ignore_user_abort();


// Location identification (prefer LOCATION_NAME, fallback to LOCATION_SLUG, then UNKNOWN)
$location = getenv('LOCATION_NAME');
if ($location && trim($location) !== '') {
    $location = trim($location);
} else if (getenv('LOCATION_SLUG') && trim(getenv('LOCATION_SLUG')) !== '') {
    $location = trim(getenv('LOCATION_SLUG'));
} else {
    $location = 'UNKNOWN';
}

$orderID = isset($_POST['order'])  ? trim($_POST['order'])  : '';
$action  = isset($_POST['action']) ? trim($_POST['action']) : '';
$paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
$transactionId = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';

if ($orderID === '' || !preg_match('/^\d+$/', $orderID)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing order number.']);
    exit;
}

$action = strtolower($action);
if (!in_array($action, ['paid', 'void', 'email'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    exit;
}

$baseDir = realpath(__DIR__ . '/../../photos');
if (!$baseDir) {
    echo json_encode(['status' => 'error', 'message' => 'Photo base directory not found.']);
    exit;
}

$date_path   = date('Y/m/d');
$receiptPath = $baseDir . '/' . $date_path . '/receipts/' . $orderID . '.txt';

if (!file_exists($receiptPath)) {
    echo json_encode(['status' => 'error', 'message' => "Receipt not found for Order #$orderID"]);
    exit;
}

$receiptData = file_get_contents($receiptPath);
if ($receiptData === false || $receiptData === '') {
    echo json_encode(['status' => 'error', 'message' => "Unable to read receipt for Order #$orderID"]);
    exit;
}

// --- Helper: Get Auto Print Status from config file
function acp_get_autoprint_status(): bool {
    $statusFilePath = realpath(__DIR__ . "/../../config/autoprint_status.txt");
    if ($statusFilePath && file_exists($statusFilePath)) {
        $content = @file_get_contents($statusFilePath);
        return trim($content) === '1';
    }
    return true;
}


function acp_watermark_image($source, $dest) {
    $logoPath = __DIR__ . '/../../public/assets/images/pixel.png';
    $logo_to_use = $logoPath;
    
    // Check LOCATION_LOGO env var first
    //if (!empty(getenv('LOCATION_LOGO'))) {
    //    $env_logo = getenv('LOCATION_LOGO');
    //    if (is_string($env_logo) && file_exists($env_logo)) {
    //        $logo_to_use = $env_logo;
    //    }
    // }
    
    // Fallback to local path
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

function acp_log_event($orderID, $event) {
    // Local file logging for visibility
    $log_file = __DIR__ . '/../../logs/order_action_' . date('Y-m-d') . '.log';
    if (!is_dir(dirname($log_file))) @mkdir(dirname($log_file), 0777, true);
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] Order {$orderID} | {$event}\n";
    @file_put_contents($log_file, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also log to legacy system (fsockopen)
    $log_data = [
        'log_message' => "Order {$orderID} | {$event}",
    ];
    $payload = http_build_query($log_data);
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $port = $_SERVER['SERVER_PORT'] ?? 80;
    if (strpos($host, ':') !== false) {
        list($host, $port) = explode(':', $host);
    }
    $fp = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($fp) {
        $out = "POST /admin/admin_cash_order_log.php?action=log HTTP/1.1\r\n";
        $out .= "Host: {$host}\r\n";
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= "Content-Length: " . strlen($payload) . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        $out .= $payload;
        fwrite($fp, $out);
        fclose($fp);
        return true;
    }
    return false;
}

function acp_sync_log_to_master($location, $dateISO, $type, $count, $amount) {
    $url = 'https://alleycatphoto.net/admin/index.php?' . http_build_query([
        'action'   => 'log',
        'date'     => $dateISO,
        'location' => $location,
        'type'     => $type,
        'count'    => $count,
        'amount'   => $amount
    ]);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);

    } else {
        @file_get_contents($url);
    }
}

function acp_parse_receipt_items(array $lines): array {
    $items = [];
    foreach ($lines as $line) {
        $lineTrim = trim($line);
        if (preg_match('/\[(\d+)\]\s*(.+?)\s*\((\d+)\)/', $lineTrim, $m)) {
            $qty       = intval($m[1]);
            $item_name = trim($m[2]);
            $photo_id  = trim($m[3]);
            if (preg_match('/(\d+x\d+)/', $item_name, $size)) {
                $prod_code = $size[1];
            } elseif (stripos($item_name, 'email') !== false) {
                $prod_code = 'EML';
            } else {
                $prod_code = 'UNK';
            }
            $items[] = [
                'prod_code' => $prod_code,
                'photo_id'  => $photo_id,
                'quantity'  => $qty
            ];
        }
    }
    return $items;
}

function acp_update_cash_status(string $receipt, string $newStatus): array {
    $count = 0;
    $updated = preg_replace(
        '/^(CASH ORDER:\s*\$[0-9]+(?:\.[0-9]{2})?)\s+DUE\s*$/mi',
        '$1 ' . strtoupper($newStatus),
        $receipt,
        1,
        $count
    );
    if ($updated === null) {
        return [$receipt, 0];
    }
    return [$updated, $count];
}

function acp_update_square_status(string $receipt, string $transactionId): array {
    $count = 0;
    if (preg_match('/CASH ORDER:\s*\$([0-9]+(?:\.[0-9]{2})?)\s+DUE/i', $receipt, $m)) {
        $originalAmt = floatval($m[1]);
        $taxedAmt = ($originalAmt * 1.035) * 1.0675;
        $taxedAmtStr = number_format($taxedAmt, 2);
        $originalAmtStr = number_format($originalAmt, 2);

        $updated = preg_replace(
            '/^CASH ORDER:\s*\$[0-9]+(?:\.[0-9]{2})?\s+DUE\s*$/mi',
            'SQUARE ORDER: \\$' . $taxedAmtStr . ' PAID',
            $receipt,
            1,
            $count
        );

        $updated = str_replace('$' . $originalAmtStr, '$' . $taxedAmtStr, $updated);
        $updated .= "\nSQUARE CONFIRMATION: " . $transactionId . "\n";
        
        return [$updated, 1];
    }
    return [$receipt, 0];
}

function acp_update_qr_status(string $receipt, string $transactionId): array {
    $count = 0;
    if (preg_match('/CASH ORDER:\s*\$([0-9]+(?:\.[0-9]{2})?)\s+DUE/i', $receipt, $m)) {
        $originalAmt = floatval($m[1]);
        $taxedAmt = ($originalAmt * 1.035) * 1.0675;
        $taxedAmtStr = number_format($taxedAmt, 2);
        $originalAmtStr = number_format($originalAmt, 2);

        $updated = preg_replace(
            '/^CASH ORDER:\s*\$[0-9]+(?:\.[0-9]{2})?\s+DUE\s*$/mi',
            'SQUARE ORDER: \\$' . $taxedAmtStr . ' PAID',
            $receipt,
            1,
            $count
        );

        $updated = str_replace('$' . $originalAmtStr, '$' . $taxedAmtStr, $updated);
        $updated .= "\nQR CONFIRMATION: " . $transactionId . "\n";
        
        return [$updated, 1];
    }
    return [$receipt, 0];
}

function acp_send_digital_email($orderID): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $url    = $scheme . '://' . $host . '/mailer.php?order=' . urlencode($orderID);
    $body = '';
    $ok   = false;
    $payload = http_build_query([]);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/plain,*/*']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

        $cacertPath = realpath(__DIR__ . '/../../cacert.pem');
        if ($cacertPath && file_exists($cacertPath)) {
            curl_setopt($ch, CURLOPT_CAINFO, $cacertPath);
        }
        $body   = curl_exec($ch);
        $errNo  = curl_errno($ch);
        $errStr = curl_error($ch);


        if ($errNo === 0 || $errNo === 28) {
            $ok = true; 
            $body = "Email triggered in background (Timeout set to 1s).";
        } else {
            $body = 'cURL error: ' . $errStr;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                             "Accept: text/plain,*/*\r\n",
                'content' => $payload,
                'timeout' => 10,
            ]
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body !== false) {
            $text = preg_replace('/<[^>]*>/', '', $body);
            $ok   = (bool)preg_match('/Message has been sent/i', $text);
        } else {
            $body = 'HTTP request to /mailer.php failed.';
        }
    }
    return [
        'success' => $ok,
        'raw'     => $body,
    ];
}

// --- Main Logic Starts Here ---

$day_spool = $baseDir . '/' . $date_path . "/spool/";
$printer_spool = $day_spool . "printer/";
$mailer_spool = $day_spool . "mailer/";

// Ensure spool directories exist
if (!is_dir($printer_spool)) mkdir($printer_spool, 0777, true);
if (!is_dir($mailer_spool)) mkdir($mailer_spool, 0777, true);

$normalized  = str_replace("\r", "", $receiptData);
$lines       = explode("\n", $normalized);
$items       = acp_parse_receipt_items($lines);

// Extract customer email if present
$user_email = '';
foreach ($lines as $line) {
    if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $line, $m)) {
        $user_email = trim(strtolower($m[1]));
        break;
    }
}

$copiedFiles    = [];
$emailAttempted = false;
$emailSuccess   = false;
$emailRaw       = null;

if ($action === 'email') {
    // Direct manual email trigger from console
    $emailResult = acp_send_digital_email($orderID);
    if ($emailResult['success']) {
        acp_log_event($orderID, "EMAIL_OK");
        echo json_encode([
            'status'          => 'success',
            'message'         => "Email sent for Order #$orderID.",
            'email_attempted' => true,
            'email_success'   => true,
        ]);
    } else {
        acp_log_event($orderID, "EMAIL_ERROR: {$emailResult['raw']}");
        echo json_encode([
            'status'          => 'error',
            'message'         => "Email step failed for Order #$orderID.",
            'email_attempted' => true,
            'email_success'   => false,
            'email_raw'       => $emailResult['raw'],
        ]);
    }
    exit;
}

if ($action === 'paid') {
    acp_log_event($orderID, "PAID_ACTION_START: method=$paymentMethod, location=$location, trans_id=$transactionId");
    $shouldAutoPrint = acp_get_autoprint_status();
    acp_log_event($orderID, "AUTO_PRINT_STATUS: " . ($shouldAutoPrint ? 'ENABLED' : 'DISABLED'));
    
    foreach ($items as $item) {
        $prod_code = $item['prod_code'];
        $photo_id  = $item['photo_id'];
        $quantity  = $item['quantity'];

        // 1. Handle Printer Spooling
        if ($prod_code !== 'EML' && $shouldAutoPrint) {
            $sourcefile = $baseDir . '/' . $date_path . '/raw/' . $photo_id . '.jpg';
            if (file_exists($sourcefile)) {
                $orientation = 'V';
                $imgInfo = @getimagesize($sourcefile);
                if ($imgInfo && isset($imgInfo[0], $imgInfo[1])) {
                    $orientation = ($imgInfo[0] > $imgInfo[1]) ? 'H' : 'V';
                }

                for ($i = 1; $i <= $quantity; $i++) {
                    $filename = sprintf("%s-%s-%s%s-%d.jpg", $orderID, $photo_id, $prod_code, $orientation, $i);
                    if (acp_watermark_image($sourcefile, $printer_spool . $filename)) {
                        $copiedFiles[] = $filename;
                        acp_log_event($orderID, "PRINT_QUEUED: $filename (qty=$i/$quantity, code=$prod_code, orient=$orientation)");
                        usleep(250000); // 0.25 second delay between files to ensure FS/network write completes
                    }
                }
                // Log receipt for reference in spool
                file_put_contents($printer_spool . $orderID . ".txt", $receiptData, FILE_APPEND);
            }
        }

        // 2. Handle Mailer Spooling
        if ($user_email !== '') {
            $emailAttempted = true;
            $order_mail_dir = $mailer_spool . $orderID . "/";
            if (!is_dir($order_mail_dir)) mkdir($order_mail_dir, 0777, true);
            
            acp_log_event($orderID, "EMAIL_QUEUE_START: $user_email");
            
            $sourcefile = $baseDir . '/' . $date_path . '/raw/' . $photo_id . '.jpg';
            if (file_exists($sourcefile)) {
                copy($sourcefile, $order_mail_dir . $photo_id . ".jpg");
                acp_log_event($orderID, "EMAIL_QUEUE_PHOTO: $photo_id.jpg queued for $user_email");
            }
            
            file_put_contents($order_mail_dir . "info.txt", json_encode([
                'email' => $user_email,
                'order_id' => $orderID,
                'timestamp' => time(),
                'location' => $location
            ]));
            acp_log_event($orderID, "EMAIL_QUEUE_READY: Waiting for spooler.tick_mailer to trigger gmailer.php");
        }
    }

    // Email will be sent by spooler's tick_mailer when it detects the queued order
    // This ensures single source of truth and prevents race conditions
    if ($emailAttempted) {
        acp_log_event($orderID, "SPOOLER_WILL_SEND_EMAIL: tick_mailer will call gmailer.php");
        $emailSuccess = true;
        $emailRaw = "Email queued for spooler processing.";
    }

    if (!empty($copiedFiles)) {
        acp_log_event($orderID, "SPOOL_PRINT_OK (x".count($copiedFiles).")");
    }

    // Update receipt text based on payment method
    if ($paymentMethod === 'square') {
        list($updatedReceipt, $changed) = acp_update_square_status($receiptData, $transactionId);
    } else if ($paymentMethod === 'qr') {
        list($updatedReceipt, $changed) = acp_update_qr_status($receiptData, $transactionId);
    } else {
        list($updatedReceipt, $changed) = acp_update_cash_status($receiptData, 'PAID');
    }

    if ($changed > 0) {
        file_put_contents($receiptPath, $updatedReceipt);
        $receiptData = $updatedReceipt;
        acp_log_event($orderID, strtoupper($paymentMethod));

        // --- Log to Daily CSV ---
        if (preg_match('/(?:CASH|SQUARE|QR) ORDER:\s*\$([0-9]+\.[0-9]{2})\s*PAID/i', $updatedReceipt, $m)) {
            $txtAmt = floatval($m[1]);
            $csvFile = __DIR__ . '/../../sales/transactions.csv';
            $today = date("m/d/Y");
            $locationKey = $location;

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

            $pType = ($paymentMethod === 'square' || $paymentMethod === 'qr') ? 'Credit' : 'Cash';
            $key = $locationKey . '|' . $today . '|' . $pType;
            if (!isset($data[$key])) {
                $data[$key] = [$locationKey, $today, 0, $pType, 0];
            }
            $data[$key][2] += 1;
            $data[$key][4] += $txtAmt;

            $fp = @fopen($csvFile, 'w');
            if ($fp !== false) {
                fputcsv($fp, ['Location', 'Order Date', 'Orders', 'Payment Type', 'Amount']);
                foreach ($data as $row) {
                    $row[4] = '$' . number_format($row[4], 2);
                    fputcsv($fp, $row);
                }
                fclose($fp);
                acp_sync_log_to_master($locationKey, date('Y-m-d'), $pType, $data[$key][2], $data[$key][4]);
            }
        }
    }
    $statusMsg = "Order #$orderID marked " . strtoupper($paymentMethod) . " PAID.";
} else if ($action === 'void') {
    list($updatedReceipt, $changed) = acp_update_cash_status($receiptData, 'VOID');
    if ($changed > 0) {
        file_put_contents($receiptPath, $updatedReceipt);
        $receiptData = $updatedReceipt;
        acp_log_event($orderID, "VOID");
    }
    $statusMsg = "Order #$orderID voided.";
}

echo json_encode([
    'status'          => 'success',
    'message'         => $statusMsg ?? '',
    'action'          => $action,
    'files'           => $copiedFiles,
    'email_attempted' => $emailAttempted,
    'email_success'   => $emailSuccess,
    'email_raw'       => $emailRaw,
    'receipt'         => nl2br(htmlspecialchars($receiptData, ENT_QUOTES, 'UTF-8')),
]);

