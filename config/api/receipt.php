<?php
// Gemicunt API - Receipt Details Endpoint
// Returns raw content of a specific receipt

header('Content-Type: application/json');

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    echo json_encode(['status' => 'error', 'message' => 'No file specified']);
    exit;
}

// Security: Prevent traversal
$filename = basename($filename);

try {
    $baseDir = realpath(__DIR__ . '/../../photos');
    $date_path = date('Y/m/d');
    $receiptsDir = rtrim($baseDir, '/') . '/' . $date_path . '/receipts';
    $filePath = $receiptsDir . '/' . $filename;

    if (!file_exists($filePath)) {
        throw new Exception("File not found.");
    }

    $content = file_get_contents($filePath);
    
    echo json_encode([
        'status' => 'ok',
        'file'   => $filename,
        'content' => $content
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
