<?php
//*********************************************************************//
//       _____  .__  .__                 _________         __          //
//      /  _  \ |  | |  |   ____ ___.__. \_   ___ \_____ _/  |_        //
//     /  /_\  \|  | |  | _/ __ <   |  | /    \  \/\__  \\   __\       //
//    /    |    \  |_|  |_\  ___/\___  | \     \____/ __ \|  |         //
//    \____|__  /____/____/\___  > ____|  \______  (____  /__|         //
//            \/               \/\/              \/     \/             //
// *********************** INFORMATION ********************************//
// AlleyCat PhotoStation v2.0.4                                        //
// Author: Paul K. Smith (photos@alleycatphoto.net)                    //
// Date: 12/09/2024                                                    //
// Last Revision 12/21/2024 (PKS)                                      //
// Administration: Manual Importer Processor                           //
// ------------------------------------------------------------------- //
//*********************************************************************//

require_once "config.php";

ini_set('memory_limit', '-1');
set_time_limit(0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
ignore_user_abort();

$timestamp = time();
$dirname = __DIR__ . "/incoming/";
$date_path = date('Y/m/d');
$images = glob($dirname . "*.[jJ][pP]*");
$dir_base = getenv('PHOTOS_BASE_DIR') ?: __DIR__ . '/../photos';
$date_path = date('Y/m/d');
$thiscount = 0;

$photostock_base = getenv('PHOTOSTOCK_BASE_DIR') ?: __DIR__ . "/../photostock";
$photostock = $photostock_base . "/" . date('Y') . " " . getenv('PHOTOSTOCK_SLUG')  . "/" . date('F') . " " . date('Y') . "/" . date('F d, Y');

// Load categories
$cat_raw = @file_get_contents(__DIR__ . '/categories.txt');
if ($cat_raw === false) {
    error_log('Failed to load categories.txt');
    echo json_encode(['success' => false, 'message' => 'Failed to load categories']);
    exit;
}
$cat = unserialize($cat_raw);
ob_start();

if (php_sapi_name() === 'cli') {
    // Running as a command-line process
    $params = json_decode(file_get_contents('import_params.json'), true);
    $token = $params['token'] ?? '';
    $importType = $params['custom_target'] ?? '';
    $importTime = $params['selTime'] ?? '';
    $rangeLabel = timeToRange($importTime); 
    $catName = $cat[$importType] ?? 'Unknown';
    // Set import datetime
    $importDateTime = date('Y') . '-' . date('m') . '-' . date('d') . ' ' . $importTime;
    $importDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $importDateTime);
} else {
    // Running as a web request
    $token = $_POST['token'] ?? '';
    $importType = $_POST['custom_target'] ?? '';
    $importTime = $_POST['selTime'] ?? '';
    $rangeLabel = timeToRange($importTime);

    $catName = $cat[$importType] ?? 'Unknown';
    // Set import datetime
    $importDateTime = date('Y') . '-' . date('m') . '-' . date('d') . ' ' . $importTime;
    $importDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $importDateTime);

    if (empty($token) || empty($importType) || empty($importTime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    // Save parameters for background process
    $params = [
        'token' => $token,
        'custom_target' => $importType,
        'selTime' => $importTime,
    ];
    file_put_contents('import_params.json', json_encode($params));

    // Start the background process
    echo json_encode(['success' => true, 'message' => "Files Succesfully Imported $catName ( " . $importDateTime->format('Y-m-d h:iA') . " )"]);
    ob_flush(); // Flush PHP output buffer

    flush();    // Push to client immediately    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = 'start /B php admin_import_proc.php > NUL';
        pclose(popen($command, 'r'));
    } else {
        $command = 'php admin_import_proc.php > /dev/null 2>&1 &';
        exec($command);
    }

    exit;
}
function timeToRange($time) {
    if (preg_match('/^(\d{1,2}):\d{2}:\d{2}$/', $time, $matches)) {
        $start24 = (int)$matches[1];
        
        // Determine start hour in 12-hour format
        $start12 = $start24 % 12 === 0 ? 12 : $start24 % 12;
        $startPeriod = $start24 < 12 ? 'AM' : 'PM';
        
        // Special case: if it's 12, we force end to be 2PM
        if ($start12 === 12) {
            $end12 = 2;
            $endPeriod = 'PM';
        } else {
            $end24 = $start24 + 2;
            $end12 = $end24 % 12 === 0 ? 12 : $end24 % 12;
            $endPeriod = $end24 < 12 || $end24 === 24 ? 'AM' : 'PM';
        }

        return "{$start12}{$startPeriod}-{$end12}{$endPeriod}";
    }

    return "Invalid time format";
}
// Define paths

$dir_incoming = "incoming/$token/";
$date_path = date('Y/m/d');
$images = glob($dir_incoming . "*.[jJ][pP]*");

if (empty($images)) {
    echo json_encode(['success' => false, 'message' => 'No images found to process.']);
    exit;
}

// Reset progress.json
if (
    file_put_contents("progress.json", json_encode([
        'processed' => 0,
        'total' => count($images),
        'percent' => 0
    ]), LOCK_EX) === false
) {
    error_log('Failed to write to progress.json during reset');
}

if (!file_exists("$dir_base/$date_path")) {
    mkdir("$dir_base/$date_path/raw", 0777, true);
    mkdir("$dir_base/$date_path/numbered", 0777, true);
    mkdir("$dir_base/$date_path/web", 0777, true);
}
// Create necessary directories
if (!file_exists($photostock)) {
    if (!mkdir($photostock, 0777, true) && !is_dir($photostock)) {
        error_log('Failed to create Photostock directory');
        echo json_encode(['success' => false, 'message' => 'Failed to create Photostock directory']);
        exit;
    }
}

// Ensure photos directories exist
$photos_raw_dir = "$dir_base/$date_path/raw";
$photos_numbered_dir = "$dir_base/$date_path/numbered";
$photos_receipts_dir = "$dir_base/$date_path/receipts";

if (!file_exists($photos_raw_dir)) {
    mkdir($photos_raw_dir, 0777, true);
}
if (!file_exists($photos_numbered_dir)) {
    mkdir($photos_numbered_dir, 0777, true);
}
if (!file_exists($photos_receipts_dir)) {
    mkdir($photos_receipts_dir, 0777, true);
}



if (!file_exists($photostock. '/' . $catName)) {
    mkdir($photostock . '/' . $catName, 0777, true);
}

if (!file_exists($photostock . '/' . $catName . '/' . $rangeLabel )) {
    mkdir($photostock . '/' . $catName . '/' . $rangeLabel , 0777, true);
}


// Load and update count file
$countFile = "$dir_base/$date_path/$importType.txt";
if (file_exists($countFile)) {
    $count = (int) file_get_contents($countFile);
} else {
    $count = ltrim($importType, '0')."0000"; // Default starting count
    file_put_contents($countFile, $count);
}

$progressFile = 'progress.json';
$totalFiles = count($images);
$processed = 0;

// Clear previous progress.json or reset its content
file_put_contents("progress.json", json_encode(['processed' => 0, 'total' => $totalFiles]));
ob_flush(); // Flush PHP output buffer
flush();    // Push to client immediately
// Process imagesd
// Function for stroking text
function imagettfstroketext(&$image, $size, $angle, $x, $y, &$textcolor, &$strokecolor, $fontfile, $text, $px)
{
    for ($c1 = ($x - abs($px)); $c1 <= ($x + abs($px)); $c1++) {
        for ($c2 = ($y - abs($px)); $c2 <= ($y + abs($px)); $c2++) {
            imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
        }
    }
    return imagettftext($image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
}
foreach ($images as $image) {
    $mtime = filemtime($image);
    $ctime = filectime($image);
    $difftime = time() - $ctime;

    if ($difftime >= 0) {
        $count++;
        $rawPath = "$dir_base/$date_path/raw/$count.jpg";
        $numberedPath = "$dir_base/$date_path/numbered/$count.jpg";
        $webPath = "$photostock/$catName/$rangeLabel/$count.jpg";

        $firePath_base = getenv('FIRE_PATH_BASE') ?: "../photos/fire";
        $firePath = "$firePath_base/$count.jpg";

        $firePath2_base = getenv('FIRE_PATH2_BASE') ?: "../photos/fire2";
        $firePath2 = "$firePath2_base/$count.jpg";

        // Ensure fire paths exist
        if (!file_exists($firePath_base)) {
            mkdir($firePath_base, 0777, true);
        }
        if (!file_exists($firePath2_base)) {
            mkdir($firePath2_base, 0777, true);
        }

        // Process image
        if (rename($image, $rawPath)) {
            $im = imagecreatefromjpeg($rawPath);
            $exif = exif_read_data($rawPath);

            if ($exif && isset($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 8:
                        $im = imagerotate($im, 90, 0);
                        break;
                    case 3:
                        $im = imagerotate($im, 180, 0);
                        break;
                    case 6:
                        $im = imagerotate($im, -90, 0);
                        break;
                }
            }

            // Create resized images with numbering on smaller images
            $width = imagesx($im);
            $height = imagesy($im);
            $font_path = '../public/assets/fonts/rockeb.ttf';
            $text = $count;

            if ($width >= $height) {
                $thisOrient = "L";
                $virtualImage = imagecreatetruecolor(900, 600);
                imagecopyresampled($virtualImage, $im, 0, 0, 0, 0, 900, 600, $width, $height);
                $font_color = imagecolorallocate($virtualImage, 255, 241, 212);
                $stroke_color = imagecolorallocate($virtualImage, 0, 0, 0);
                imagettfstroketext($virtualImage, 60, 0, (900 / 2) - (strlen($text) * 18), 600 - 30, $font_color, $stroke_color, $font_path, $text, 2);
            } else {
                $thisOrient = "P";
                $virtualImage = imagecreatetruecolor(600, 900);
                imagecopyresampled($virtualImage, $im, 0, 0, 0, 0, 600, 900, $width, $height);
                $font_color = imagecolorallocate($virtualImage, 255, 241, 212);
                $stroke_color = imagecolorallocate($virtualImage, 0, 0, 0);
                imagettfstroketext($virtualImage, 60, 0, (600 / 2) - (strlen($text) * 18), 900 - 30, $font_color, $stroke_color, $font_path, $text, 3);
            }

            // Add watermark
            $stamp = imagecreatefrompng(($width >= $height) ? '../public/assets/images/alley_logo_watermark.png' : '../public/assets/images/alley_logo_watermark_P.png');
            imagecopy($virtualImage, $stamp, imagesx($virtualImage) - imagesx($stamp), imagesy($virtualImage) - imagesy($stamp), 0, 0, imagesx($stamp), imagesy($stamp));

            // Save images
            if (!imagejpeg($virtualImage, $numberedPath, 55)) {
                error_log("Failed to save numbered image: $numberedPath");
            } // Smaller image with numbering
            imagejpeg($im, $webPath, 100); // Larger image without numbering
            imagejpeg($im, $rawPath, 90); // Larger image without numbering
            //imagejpeg($im, $webPath, 80); // Larger image without numbering
            if ($locationSlug === "Hawksnest") {
                imagejpeg($im, $firePath2, 100); // Larger image without numbering
            }
            //Change permissions to read write
            chmod($rawPath, 0777);
            chmod($numberedPath, 0777);
            chmod($webPath, 0777);
            if ($locationSlug === "Hawksnest") {
                chmod($firePath, 0777);
            }
            // Update timestamps
            touch($rawPath, date_timestamp_get($importDateTime));
            touch($numberedPath, date_timestamp_get($importDateTime));
            touch($webPath, date_timestamp_get($importDateTime));
            if ($locationSlug === "Hawksnest") {
                touch($firePath, date_timestamp_get($importDateTime));
            }
            //touch($firePath2, date_timestamp_get($importDateTime));
			unset($exif);


            // Increment processed counter
            $processed++;

            // Update progress.json
            file_put_contents("progress.json", json_encode([
                'processed' => $processed,
                'total' => $totalFiles,
                'percent' => round(($processed / $totalFiles) * 100, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            ob_flush(); // Flush PHP output buffer
            flush();    // Push to client immediately
        }
    }
}

$endtime = time();
// Update final count
file_put_contents($countFile, $count);

// Clean up
rmdir($dir_incoming);

// Respond with success
echo json_encode(['success' => true, 'message' => "$processed files processed successfully $catName at " . $importDateTime->format('Y-m-d h:iA') . "."]);
