<?php
/**
 * Spooler API (Enhanced)
 * Manages printer/mailer queues, history, system health, and SILENT RECEIPT PRINTING.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/../../admin/config.php';
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['status' => 'error', 'message' => 'Vendor autoload missing. Run composer install.']);
    exit;
}
require_once $autoload;
try { $dotenv = Dotenv\Dotenv::createImmutable(realpath(__DIR__ . '/../../')); $dotenv->safeLoad(); } catch (Exception $e) {}

// Paths
$base_dir = realpath(__DIR__ . '/../../');
$date_path = date("Y/m/d");
$spool_base = $base_dir . '/photos/' . $date_path . '/spool/';
$email_archive = $base_dir . '/photos/' . $date_path . '/emails/';
$printer_spool = $spool_base . 'printer/';
$mailer_spool = $spool_base . 'mailer/';
$completed_spool = $spool_base . 'completed/';
$physical_printer_path = $_ENV['PRINTER_HOTFOLDER_MAIN'] ?? 'c:/orders/';
$physical_printer_path_fire = $_ENV['PRINTER_HOTFOLDER_FIRE'] ?? 'C:/orders/';
$print_log_file = $base_dir . '/logs/print_history_' . date("Y-m-d") . '.json';
$credentialsPath = $base_dir . '/config/google/credentials.json';
$tokenPath = $base_dir . '/config/google/token.json';

// Receipt Hot Folders
$receipt_hot_main = $base_dir . '/photos/receipts/';
$receipt_hot_fire = $base_dir . '/photos/receipts/fire/';

// Ensure directories exist
if (!is_dir($printer_spool)) @mkdir($printer_spool, 0777, true);
if (!is_dir($mailer_spool)) @mkdir($mailer_spool, 0777, true);
if (!is_dir($completed_spool)) @mkdir($completed_spool, 0777, true);
if (!is_dir(dirname($print_log_file))) @mkdir(dirname($print_log_file), 0777, true);
if (!is_dir($receipt_hot_main)) @mkdir($receipt_hot_main, 0777, true);
if (!is_dir($receipt_hot_fire)) @mkdir($receipt_hot_fire, 0777, true);
if (!is_dir($physical_printer_path_fire)) @mkdir($physical_printer_path_fire, 0777, true);

$action = $_GET['action'] ?? 'status';

function get_print_history($log_file) {
    if (!file_exists($log_file)) return [];
    $data = json_decode(file_get_contents($log_file), true);
    return is_array($data) ? array_reverse($data) : [];
}

function log_print($log_file, $filename) {
    $history = [];
    if (file_exists($log_file)) {
        $history = json_decode(file_get_contents($log_file), true);
        if (!is_array($history)) $history = [];
    }
    $parts = explode('-', $filename);
    $order_id = $parts[0] ?? 'Unknown';
    $history[] = [
        'timestamp' => time(),
        'file' => $filename,
        'order_id' => $order_id
    ];
    if (count($history) > 200) $history = array_slice($history, -200);
    file_put_contents($log_file, json_encode($history));
}

function get_valid_token($credPath, $tokenPath) {
    global $base_dir;
    if (!file_exists($tokenPath)) return null;
    $token = json_decode(file_get_contents($tokenPath), true);
    if (($token['created'] + ($token['expires_in'] ?? 3600) - 60) < time()) {
        if (!file_exists($credPath)) return null;
        $creds = json_decode(file_get_contents($credPath), true)['installed'];
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CAINFO, $base_dir . '/cacert.pem');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'refresh_token' => $token['refresh_token'] ?? '',
            'grant_type' => 'refresh_token'
        ]));
        $res = json_decode(curl_exec($ch), true);
        if (isset($res['access_token'])) {
            $token['access_token'] = $res['access_token'];
            $token['created'] = time();
            file_put_contents($tokenPath, json_encode($token, JSON_PRETTY_PRINT));
        } else {
            return null;
        }
    }
    return $token['access_token'];
}

function google_api_call($url, $method, $token) {
    global $base_dir;
    $ch = curl_init($url);
    $headers = ["Authorization: Bearer $token", "Content-Type: application/json"];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CAINFO, $base_dir . '/cacert.pem');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'body' => json_decode($resp, true)];
}

switch ($action) {
    case 'status':
        // Filter out .txt files from printer queue display
        $all_printer_files = array_diff(scandir($printer_spool), array('.', '..'));
        $printer_queue = array_values(array_filter($all_printer_files, function($f) {
            return stripos($f, '.jpg') !== false;
        }));

        $mailer_queue = array_values(array_diff(scandir($mailer_spool), array('.', '..')));
        $sent_emails = [];
        if (is_dir($email_archive)) {
            $folders = glob($email_archive . '*', GLOB_ONLYDIR);
            if ($folders) {
                usort($folders, function($a, $b) { return filemtime($b) - filemtime($a); });
                $folders = array_slice($folders, 0, 50);
                foreach ($folders as $dir) {
                    $oid = basename($dir);
                    $info_file = $dir . "/info.txt";
                    $email_addr = 'Unknown';
                    if (file_exists($info_file)) {
                        $meta = json_decode(file_get_contents($info_file), true);
                        $email_addr = $meta['email'] ?? 'Unknown';
                    }
                    $sent_emails[] = ['order_id' => $oid, 'time' => filemtime($dir), 'email' => $email_addr];
                }
            }
        }
        $print_history = get_print_history($print_log_file);
        $alert_level = 'normal';
        if (count($printer_queue) > 3) $alert_level = 'warning';
        if (count($printer_queue) > 8) $alert_level = 'critical';
        echo json_encode([
            'printer_count' => count($printer_queue),
            'mailer_count' => count($mailer_queue),
            'printer_items' => $printer_queue,
            'mailer_items' => $mailer_queue,
            'email_history' => $sent_emails,
            'print_history' => $print_history,
            'alert_level' => $alert_level
        ]);
        break;

    case 'health_check':
        $token = get_valid_token($credentialsPath, $tokenPath);
        $gmail_status = 'disconnected';
        $drive_status = 'disconnected';
        $account_email = 'Unknown';
        if ($token) {
            $g_res = google_api_call("https://www.googleapis.com/oauth2/v2/userinfo", "GET", $token);
            if ($g_res['code'] == 200) {
                $gmail_status = 'connected';
                $account_email = $g_res['body']['email'];
            }
            $d_res = google_api_call("https://www.googleapis.com/drive/v3/files?pageSize=1", "GET", $token);
            if ($d_res['code'] == 200) {
                $drive_status = 'connected';
            }
        }
        echo json_encode([
            'gmail' => $gmail_status,
            'drive' => $drive_status,
            'account' => $account_email,
            'token_exists' => file_exists($tokenPath)
        ]);
        break;

    case 'tick_printer':
        clearstatcache(); // Ensure we have the latest file status to prevent race conditions
        // Check both hot folders
        $current_printer_files_main = file_exists($physical_printer_path) ? array_diff(scandir($physical_printer_path), array('.', '..', 'Archive', 'archive', 'Thumbs.db')) : [];
        $current_printer_files_fire = file_exists($physical_printer_path_fire) ? array_diff(scandir($physical_printer_path_fire), array('.', '..', 'Archive', 'archive', 'Thumbs.db')) : [];

        $main_busy = count($current_printer_files_main) > 0;
        $fire_busy = count($current_printer_files_fire) > 0;

        // Queue processing
        $queued_files = array_diff(scandir($printer_spool), array('.', '..'));
        sort($queued_files);
        
        $jpgs = array_filter($queued_files, function($f) { return stripos($f, '.jpg') !== false; });
        
        $moved_files = [];

        if (count($jpgs) > 0) {
            foreach ($jpgs as $file_to_move) {
                // Determine destination based on TXT file content
                $parts = explode('-', $file_to_move);
                $order_id = $parts[0];
                $txt_file = $printer_spool . $order_id . ".txt";
                
                // --- Defensive Check for TXT file ---
                if (!file_exists($txt_file) || filesize($txt_file) === 0) {
                    // As a last resort, try the fallback recovery. Check today AND yesterday for midnight rollovers.
                    $receipt_file_today = __DIR__ . '/../../photos/' . date('Y/m/d') . "/receipts/$order_id.txt";
                    $receipt_file_yesterday = __DIR__ . '/../../photos/' . date('Y/m/d', strtotime('-1 day')) . "/receipts/$order_id.txt";
                    
                    $receipt_to_copy = null;
                    if (file_exists($receipt_file_today)) {
                        $receipt_to_copy = $receipt_file_today;
                    } elseif (file_exists($receipt_file_yesterday)) {
                        $receipt_to_copy = $receipt_file_yesterday;
                    }
            
                    if ($receipt_to_copy) {
                        @copy($receipt_to_copy, $txt_file);
                    }
                    
                    // CRITICAL: Even if we attempted recovery, we wait for the next tick to process.
                    error_log("Spooler Warning: TXT for Order {$order_id} was missing/empty. Attempted recovery. Will re-process on next tick.");
                    continue; 
                }

                $content = file_get_contents($txt_file);
                if ($content === false) {
                    error_log("Spooler Error: Could not read TXT for Order {$order_id} despite it existing. Skipping.");
                    continue;
                }

                $is_fire = (strpos($content, '- FS') !== false || strpos($content, 'Fire Station') !== false);

                if ($is_fire) {
                    if (!$fire_busy) {
                        $dest = $physical_printer_path_fire . $file_to_move;
                        if (@rename($printer_spool . $file_to_move, $dest)) {
                            log_print($print_log_file, $file_to_move);
                            $moved_files[] = ['file' => $file_to_move, 'station' => 'Fire'];
                            usleep(250000); // 0.25s delay to guarantee FS/Network write completes
                        }
                    }
                } else { // Not a fire station order
                    if (!$main_busy) {
                        $dest = $physical_printer_path . $file_to_move;
                        if (@rename($printer_spool . $file_to_move, $dest)) {
                            log_print($print_log_file, $file_to_move);
                            $moved_files[] = ['file' => $file_to_move, 'station' => 'Main'];
                            usleep(250000); // 0.25s delay to guarantee FS/Network write completes
                        }
                    }
                }
            }
            
            if (count($moved_files) > 0) {
                 echo json_encode(['status' => 'success', 'moved' => $moved_files]);
            } else {
                 echo json_encode(['status' => 'busy', 'main_count' => count($current_printer_files_main), 'fire_count' => count($current_printer_files_fire)]);
            }

        } else {
            // Cleanup TXT files only if all JPGs for that order are gone
            $txts = array_filter($queued_files, function($f) { return stripos($f, '.txt') !== false; });
            foreach ($txts as $txt) {
                $order_id = pathinfo($txt, PATHINFO_FILENAME);
                // Check if any JPGs remain for this order
                $remaining_jpgs = array_filter($queued_files, function($f) use ($order_id) {
                    return stripos($f, $order_id . '-') === 0 && stripos($f, '.jpg') !== false;
                });
                
                if (count($remaining_jpgs) === 0) {
                    @rename($printer_spool . $txt, $completed_spool . $txt);
                }
            }
            echo json_encode(['status' => 'idle', 'message' => 'No JPGs']);
        }
        break;

    case 'tick_mailer':
        // Watchdog: Check for items in mailer spool and process them
        $orders = array_diff(scandir($mailer_spool), array('.', '..'));
        $triggered = [];
        $timeout = 2; // Process items that are at least 2 seconds old (to avoid race conditions during queue creation)
        $lock_file_extension = '.gmailer_processing';

        foreach ($orders as $order_id) {
            $path = $mailer_spool . $order_id;
            if (is_dir($path)) {
                $lock_file = $path . '/' . $lock_file_extension;
                $mtime = filemtime($path);
                $age = time() - $mtime;
                $info_exists = file_exists($path . '/info.txt');
                $is_processing = file_exists($lock_file);
                
                // Stale lock detection (if lock is older than 2 minutes, something probably died)
                if ($is_processing && (time() - filemtime($lock_file) > 120)) {
                    @unlink($lock_file);
                    $is_processing = false;
                    error_log("Spooler: Stale lock removed for Order $order_id. Retrying trigger.");
                }
                
                if (($age > $timeout || $age < 0) && $info_exists && !$is_processing) {
                    // Item is ready AND not already being processed
                    // Create lock file to prevent concurrent execution
                    touch($lock_file);
                    
                    // Use absolute path to gmailer.php
                    $gmailer_path = realpath(__DIR__ . '/../../gmailer.php');
                    
                    // Windows-friendly background execution using WScript.Shell (Reliable & Silent)
                    $log_file = realpath(__DIR__ . '/../../logs') . '/spooler_exec.log';
                    $gmailer_path = realpath(__DIR__ . '/../../gmailer.php');
                    $php_exe = 'C:\UniServerZ\core\php83\php.exe'; // Absolute path for UniServerZ
                    
                    if (!file_exists($php_exe)) $php_exe = 'php';
                    
                    // Construct command with proper Windows quoting for cmd /c
                    $php_cmd = escapeshellarg($php_exe) . ' ' . escapeshellarg($gmailer_path) . ' ' . escapeshellarg($order_id) . ' >> ' . escapeshellarg($log_file) . ' 2>&1';
                    $full_cmd = 'cmd /c "' . $php_cmd . '"';
                    
                    try {
                        if (!class_exists('COM')) {
                             throw new Exception("COM class not found");
                        }
                        $WshShell = new COM("WScript.Shell");
                        $WshShell->Run($full_cmd, 0, false);
                    } catch (Exception $e) {
                        // Fallback to popen
                        $fallback_cmd = 'start /B ' . $full_cmd;
                        pclose(popen($fallback_cmd, "r"));
                    }
                    
                    $triggered[] = $order_id;
                }
            }
        }
        echo json_encode(['status' => 'checked', 'triggered' => $triggered]);
        break;

    case 'print_receipt':
        $order_id = $_GET['order'] ?? '';
        if (!$order_id) die(json_encode(['status'=>'error', 'message'=>'No Order ID']));

        $receipt_src = "../../photos/" . date("Y/m/d") . "/receipts/" . $order_id . ".txt";
        if (!file_exists($receipt_src)) {
            die(json_encode(['status'=>'error', 'message'=>'Receipt file not found']));
        }

        // Logic for Fire Station vs Main Station
        $is_fire = false;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_fire = $_ENV['IP_FIRE'] ?? '192.168.2.126';
        if ($ip === $ip_fire) {
            $is_fire = true;
        } else {
            $content = file_get_contents($receipt_src);
            if (strpos($content, '- FS') !== false || strpos($content, 'Fire Station') !== false) {
                $is_fire = true;
            }
        }

        $dest_folder = $is_fire ? $receipt_hot_fire : $receipt_hot_main;
        $dest_file = $dest_folder . $order_id . ".txt";

        if (copy($receipt_src, $dest_file)) {
            echo json_encode(['status'=>'success', 'message'=>"Receipt printed to " . ($is_fire ? "Fire Station" : "Main Station")]);
        } else {
            echo json_encode(['status'=>'error', 'message'=>'Failed to copy receipt to hot folder']);
        }
        break;

    case 'retry_print':
        $filename = $_GET['file'] ?? '';
        if (!$filename) die(json_encode(['status'=>'error', 'message'=>'No file specified']));
        $parts = explode('-', $filename); 
        if (count($parts) < 2) die(json_encode(['status'=>'error', 'message'=>'Invalid filename format']));
        $photo_id = $parts[1];
        $raw_path = $base_dir . "/photos/" . $date_path . "/raw/" . $photo_id . ".jpg";
        if (file_exists($raw_path)) {
            if (copy($raw_path, $printer_spool . $filename)) {
                echo json_encode(['status' => 'success', 'message' => "Re-spooled $filename"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Copy failed']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Source RAW file not found']);
        }
        break;

    case 'retry_mail':
        $order_id = $_GET['order_id'] ?? '';
        if (!$order_id) die(json_encode(['status'=>'error', 'message'=>'No Order ID']));
        $archived_path = $email_archive . $order_id;
        $active_path = $mailer_spool . $order_id;
        if (is_dir($archived_path)) {
            if (@rename($archived_path, $active_path)) {
                $cmd = "start /B php ../../gmailer.php \"$order_id\"";
                pclose(popen($cmd, "r"));
                echo json_encode(['status' => 'success', 'message' => 'Restored and triggered']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to move folder']);
            }
        } elseif (is_dir($active_path)) {
            $cmd = "start /B php ../../gmailer.php \"$order_id\"";
            pclose(popen($cmd, "r"));
            echo json_encode(['status' => 'success', 'message' => 'Retriggered']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        }
        break;
}
