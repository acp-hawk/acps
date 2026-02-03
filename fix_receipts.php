<?php
/**
 * Fix $0.00 and corrupted Receipts using Square QR Generation Log - Version 2
 * (Fixed regex to preserve $ sign)
 */

// Parse the QR log
$qr_log_file = __DIR__ . '/logs/square_qr_generation.log';
$qr_log = file_get_contents($qr_log_file);

$qr_map = []; // email => [OrderID, Amount]
$entries = explode("\n------------------------------------------", $qr_log);

foreach ($entries as $entry) {
    if (preg_match('/QR Generated.*?OrderID:\s*([^\|]+)\s*\|.*?Amount:\s*([^\|]+)\s*\|.*?Email:\s*([^\|]+)\s*\|/', $entry, $m)) {
        $orderID = trim($m[1]);
        $amount = trim($m[2]);
        $email = strtolower(trim($m[3]));
        $qr_map[$email] = ['OrderID' => $orderID, 'Amount' => $amount];
    }
}

echo "Loaded " . count($qr_map) . " QR orders from log.\n\n";

// Find all corrupted or $0.00 receipts
$receipt_dirs = [
    __DIR__ . '/photos/2026/01/24/receipts',
    __DIR__ . '/photos/2026/01/24/spool/completed'
];

$fixed_count = 0;
$fixed_orders = [];

foreach ($receipt_dirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = glob($dir . '/*.txt');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        
        // Check if this needs fixing (either $0.00 or corrupted like $.82)
        if (!preg_match('/SQUARE ORDER:\s*\$?([0-9.]*)\s*PAID/i', $content, $m)) continue;
        
        $current_amount = $m[1];
        
        // Extract email
        if (!preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $content, $m)) continue;
        $email = strtolower($m[1]);
        
        // Check if we have this email in QR map
        if (!isset($qr_map[$email])) {
            continue;
        }
        
        $qr_info = $qr_map[$email];
        $correct_amount = $qr_info['Amount'];
        $qr_order_id = $qr_info['OrderID'];
        
        // Extract current order ID from receipt
        if (!preg_match('/Order #:\s*(\d+)\s*-\s*([A-Z]+)/', $content, $m)) continue;
        $receipt_order_id = $m[1] ?? '';
        $station = $m[2] ?? '';
        
        // Skip if amount is already correct
        if ($current_amount == $correct_amount) {
            continue;
        }
        
        // Fix: Replace SQUARE ORDER line with proper dollar format
        $updated = preg_replace(
            '/SQUARE ORDER:\s*\$?[0-9.]*\s*PAID/i',
            'SQUARE ORDER: $' . $correct_amount . ' PAID',
            $content
        );
        
        // Fix: Replace Order Total line with proper dollar format
        $updated = preg_replace(
            '/Order Total:\s*\$?[0-9.]*\s*$/mi',
            'Order Total: $' . $correct_amount,
            $updated
        );
        
        // Save the fixed receipt
        file_put_contents($file, $updated);
        
        $fixed_count++;
        $fixed_orders[] = [
            'file' => basename($file),
            'receipt_order' => $receipt_order_id,
            'email' => $email,
            'qr_order' => $qr_order_id,
            'amount' => $correct_amount,
            'was' => $current_amount
        ];
        
        echo "[FIXED] " . basename($file) . " - $email => \$" . $correct_amount . " (was: \$" . $current_amount . ")\n";
    }
}

echo "\n========================================\n";
echo "Fixed $fixed_count receipts\n";
echo "========================================\n\n";

foreach ($fixed_orders as $order) {
    echo sprintf("Order #%s (%s) - %s - Amount: \$%s (QR ID: %s)\n", 
        $order['receipt_order'], 
        $order['file'],
        $order['email'],
        $order['amount'],
        $order['qr_order']
    );
}
