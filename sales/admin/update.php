<?php
// Simple endpoint to receive report updates and maintain acps_transactions.csv
// Expected query params: date, cash, credit, cash_count, credit_count, location

header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
$cash = isset($_GET['cash']) ? floatval($_GET['cash']) : 0.0;
$credit = isset($_GET['credit']) ? floatval($_GET['credit']) : 0.0;
$cash_count = isset($_GET['cash_count']) ? intval($_GET['cash_count']) : 0;
$credit_count = isset($_GET['credit_count']) ? intval($_GET['credit_count']) : 0;
$location = $_GET['location'] ?? 'UNKNOWN';

$csvFile = __DIR__ . '/acps_transactions.csv';
$rows = [];
if (file_exists($csvFile)) {
    if (($h = fopen($csvFile, 'r')) !== false) {
        $hdr = fgetcsv($h);
        while (($r = fgetcsv($h)) !== false) $rows[] = $r;
        fclose($h);
    }
}

// Remove existing matching row for location+date
$rows = array_values(array_filter($rows, function($r) use ($location, $date) {
    return !(trim($r[0]) === $location && trim($r[1]) === $date);
}));

// Append new row
$rows[] = [$location, $date, number_format($cash, 2, '.', ''), number_format($credit, 2, '.', ''), $cash_count, $credit_count];

// Write back
if (($h = fopen($csvFile, 'w')) !== false) {
    fputcsv($h, ['Location', 'Date', 'Cash', 'Credit', 'Cash_Count', 'Credit_Count']);
    foreach ($rows as $r) fputcsv($h, $r);
    fclose($h);
    echo json_encode(['status' => 'ok']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unable to write file']);
