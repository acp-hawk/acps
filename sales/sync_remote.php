<?php
/**
 * [WIZARD STEALTH SYNC v5]
 * Uses human-emulated GET requests.
 * Designed to bypass Network Solutions/OpenResty 403 blocks.
 */

$remoteUrl = "https://alleycatphoto.net/admin/index.php";
$csvFile = __DIR__ . "/transactions.csv";

$syncMap = [
    'Hawks Nest' => 'Hawksnest',
    'Moonshine Mountain' => 'Moonshine Mt.',
    'Zip n Slip' => 'ZipnSlip'
];

if (!file_exists($csvFile)) die("ERROR: transactions.csv not found.");

echo "Stealth Syncing to $remoteUrl...\n";
echo "Applying human-emulation delay to bypass firewall locks...\n\n";

$handle = fopen($csvFile, 'r');
fgetcsv($handle);

$entriesFound = 0;
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 5) continue;
    $lRaw = trim($row[0], " \"");
    $lName = $syncMap[$lRaw] ?? $lRaw;
    if (strtolower($lName) === 'unknown' || empty($lName)) continue;
    
    $type = strtolower(trim($row[3], " \""));
    if ($type !== 'cash') continue;

    $dateRaw = trim($row[1], " \"");
    $amount = (float)str_replace(['$', ','], '', $row[4]);
    if ($amount <= 0) continue;

    // Standardize Date for the endpoint
    $dParts = explode('/', $dateRaw);
    $formattedDate = (count($dParts) === 3) ? str_pad($dParts[0], 2, '0', STR_PAD_LEFT) . str_pad($dParts[1], 2, '0', STR_PAD_LEFT) . $dParts[2] : $dateRaw;

    $params = [
        'loc'    => $lName,
        'cash'   => $amount,
        'date'   => $formattedDate,
        'silent' => 1,
        'v'      => uniqid() // Add a unique cache-buster
    ];

    $target = $remoteUrl . "?" . http_build_query($params);

    // STEALTH GET RITUAL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Mimic a Chrome Browser
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36");
    curl_setopt($ch, CURLOPT_REFERER, "https://alleycatphoto.net/admin/");
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isOk = (strpos($response, 'WIZARD_ACK') !== false || $httpCode == 200);
    $statusIcon = $isOk ? "✓" : "✗";
    
    echo "[$statusIcon] Pushing: $lName ($formattedDate) - $$amount ... " . ($isOk ? "Success" : "FAILED [CODE $httpCode]") . "\n";
    
    if ($isOk) $entriesFound++;

    // Randomized Human Delay (0.5 to 1.5 seconds)
    usleep(rand(500000, 1500000)); 
}

fclose($handle);
echo "\nRitual Complete. $entriesFound entries successfully channeled. 🩸✨\n";
