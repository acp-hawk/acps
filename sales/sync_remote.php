<?php
/**
 * [WIZARD SYNC v3]
 * Forces synchronization by bypassing local SSL issues.
 */

$remoteUrl = "https://alleycatphoto.net/admin/index.php"; // Hit index.php directly
$csvFile = __DIR__ . "/transactions.csv";

$syncMap = [
    'Hawks Nest' => 'Hawksnest',
    'Moonshine Mountain' => 'Moonshine Mt.',
    'Zip n Slip' => 'ZipnSlip'
];

if (!file_exists($csvFile)) die("ERROR: transactions.csv not found.");

echo "Force-Syncing to $remoteUrl...\n";

$handle = fopen($csvFile, 'r');
fgetcsv($handle);

$entriesFound = 0;
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 5) continue;
    $lName = $syncMap[trim($row[0], " \"")] ?? trim($row[0], " \"");
    if (strtolower($lName) === 'unknown' || empty($lName)) continue;
    
    $type = strtolower(trim($row[3], " \""));
    if ($type !== 'cash') continue;

    $dateRaw = trim($row[1], " \"");
    $amount = (float)str_replace(['$', ','], '', $row[4]);
    if ($amount <= 0) continue;

    // Date Conversion
    $dParts = explode('/', $dateRaw);
    $formattedDate = (count($dParts) === 3) ? str_pad($dParts[0], 2, '0', STR_PAD_LEFT) . str_pad($dParts[1], 2, '0', STR_PAD_LEFT) . $dParts[2] : $dateRaw;

    $params = ['loc' => $lName, 'cash' => $amount, 'date' => $formattedDate, 'silent' => 1];
    $target = $remoteUrl . "?" . http_build_query($params);

    // CURL RITUAL (Bypass SSL)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass local certificate blindness
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "Pushing: $lName ($formattedDate) - ERROR: $error\n";
    } else {
        echo "Pushing: $lName ($formattedDate) - " . (trim($response) ?: "EMPTY_RESPONSE") . "\n";
        $entriesFound++;
    }
    usleep(20000); // Speed: 50 requests per second
}

fclose($handle);
echo "\nRitual Complete. $entriesFound entries finalized. 🩸✨\n";
