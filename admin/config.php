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
// Administration: Manual Importer UI                                  //
// Enable Error Reporting and increase memory/time limits              //
// ------------------------------------------------------------------- //
//*********************************************************************//

ini_set('log_errors', 1);     // Log errors to a file
ini_set('error_log', __DIR__.'/logs/import_php_error.log');

// Load Composer autoloader and .env (if present) so environment vars are available
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    if (class_exists('Dotenv\\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->safeLoad();
        } catch (Exception $e) {
            error_log('Failed to load .env: ' . $e->getMessage());
        }
    }
}

// Create logs directory if it doesn't exist
@mkdir(__DIR__.'/logs', 0777, true);
if (file_put_contents(__DIR__.'/logs/import_post_debug.log', print_r($_POST, true), FILE_APPEND) === false) {
    error_log('Failed to write to import_post_debug.log');
}

$locationName = getenv('LOCATION_NAME') ?: "Hawks Nest";
$locationSlug = getenv('LOCATION_SLUG') ?: "Hawksnest";
$locationEmail = getenv('LOCATION_EMAIL') ?: "hawksnest@alleycatphoto.net";
$locationEmailPass = getenv('LOCATION_EMAIL_PASS') ?: ""; // REQUIRED: Set in .env file
$locationLogo = getenv('LOCATION_LOGO') ?: "/public/assets/images/hawksnest-logo.png";

$timestamp = time();
$dirname = getenv('INCOMING_DIR') ?: "../admin/incoming/";
$date_path = date('Y/m/d');
$images = glob($dirname . "*.[jJ][pP]*");
$dir_base = getenv('PHOTOS_BASE_DIR') ?: '../photos';
$date_path = date('Y/m/d');
$thiscount = 0;

// Load categories
$cat_raw = @file_get_contents(__DIR__ . '/categories.txt');
if ($cat_raw === false) {
    error_log('Failed to load categories.txt');
    echo json_encode(['success' => false, 'message' => 'Failed to load categories']);
    exit;
}
$cat = unserialize($cat_raw);