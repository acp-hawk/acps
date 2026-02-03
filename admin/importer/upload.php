<?php
//*********************************************************************//
//       _____  .__  .__                 _________         __          //
//      /  _  \ |  | |  |   ____ ___.__. \_   ___ \_____ _/  |_        //
//     /  /_\  \|  | |  | _/ __ <   |  | /    \  \/\__  \\   __\       //
//    /    |    \  |_|  |_\  ___/\___  | \     \____/ __ \|  |         //
//    \____|__  /____/____/\___  > ____|  \______  (____  /__|         //
//            \/               \/\/              \/     \/             //
// *********************** INFORMATION ********************************//
// AlleyCat PhotoStation v2.0.1                                        //
// Author: Paul K. Smith (paul.kelso.smith@gmail.com)                  //
// Date: 01/13/2021                                                    //
// Last Revision 01/14/2021 (PKS)                                      //
// Administration: Manual Importer Upload Functions                                  //
// Enable Error Reporting and increase memory/time limits              //
// error_reporting(E_ALL);                                             //
// ini_set('display_errors', 1);                                       //
//*********************************************************************//

ini_set('log_errors', 1);     // Log errors to a file
ini_set('error_log', __DIR__ . '/../logs/upload_php_error.log');

ini_set('memory_limit', '-1');
set_time_limit(0);
$thiscount=0;

$timestamp = time();
$token=md5('unique_salt' . $timestamp);

header('Content-type:application/json;charset=utf-8');

try {
    if (
        !isset($_FILES['file']['error']) ||
        is_array($_FILES['file']['error'])
    ) {
        throw new RuntimeException('Invalid parameters.');
    }

    switch ($_FILES['file']['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }
    $timestamp = time();
    $token=md5('unique_salt' . $timestamp);
    
    //$filepath = sprintf('files/%s_%s', uniqid(), $_FILES['file']['name']);
    
    $targetFolder = __DIR__ . '/../incoming/' . $_POST['token'];

    if (!file_exists($targetFolder)) {
		mkdir($targetFolder, 0777, true);
	}
    
    $filepath = $targetFolder . '/' . $_FILES['file']['name'];
    if (!move_uploaded_file(
        $_FILES['file']['tmp_name'],
        $filepath
    )) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    // All good, send the response
    echo json_encode([
        'status' => 'ok',
        'path' => $filepath
    ]);

} catch (RuntimeException $e) {
	// Something went wrong, send the err message as JSON
	http_response_code(400);

	echo json_encode([
		'status' => 'error',
		'message' => $e->getMessage()
	]);
}