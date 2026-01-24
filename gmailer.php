<?php
//*********************************************************************//
//       _____  .__  .__                 _________         __           //
//      /  _  \ |  | |  |   ____ ___.__. \_   ___ \_____ _/  |_         //
//     /  /_\  \|  | |  | _/ __ <   |  | /    \  \/\__  \\  __\        //
//    /    |    \  |_|  |_\  ___/\___  | \     \____/ __ \|  |         //
//    \____|__  /____/____/\___  > ____|  \______  (____  /__|         //
//            \/               \/\/              \/     \/             //
// *********************** INFORMATION ********************************//
// ACPS90 - AlleyCat PhotoStation v9.0 - GMailer Driver               //
// Author: Paul K. Smith (photos@alleycatphoto.net)                    //
// Date: 01/20/2026                                                     //
//*********************************************************************//

require_once __DIR__ . '/admin/config.php';
$credentialsPath = __DIR__ . '/config/google/credentials.json';
$tokenPath = __DIR__ . '/config/google/token.json';
define('SENDER_EMAIL', 'hawksnest@alleycatphoto.com');

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/gmailer_error.log');
ini_set('memory_limit', '-1');
set_time_limit(0);
ignore_user_abort();

$order_id = $argv[1] ?? null;
if (!$order_id) die("No Order ID provided.\n");

// Define lock file location at the start
$lock_file_extension = '.gmailer_processing';

// --- LOGGING ---
function acp_log_event($orderID, $event) {
    $log_file = __DIR__ . '/logs/cash_orders_event.log';
    $error_file = __DIR__ . '/logs/gmailer_error.log';
    if (!is_dir(dirname($log_file))) @mkdir(dirname($log_file), 0777, true);
    $timestamp = date("Y-m-d H:i:s");
    $log_entry = "{$timestamp} | Order {$orderID} | {$event}\n";
    
    // Write to cash_orders_event.log
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also write to gmailer_error.log for complete audit trail
    file_put_contents($error_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Helper to clean up lock file
function remove_lock_file($spool_path) {
    global $lock_file_extension;
    $lock_file = $spool_path . '/' . $lock_file_extension;
    if (file_exists($lock_file)) {
        @unlink($lock_file);
    }
}

// Log that gmailer was triggered
acp_log_event($order_id, "GMAILER_INIT: Script started with order_id=$order_id");

$base_dir = __DIR__;

// --- PATH DETECTION: Handle date rollover ---
// Try current date first, then yesterday (in case job runs past midnight)
$spool_path = null;
$info_file = null;

// Try today's date
$date_path = date("Y/m/d");
$candidate_path = $base_dir . "/photos/$date_path/spool/mailer/$order_id/";
$candidate_info = $candidate_path . "info.txt";
if (file_exists($candidate_info)) {
    $spool_path = $candidate_path;
    $info_file = $candidate_info;
}

// Try yesterday's date if not found
if (!$spool_path) {
    $yesterday = date("Y/m/d", strtotime('-1 day'));
    $candidate_path = $base_dir . "/photos/$yesterday/spool/mailer/$order_id/";
    $candidate_info = $candidate_path . "info.txt";
    if (file_exists($candidate_info)) {
        $spool_path = $candidate_path;
        $info_file = $candidate_info;
        $date_path = $yesterday;
        acp_log_event($order_id, "PATH_FOUND_YESTERDAY: Using yesterday's date ($yesterday)");
    }
}

// Fall back to old cash_email path if new path doesn't exist (legacy orders)
if (!$spool_path) {
    $spool_path = $base_dir . "/photos/$date_path/cash_email/$order_id/";
    $info_file = $spool_path . "info.txt";
    if (!file_exists($info_file)) {
        // Try yesterday's cash_email too
        $yesterday = date("Y/m/d", strtotime('-1 day'));
        $spool_path = $base_dir . "/photos/$yesterday/cash_email/$order_id/";
        $info_file = $spool_path . "info.txt";
        if (file_exists($info_file)) {
            $date_path = $yesterday;
            acp_log_event($order_id, "PATH_FALLBACK_YESTERDAY: Using legacy cash_email path from yesterday");
        } else {
            acp_log_event($order_id, "PATH_FALLBACK: Using legacy cash_email path");
        }
    }
}

// If still not found, try looking up by email (very old system)
if (!file_exists($info_file)) {
    acp_log_event($order_id, "PATH_ERROR: Order folder not found in spooler or cash_email - checking by email");
    // Try to find it in /emails directory by scanning
    $emails_dir = $base_dir . "/photos/$date_path/emails/";
    if (is_dir($emails_dir)) {
        $dirs = scandir($emails_dir);
        foreach ($dirs as $d) {
            if ($d !== '.' && $d !== '..' && is_dir($emails_dir . $d)) {
                $candidate_info = $emails_dir . $d . "/info.txt";
                if (file_exists($candidate_info)) {
                    $info_content = @file_get_contents($candidate_info);
                    if (stripos($info_content, $order_id) !== false) {
                        $spool_path = $emails_dir . $d . "/";
                        $info_file = $spool_path . "info.txt";
                        acp_log_event($order_id, "PATH_FOUND_IN_EMAILS: Located in $spool_path");
                        break;
                    }
                }
            }
        }
    }
}

// Final check - die if still not found
if (!file_exists($info_file)) {
    acp_log_event($order_id, "GMAILER_FATAL: Order folder not found anywhere for order_id=$order_id");
    // Try to find the spool path to clean up lock file
    $spool_candidates = [
        $base_dir . "/photos/" . date("Y/m/d") . "/spool/mailer/$order_id/",
        $base_dir . "/photos/" . date("Y/m/d", strtotime('-1 day')) . "/spool/mailer/$order_id/"
    ];
    foreach ($spool_candidates as $candidate) {
        remove_lock_file($candidate);
    }
    die("ERROR: Order folder not found for Order #$order_id\n");
}

// Parse info.txt to get customer email
$info_raw = file_get_contents($info_file);
$info_data = json_decode($info_raw, true);
if (!$info_data || !isset($info_data['email'])) {
    // Try old format: email|status|...
    $parts = explode('|', $info_raw);
    $customer_email = trim($parts[0] ?? '');
} else {
    $customer_email = $info_data['email'];
}

if (!$customer_email) {
    acp_log_event($order_id, "GMAILER_FATAL: No customer email found in info.txt");
    remove_lock_file($spool_path);
    die("ERROR: No customer email found\n");
}

acp_log_event($order_id, "PATH_RESOLVED: spool_path=$spool_path, email=$customer_email");

// Archive path always goes to /photos/YYYY/MM/DD/emails/ORDER_ID/
$archive_path = $base_dir . "/photos/$date_path/emails/$order_id/";
acp_log_event($order_id, "ARCHIVE_PATH: $archive_path");


// --- TOKEN MGMT ---
function get_valid_token($credPath, $tokenPath) {
    if (!file_exists($tokenPath)) return null;
    $token = json_decode(file_get_contents($tokenPath), true);
    if (($token['created'] + ($token['expires_in'] ?? 3600) - 60) < time()) {
        $creds = json_decode(file_get_contents($credPath), true)['installed'];
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'refresh_token' => $token['refresh_token'],
            'grant_type' => 'refresh_token'
        ]));
        $res = json_decode(curl_exec($ch), true);
        if (isset($res['access_token'])) {
            $token['access_token'] = $res['access_token'];
            $token['created'] = time();
            file_put_contents($tokenPath, json_encode($token, JSON_PRETTY_PRINT));
        }
    }
    return $token['access_token'];
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
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($resp, true)];
}

// --- WATERMARKING & THUMBNAIL GRID ---
function process_images($folder, $logoPath) {
    global $order_id;
    // Normalize path separators for Windows/Linux compatibility
    $folder = str_replace('\\', '/', $folder);
    if (substr($folder, -1) !== '/') $folder .= '/';
    
    acp_log_event($order_id, "PROCESS_IMAGES_START: folder=$folder, logo=$logoPath");
    
    $files = glob($folder . "*.jpg");
    acp_log_event($order_id, "GLOB_RESULT: Found " . (is_array($files) ? count($files) : 0) . " jpg files");
    
    // Filter out previous preview if it exists
    $files = array_filter($files, function($f) { return basename($f) !== 'preview_grid.jpg'; });
    
    if (empty($files)) {
        // Log but continue - email can be sent without preview grid
        acp_log_event($order_id, "NO_IMAGES_FOUND: No JPG files in $folder");
        error_log("WARNING: No JPG files found in $folder");
        return null;
    }

    acp_log_event($order_id, "WATERMARK_START: Processing " . count($files) . " images");

    // 1. Apply Watermarks (Branding Overlay)
    // Determine which logo path to use
    $logo_to_use = null;
    if (!empty(getenv('LOCATION_LOGO'))) {
        $env_logo = getenv('LOCATION_LOGO');
        if (is_string($env_logo) && file_exists($env_logo)) {
            $logo_to_use = $env_logo;
        }
    }
    if (!$logo_to_use && file_exists($logoPath)) {
        $logo_to_use = $logoPath;
    }
    
    if ($logo_to_use) {
        $stamp = @imagecreatefrompng($logo_to_use);
        if ($stamp) {
            imagealphablending($stamp, true);
            imagesavealpha($stamp, true);
            $sw = imagesx($stamp); $sh = imagesy($stamp);

            foreach ($files as $image) {
                $photo = @imagecreatefromjpeg($image);
                if (!$photo) continue;
                $pw = imagesx($photo); $ph = imagesy($photo);
                
                // Scale logo to ~10% of photo width
                $target_w = max(120, (int)round($pw * 0.10));
                $scale = $target_w / $sw;
                $target_h = (int)round($sh * $scale);
                
                $res_stamp = imagecreatetruecolor($target_w, $target_h);
                imagealphablending($res_stamp, false); 
                imagesavealpha($res_stamp, true);
                imagecopyresampled($res_stamp, $stamp, 0, 0, 0, 0, $target_w, $target_h, $sw, $sh);
                
                // Place bottom-right
                imagecopy($photo, $res_stamp, $pw - $target_w - 40, $ph - $target_h - 40, 0, 0, $target_w, $target_h);
                imagejpeg($photo, $image, 90);
                
            }

        }
    }

    // 2. Generate 600px Thumbnail Grid (3 across, black background)
    $cols = 3;
    $thumb_w = 195; 
    $margin = 5;
    $rows = ceil(count($files) / $cols);
    $grid_h = $rows * ($thumb_w + $margin) + $margin;
    
    $grid = imagecreatetruecolor(600, $grid_h);
    $black = imagecolorallocate($grid, 0, 0, 0); 
    imagefill($grid, 0, 0, $black);

    $index = 0;
    foreach ($files as $image) {
        $src = @imagecreatefromjpeg($image);
        if (!$src) continue;
        
        $r = floor($index / $cols);
        $c = $index % $cols;
        $dx = $margin + ($c * ($thumb_w + $margin));
        $dy = $margin + ($r * ($thumb_w + $margin));
        
        $src_w = imagesx($src); $src_h = imagesy($src);
        $size = min($src_w, $src_h);
        $offX = ($src_w - $size) / 2;
        $offY = ($src_h - $size) / 2;
        
        imagecopyresampled($grid, $src, $dx, $dy, $offX, $offY, $thumb_w, $thumb_w, $size, $size); 
        imagedestroy($src);
        $index++;
    }
    
    $preview_path = $folder . "preview_grid.jpg";
    imagejpeg($grid, $preview_path, 85);
    imagedestroy($grid);
    return $preview_path;
}

// --- DRIVE LOGIC ---
function process_drive($order_id, $folder_path, $token) {
    // Normalize path
    $folder_path = str_replace('\\', '/', $folder_path);
    if (substr($folder_path, -1) !== '/') $folder_path .= '/';
    
    $daily_name = 'ACPS_Photos_' . date("Y-m-d");
    acp_log_event($order_id, "DRIVE_SEARCHING: Looking for folder '$daily_name'");
    $search = google_api_call("https://www.googleapis.com/drive/v3/files?q=" . urlencode("name='$daily_name' and mimeType='application/vnd.google-apps.folder' and trashed=false"), "GET", $token);
    
    if ($search['code'] !== 200) {
        throw new Exception("Google Drive search failed (code {$search['code']}): " . json_encode($search['body']));
    }
    
    $daily_id = $search['body']['files'][0]['id'] ?? null;
    if (!$daily_id) {
        acp_log_event($order_id, "DRIVE_CREATING_DAILY: Creating daily folder '$daily_name'");
        $create = google_api_call("https://www.googleapis.com/drive/v3/files", "POST", $token, ['name' => $daily_name, 'mimeType' => 'application/vnd.google-apps.folder']);
        if ($create['code'] !== 200) {
            throw new Exception("Google Drive daily folder creation failed (code {$create['code']}): " . json_encode($create['body']));
        }
        $daily_id = $create['body']['id'] ?? null;
        if (!$daily_id) {
            throw new Exception("Google Drive daily folder created but no ID returned");
        }
        acp_log_event($order_id, "DRIVE_DAILY_CREATED: Folder ID $daily_id");
    } else {
        acp_log_event($order_id, "DRIVE_DAILY_FOUND: Using existing folder ID $daily_id");
    }
    
    acp_log_event($order_id, "DRIVE_CREATING_ORDER_FOLDER: Creating order folder 'Order_$order_id' under parent $daily_id");
    $create_order = google_api_call("https://www.googleapis.com/drive/v3/files", "POST", $token, ['name' => "Order_$order_id", 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$daily_id]]);
    if ($create_order['code'] !== 200) {
        throw new Exception("Google Drive order folder creation failed (code {$create_order['code']}): " . json_encode($create_order['body']));
    }
    $order_fid = $create_order['body']['id'] ?? null;
    if (!$order_fid) {
        throw new Exception("Google Drive order folder created but no ID returned");
    }
    acp_log_event($order_id, "DRIVE_ORDER_FOLDER_CREATED: Order folder ID $order_fid");

    $files = glob($folder_path . "*.jpg");
    if (!$files) {
        acp_log_event($order_id, "WARNING: No JPG files found in $folder_path");
        $files = [];
    } else {
        acp_log_event($order_id, "DRIVE_UPLOADING_FILES: Found " . count($files) . " files to upload");
    }
    
    foreach ($files as $file) {
        if (basename($file) == 'preview_grid.jpg') {
            acp_log_event($order_id, "DRIVE_SKIP_PREVIEW: Skipping preview_grid.jpg");
            continue;
        }
        
        acp_log_event($order_id, "DRIVE_FILE_UPLOAD_START: Uploading " . basename($file));
        $file_content = @file_get_contents($file);
        if (!$file_content) {
            acp_log_event($order_id, "WARNING: Failed to read file $file");
            continue;
        }
        
        $m_res = google_api_call("https://www.googleapis.com/drive/v3/files", "POST", $token, ['name' => basename($file), 'parents' => [$order_fid]]);
        if ($m_res['code'] !== 200) {
            throw new Exception("Google Drive file create failed for " . basename($file) . " (code {$m_res['code']}): " . json_encode($m_res['body']));
        }
        
        $file_id = $m_res['body']['id'] ?? null;
        if (!$file_id) {
            throw new Exception("Google Drive file created but no ID returned for " . basename($file));
        }
        
        acp_log_event($order_id, "DRIVE_FILE_CREATED: File ID $file_id for " . basename($file));
        
        $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files/$file_id?uploadType=media");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: image/jpeg"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $upload_res = curl_exec($ch);
        $upload_err = curl_error($ch);
        curl_close($ch);
        
        if ($upload_err) {
            throw new Exception("File upload curl error for " . basename($file) . ": $upload_err");
        }
        acp_log_event($order_id, "DRIVE_FILE_UPLOADED: " . basename($file));
    }
    
    acp_log_event($order_id, "DRIVE_SETTING_PERMISSIONS: Making folder readable to anyone");
    $perm_res = google_api_call("https://www.googleapis.com/drive/v3/files/$order_fid/permissions", "POST", $token, ['role' => 'reader', 'type' => 'anyone']);
    if ($perm_res['code'] !== 200) {
        acp_log_event($order_id, "WARNING: Permission setting failed (code {$perm_res['code']}), but continuing");
    }
    
    acp_log_event($order_id, "DRIVE_COMPLETE: All files uploaded to $order_fid");
    return "https://drive.google.com/drive/folders/$order_fid";
}

// --- MAIN EXECUTION ---
acp_log_event($order_id, "TOKEN_RETRIEVAL_STARTING");
$token = get_valid_token($credentialsPath, $tokenPath);
if (!$token) {
    acp_log_event($order_id, "GMAILER_FATAL: Token authentication failed - get_valid_token returned null");
    remove_lock_file($spool_path);
    die("Error: Authentication missing. Run auth_setup.php\n");
}
acp_log_event($order_id, "TOKEN_RETRIEVAL_SUCCESS: Token obtained");
if (!is_file($credentialsPath)) {
    acp_log_event($order_id, "GMAILER_FATAL: Credentials file missing at $credentialsPath");
    remove_lock_file($spool_path);
    die("Error: Credentials file not found\n");
}

// Normalize paths
$spool_path = str_replace('\\', '/', $spool_path);
if (substr($spool_path, -1) !== '/') $spool_path .= '/';

echo "Watermarking images and generating black background preview for Order $order_id...\n";
acp_log_event($order_id, "IMAGE_PROCESSING_STARTING: spool_path=$spool_path");
$brandingLogoPath = getenv('LOCATION_LOGO') ?: (__DIR__ . '/public/assets/images/alley_logo.png');

try {
    $preview_img = process_images($spool_path, $brandingLogoPath);
    
    if ($preview_img) {
        echo "Preview grid created: $preview_img\n";
        acp_log_event($order_id, "PREVIEW_GRID_CREATED: $preview_img");
    } else {
        acp_log_event($order_id, "WARNING: No preview grid created (no images found)");
    }
} catch (Exception $e) {
    acp_log_event($order_id, "IMAGE_PROCESSING_ERROR: " . $e->getMessage());
    die("Image processing failed: " . $e->getMessage() . "\n");
} catch (Throwable $t) {
    acp_log_event($order_id, "IMAGE_PROCESSING_FATAL: " . $t->getMessage());
    die("Fatal image processing error: " . $t->getMessage() . "\n");
}

echo "Uploading to Google Drive...\n";
acp_log_event($order_id, "DRIVE_UPLOAD_STARTING");

try {
    $folder_link = process_drive($order_id, $spool_path, $token);
    if (!$folder_link) {
        throw new Exception("process_drive returned null");
    }
    acp_log_event($order_id, "DRIVE_UPLOAD_SUCCESS: $folder_link");
    echo "Drive upload complete: $folder_link\n";
} catch (Exception $e) {
    acp_log_event($order_id, "DRIVE_UPLOAD_FAILED: " . $e->getMessage());
    die("ERROR: Google Drive upload failed: " . $e->getMessage() . "\n");
}

// --- EMAIL CONSTRUCTION ---
$logo_cid = "logo_img";
$preview_cid = "preview_img";

$copyrightText = "Dear Sir/Madam:\n\nThank you for your purchase from AlleycatPhoto. Enclosed with this correspondence are the digital image files you have acquired, along with this copyright release for your records. This letter confirms that you have purchased and paid in full for the rights to the accompanying photographs. AlleycatPhoto hereby grants you express written permission to use, reproduce, print, and distribute these digital files without limitation for personal or professional purposes. While AlleycatPhoto retains the original copyright ownership of the images, you are authorized to use them freely in any lawful manner you choose, without further obligation or restriction. We sincerely appreciate your business and trust in our work. Please retain this release for your records as proof of usage rights.\n\nSincerely,\nJosh Silva\nPresident\nAlleycatPhoto";

$html = "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Poppins', Arial, sans-serif !important; }
    </style>
</head>
<body style='background-color: #0a0a0a; color: #e0e0e0; font-family: \"Poppins\", sans-serif; margin: 0; padding: 0;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #0a0a0a;'>
        <tr>
            <td align='center' style='padding: 20px;'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color: #141414; border: 1px solid #e70017; border-radius: 12px; overflow: hidden; text-align: left;'>
                    <tr>
                        <td style='padding: 30px; text-align: center; border-bottom: 1px solid #333;'>
                            <img src='cid:$logo_cid' alt='Alley Cat Photo' style='display: block; margin: 0 auto; max-width: 100%; width: 400px;'>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 40px;'>
                            <h1 style='margin: 0; font-size: 32px; color: #fff; font-weight: 800;'>Order #$order_id</h1>
                            <p style='margin: 5px 0 25px 0; color: #888; font-size: 16px;'>" . date('F j, Y') . "</p>
                            
                            <p style='font-size: 18px; line-height: 1.6; color: #bbb; margin-bottom: 25px;'>
                                Thank you for choosing <strong>Alley Cat Photo</strong>! Your digital photos are ready for download. We have processed your images and included a copyright release below.
                            </p>

                            <div style='text-align: center; margin-bottom: 30px;'>
                                <img src='cid:$preview_cid' style='width: 100%; border-radius: 8px; border: 1px solid #333;'>
                            </div>

                            <div style='text-align: center; margin: 30px 0; padding: 30px; border: 2px solid #e70017; border-radius: 8px; background-color: #000000;'>
                                <p style='color: #e4e4e4; font-size: 20px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px;'>Download Your Photos</p>
                                <a href='$folder_link' style='background-color: #e70017; color: #ffffff; text-decoration: none; font-size: 24px; font-weight: 800; padding: 15px 30px; border-radius: 5px; display: inline-block; text-transform: uppercase;'>VIEW MY GALLERY</a>
                                <p style='color: #ffab00; font-size: 18px; text-transform: uppercase; margin-top: 20px; font-weight: bold;'>⚠️ Be sure to copy these! This link will only be available for 7 days.</p>
                            </div>

                            <div style='background-color: #1a1a1a; padding: 25px; border-radius: 8px; border-left: 4px solid #e70017;'>
                                <span style='color: #fff; font-weight: bold; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 15px;'>Legal Copyright Release</span>
                                <div style='color: #999; font-size: 14px; line-height: 1.7;'>
                                    " . nl2br($copyrightText) . "
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 30px; background-color: #0f0f0f; border-top: 1px solid #333; text-align: center;'>
                            <p style='margin: 0; color: #555; font-size: 14px;'>&copy; " . date('Y') . " Alley Cat Photo Station | <a href='https://alleycatphoto.net' style='color: #e70017; text-decoration: none; font-weight: 600;'>alleycatphoto.net</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";

// Build Multipart MIME
try {
    acp_log_event($order_id, "EMAIL_CONSTRUCTION_STARTING");
    
    $boundary = "acps_rel_" . md5(time());
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/related; boundary=\"$boundary\"\r\n";
    acp_log_event($order_id, "EMAIL_HEADERS_BUILT: boundary=$boundary");

    $body_mime = "--$boundary\r\n";
    $body_mime .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
    $body_mime .= $html . "\r\n";
    acp_log_event($order_id, "EMAIL_HTML_ADDED: " . strlen($html) . " bytes");

    // Attach Header Logo CID (Use LOCATION_LOGO from environment or fallback to alley_logo_sm)
    $headerLogoPath = __DIR__ . '/public/assets/images/alley_logo_sm.png';
    if (file_exists($headerLogoPath)) {
        acp_log_event($order_id, "EMAIL_LOGO_ATTACHING: $headerLogoPath");
        $logo_data = file_get_contents($headerLogoPath);
        if (!$logo_data) throw new Exception("Failed to read logo file");
        $body_mime .= "--$boundary\r\n";
        $body_mime .= "Content-Type: image/png; name=\"logo.png\"\r\n";
        $body_mime .= "Content-Transfer-Encoding: base64\r\n";
        $body_mime .= "Content-ID: <$logo_cid>\r\n\r\n";
        $body_mime .= chunk_split(base64_encode($logo_data)) . "\r\n";
        acp_log_event($order_id, "EMAIL_LOGO_ATTACHED: " . strlen($logo_data) . " bytes");
    } else {
        acp_log_event($order_id, "WARNING: Logo file not found at $headerLogoPath");
    }

    // Attach Preview Grid CID
    if (file_exists($preview_img)) {
        acp_log_event($order_id, "EMAIL_PREVIEW_ATTACHING: $preview_img");
        $preview_data = file_get_contents($preview_img);
        if (!$preview_data) throw new Exception("Failed to read preview image");
        $body_mime .= "--$boundary\r\n";
        $body_mime .= "Content-Type: image/jpeg; name=\"preview.jpg\"\r\n";
        $body_mime .= "Content-Transfer-Encoding: base64\r\n";
        $body_mime .= "Content-ID: <$preview_cid>\r\n\r\n";
        $body_mime .= chunk_split(base64_encode($preview_data)) . "\r\n";
        acp_log_event($order_id, "EMAIL_PREVIEW_ATTACHED: " . strlen($preview_data) . " bytes");
    } else {
        acp_log_event($order_id, "WARNING: Preview image not found at $preview_img");
    }
    $body_mime .= "--$boundary--";
    acp_log_event($order_id, "EMAIL_MIME_COMPLETE: " . strlen($body_mime) . " bytes");

    $full_raw = "To: $customer_email\r\nSubject: Your Photos from Alley Cat #$order_id\r\n" . $headers . "\r\n" . $body_mime;
    acp_log_event($order_id, "EMAIL_RAW_BUILT: " . strlen($full_raw) . " bytes");
    
    $encoded_msg = strtr(base64_encode($full_raw), ['+' => '-', '/' => '_']);
    acp_log_event($order_id, "EMAIL_ENCODED: " . strlen($encoded_msg) . " bytes");

    acp_log_event($order_id, "GMAIL_SENDING: Calling Gmail API for $customer_email");

    $res = google_api_call("https://gmail.googleapis.com/gmail/v1/users/me/messages/send", "POST", $token, ['raw' => $encoded_msg]);
    acp_log_event($order_id, "GMAIL_API_RESPONSE: code=" . $res['code']);

    if ($res['code'] == 200) {
        if (!is_dir(dirname($archive_path))) mkdir(dirname($archive_path), 0777, true);
        rename($spool_path, $archive_path);
        remove_lock_file($archive_path);  // Clean up lock file from archived location
        acp_log_event($order_id, "GMAIL_SUCCESS: Email sent to $customer_email - moved to archive");
        echo "SUCCESS: Order $order_id sent with branded watermarks and black-background preview.\n";
    } else {
        $error_detail = json_encode($res['body']);
        acp_log_event($order_id, "GMAIL_ERROR: API returned code {$res['code']}, response: $error_detail");
        file_put_contents($spool_path . "error.log", $error_detail);
        remove_lock_file($spool_path);  // Clean up lock file to allow retry
        echo "ERROR: Check error.log in the order folder. API Response: $error_detail\n";
    }
} catch (Exception $e) {
    acp_log_event($order_id, "EMAIL_CONSTRUCTION_ERROR: " . $e->getMessage());
    file_put_contents($spool_path . "construction_error.log", $e->getMessage() . "\n" . $e->getTraceAsString());
    remove_lock_file($spool_path);  // Clean up lock file to allow retry
    die("ERROR: Email construction failed: " . $e->getMessage() . "\n");
}

