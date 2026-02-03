<?php
//*********************************************************************//
// AlleyCat PhotoStation : Set Auto Print Status (AJAX)
// Updates the central status file for the Auto Print Toggle.
//*********************************************************************//
require_once("config.php");

header('Content-Type: application/json');

// Define the file path relative to your web application structure
// Assuming config.php is accessible and we can write one level up.
$statusFilePath = realpath(__DIR__ . "/../config/autoprint_status.txt");

if ($statusFilePath === false) {
    echo json_encode(['status' => 'error', 'message' => 'Status file path not resolved.']);
    exit;
}

$newStatus = isset($_POST['status']) ? trim($_POST['status']) : null;

if ($newStatus === null || !in_array($newStatus, ['1', '0'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status value.']);
    exit;
}

try {
    // Ensure the directory exists
    $dir = dirname($statusFilePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    
    // Write the new status ('1' or '0')
    if (@file_put_contents($statusFilePath, $newStatus, LOCK_EX) === false) {
        throw new Exception("Failed to write to status file.");
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Auto print status updated to ' . ($newStatus === '1' ? 'ON' : 'OFF'),
        'value' => (int)$newStatus
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}