<?php
chdir(__DIR__);
$_GET['action'] = 'tick_mailer';

// Debug: Check paths
$date_path_rel = "config/api/../../photos/" . date("Y/m/d") . "/";
$spool_base = $date_path_rel . "spool/";
$mailer_spool = $spool_base . "mailer/";

echo "DEBUG:\n";
echo "  CWD: " . getcwd() . "\n";
echo "  Mailer spool path: $mailer_spool\n";
echo "  Exists: " . (is_dir($mailer_spool) ? "YES" : "NO") . "\n";

if (is_dir($mailer_spool)) {
    $orders = array_diff(scandir($mailer_spool), array('.', '..'));
    echo "  Items in queue: " . count($orders) . "\n";
    foreach ($orders as $item) {
        echo "    - $item\n";
    }
}

echo "\nNow running spooler...\n";
require 'config/api/spooler.php';
