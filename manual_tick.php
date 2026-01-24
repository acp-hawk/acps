<?php
chdir(__DIR__);

// Manual tick_mailer logic with debug
$date_path_rel = "photos/" . date("Y/m/d") . "/";
$spool_base = $date_path_rel . "spool/";
$mailer_spool = $spool_base . "mailer/";

$orders = array_diff(scandir($mailer_spool), array('.', '..'));
$triggered = [];
$timeout = 2;

echo "Processing " . count($orders) . " items with timeout=$timeout seconds\n";

foreach ($orders as $order_id) {
    $path = $mailer_spool . $order_id;
    if (is_dir($path)) {
        $mtime = filemtime($path);
        $age = time() - $mtime;
        $info_exists = file_exists($path . '/info.txt');
        
        echo "  $order_id: age=$age sec, dir_mtime=$mtime, has_info=$info_exists";
        
        if ($age > $timeout || $age < 0) {
            if ($info_exists) {
                echo " → TRIGGER!\n";
                // Would trigger here
                $triggered[] = $order_id;
            } else {
                echo " → NO INFO.TXT\n";
            }
        } else {
            echo " → TOO RECENT (wait " . ($timeout - $age) . " sec)\n";
        }
    }
}

echo "\nTriggered: " . count($triggered) . "\n";
foreach ($triggered as $t) {
    echo "  - $t\n";
}
