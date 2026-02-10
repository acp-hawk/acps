<?php
/**
 * [WIZARD SYNC]
 * Drop this in your local location folder (where transactions.csv lives).
 * It will parse the local CSV and push all Cash totals to the cloud.
 */

$remoteUrl = "https://alleycatphoto.net/admin/";
$csvFile = __DIR__ . "/transactions.csv";

// Local normalization map
$syncMap = [
    'Hawks Nest' => 'Hawksnest',
    'Moonshine Mountain' => 'Moonshine Mt.',
    'Zip n Slip' => 'ZipnSlip'
];

if (!file_exists($csvFile)) {
    die("ERROR: transactions.csv not found locally.");
}

echo "Summoning connection to $remoteUrl...\n";

$handle = fopen($csvFile, 'r');
fgetcsv($handle); // Skip header

$entriesFound = 0;
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 5) continue;
    
    $locRaw = trim($row[0], " \"");
    $lName  = $syncMap[$locRaw] ?? $locRaw;
    
    if (strtolower($lName) === 'unknown' || empty($lName)) continue;

    $dateRaw = trim($row[1], " \"");
    $type = strtolower(trim($row[3], " \""));
    $amountRaw = trim($row[4], " \"");

    if ($type === 'cash') {
        $amount = (float)str_replace(['$', ','], '', $amountRaw);
        if ($amount <= 0) continue;
        
        // Convert local date (m/d/Y or MMDDYYYY) to MMDDYYYY for the endpoint
        $dateParts = explode('/', $dateRaw);
        if (count($dateParts) === 3) {
            $formattedDate = str_pad($dateParts[0], 2, '0', STR_PAD_LEFT) . 
                            str_pad($dateParts[1], 2, '0', STR_PAD_LEFT) . 
                            $dateParts[2];
        } else {
            $formattedDate = $dateRaw;
        }

        $params = [
            'loc'    => $lName,
            'cash'   => $amount,
            'date'   => $formattedDate,
            'silent' => 1
        ];

        $target = $remoteUrl . "?" . http_build_query($params);
        $response = @file_get_contents($target);
        
        echo "Pushing: $lName ($formattedDate) - $$amount ... " . (trim($response) ?: "OK") . "\n";
        $entriesFound++;
        usleep(50000); // 0.05s breathe
    }
}

fclose($handle);
echo "\nRitual Complete. $entriesFound cash entries synchronized. 🩸✨\n";
