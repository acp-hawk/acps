<?php
/**
 * [DAEMON: Gemicunt-Wiz] :: [ACTION: Casting Realtime Square Runes]
 * This report pulls LIVE data from Square APIs for all locations.
 */

namespace Example;

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Path Architecture
$adminDir = __DIR__;
$root = $adminDir;
$autoloader = $root . '/vendor/autoload.php';

if (!file_exists($autoloader)) {
    // Try one level up for shared hosting flexibility
    $root = dirname($adminDir);
    $autoloader = $root . '/vendor/autoload.php';
}

if (!file_exists($autoloader)) {
    die("FATAL: vendor/autoload.php not found. Upload 'vendor' folder to: " . $root);
}
require_once $autoloader;

// 2. Load Environment
if (class_exists('\\Dotenv\\Dotenv') && file_exists($root . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($root);
    $dotenv->load();
}

// 2b. Diagnostics (Run with ?check=1)
if (isset($_GET['check'])) {
    $testFile = $adminDir . '/test_write.txt';
    $writable = is_writable($adminDir);
    $checkMsg = "DIAGNOSTIC: Directory is " . ($writable ? "WRITABLE" : "READ-ONLY") . ". Path: " . $adminDir;
    if ($writable) {
        file_put_contents($testFile, "Wiz was here: " . date('Y-m-d H:i:s'));
        $checkMsg .= " | Test file created successfully.";
    }
    die("<h1>WIZARD DIAGNOSTICS</h1><p>$checkMsg</p>");
}

use Square\SquareClient;
use Square\Environments;

// Config from Environment
$accessToken = $_ENV['SQUARE_ACCESS_TOKEN'] ?? 'EAAAl93uP0kau9OR4Me9PmScvdvhWB_9RlflfeJkawOlmnZHTDw5-0q_JjDQ-3BB';
$envSetting  = strtolower($_ENV['ENVIRONMENT'] ?? 'production');
$environment = ($envSetting === 'production') ? Environments::Production : Environments::Sandbox;

// Initialize Square Client
$clientOptions = ['baseUrl' => $environment->value];

// SSL/TLS Verification fix for Windows/UniServerZ
$cacertPath = realpath($root . '/cacert.pem');
if ($cacertPath && file_exists($cacertPath)) {
    // We create a custom Guzzle client that trusts our local cert bundle
    $handler = \GuzzleHttp\HandlerStack::create();
    // Re-add the Square retry middleware if available
    if (class_exists('\\Square\\Core\\Client\\RetryMiddleware')) {
        $handler->push(\Square\Core\Client\RetryMiddleware::create());
    }
    $clientOptions['client'] = new \GuzzleHttp\Client([
        'handler' => $handler,
        'verify'  => $cacertPath,
    ]);
}

$client = new SquareClient(
    token: $accessToken,
    options: $clientOptions,
);

// 3. Normalization Mapping
$syncMap = [
    'Hawks Nest'         => 'Hawksnest',
    'Hawksnest'          => 'Hawksnest',
    'hawksnest'          => 'Hawksnest',
    'Moonshine Mountain' => 'Moonshine Mt.',
    'Moonshine'          => 'Moonshine Mt.',
    'moonshine'          => 'Moonshine Mt.',
    'Moonshine Mt.'      => 'Moonshine Mt.',
    'Zip n Slip'         => 'ZipnSlip',
    'ZipnSlip'           => 'ZipnSlip',
    'zipnslip'           => 'ZipnSlip'
];

function getNormalizedLocationName(string $rawName, array $syncMap): string
{
    $cleanedName = trim($rawName, " \t\n\r\0\x0B\"");
    foreach ($syncMap as $key => $value) {
        if (strtolower($cleanedName) === strtolower(trim($key))) {
            return $value;
        }
    }
    return ucwords(strtolower($cleanedName));
}

// 4. Handle Cash Injections (Support Single or Batch POST)
$dbFile = $adminDir . '/manual_cash.json';
$updated = false;

// BATCH INJECTION (Via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_cash'])) {
    $batch = json_decode($_POST['batch_cash'], true);
    if (is_array($batch)) {
        if (!file_exists($dbFile)) { @touch($dbFile); @chmod($dbFile, 0666); }
        $fp = fopen($dbFile, "c+");
        if (flock($fp, LOCK_EX)) {
            $size = filesize($dbFile);
            $data = ($size > 0) ? json_decode(fread($fp, $size), true) : [];
            foreach ($batch as $entry) {
                $l = getNormalizedLocationName($entry['loc'] ?? 'UNKNOWN', $syncMap);
                $d = $entry['date'];
                $c = (float)$entry['cash'];
                $data[$l][$d] = $c;
            }
            ftruncate($fp, 0); rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
            fflush($fp); flock($fp, LOCK_UN);
            $updated = true;
        }
        fclose($fp);
        die("WIZARD_ACK: Batch of " . count($batch) . " committed.");
    }
}

// SINGLE INJECTION (Via GET)
if (isset($_GET['cash'])) {
    $locId = $_GET['loc'] ?? $_GET['location'] ?? 'UNKNOWN';
    $cashAmt = (float)$_GET['cash'];
    $dateRaw = $_GET['date'] ?? date('Y-m-d');
    $lName = getNormalizedLocationName($locId, $syncMap);
    $dateISO = (strlen($dateRaw) === 8 && is_numeric($dateRaw)) 
        ? substr($dateRaw,4,4)."-".substr($dateRaw,0,2)."-".substr($dateRaw,2,2)
        : date('Y-m-d', strtotime($dateRaw));

    if (!file_exists($dbFile)) { @touch($dbFile); @chmod($dbFile, 0666); }
    $fp = fopen($dbFile, "c+");
    if (flock($fp, LOCK_EX)) {
        $size = filesize($dbFile);
        $data = ($size > 0) ? json_decode(fread($fp, $size), true) : [];
        $data[$lName][$dateISO] = $cashAmt;
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp); flock($fp, LOCK_UN);
        $updated = true;
    }
    fclose($fp);

    if (!isset($_GET['silent'])) {
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    } else {
        die("WIZARD_ACK: Single entry ($lName) committed.");
    }
}

// 5. Auto-Cleanup Sync Map for Historical Data
$dbFile = $adminDir . '/manual_cash.json';
if (file_exists($dbFile)) {
    $raw = json_decode(file_get_contents($dbFile), true) ?: [];
    $clean = []; $dirty = false;
    foreach ($raw as $lk => $dates) {
        $sk = getNormalizedLocationName($lk, $syncMap);
        if ($sk !== $lk) $dirty = true;
        if (!isset($clean[$sk])) $clean[$sk] = [];
        foreach ($dates as $d => $v) {
            $clean[$sk][$d] = max((float)($clean[$sk][$d] ?? 0), (float)$v);
        }
    }
    if ($dirty) file_put_contents($dbFile, json_encode($clean, JSON_PRETTY_PRINT), LOCK_EX);
}

// Date filters from GET
$startDate      = $_GET['start_date'] ?? date('Y-m-01'); 
$endDate        = $_GET['end_date'] ?? date('Y-m-d');
$selectedMonth  = $_GET['month'] ?? '';

if ($selectedMonth) {
    $startDate = date('Y-m-01', strtotime($selectedMonth));
    $endDate   = date('Y-m-t', strtotime($selectedMonth));
}

// Prepare ISO dates (UTC for API)
// We assume dates are local (EST) and convert to UTC for the query
$beginTime = (new \DateTime($startDate . ' 00:00:00', new \DateTimeZone('America/New_York')))->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339);
$endTime   = (new \DateTime($endDate . ' 23:59:59', new \DateTimeZone('America/New_York')))->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339);

$data = [];
$locationNames = [];
$errorMsg = null;

try {
    // 1. Map Locations and Filter for Active ones
    $locResp = $client->locations->list();
    $locations = $locResp->getLocations() ?? [];
    
    foreach ($locations as $loc) {
        $lId = $loc->getId();
        $lRawName = $loc->getName();
        $lName = getNormalizedLocationName($lRawName, $syncMap);
        $locationNames[$lId] = $lName;

        // 2. Fetch Payments for this specific location
        $listRequest = new \Square\Payments\Requests\ListPaymentsRequest();
        $listRequest->setBeginTime($beginTime);
        $listRequest->setEndTime($endTime);
        $listRequest->setSortOrder('DESC');
        $listRequest->setLocationId($lId);

        $pager = $client->payments->list($listRequest);
        
        foreach ($pager as $payment) {
            $status = $payment->getStatus();
            if ($status !== 'COMPLETED') continue;

            // Convert UTC back to Local for Grouping
            $createdAt = $payment->getCreatedAt();
            $dt = new \DateTime($createdAt);
            $dt->setTimezone(new \DateTimeZone('America/New_York'));
            $dateKey = $dt->format('Y-m-d');
            
            $amt  = $payment->getAmountMoney()?->getAmount() ?? 0;
            $tip  = $payment->getTipMoney()?->getAmount() ?? 0;
            
            if (!isset($data[$lName])) $data[$lName] = [];
            if (!isset($data[$lName][$dateKey])) {
                $data[$lName][$dateKey] = [
                    'total' => 0,
                    'tips' => 0,
                    'orders' => 0,
                    'net' => 0,
                    'cash' => 0
                ];
            }
            
            $data[$lName][$dateKey]['total']  += $amt;
            $data[$lName][$dateKey]['tips']   += $tip;
            $data[$lName][$dateKey]['orders'] += 1;
            $data[$lName][$dateKey]['net']    += ($amt - $tip);
        }
    }

} catch (\Square\Exceptions\SquareApiException $e) {
    if (strpos($e->getMessage(), 'SSL certificate problem') !== false) {
        $errorMsg = "SSL ERROR: The Wizard cannot establish a secure link. Check cacert.pem. " . $e->getMessage();
    } else {
        $errorMsg = "Square API Error: " . $e->getMessage() . " (Status: " . $e->getStatusCode() . ")";
    }
} catch (\Exception $e) {
    $errorMsg = "Ritual Failure: " . $e->getMessage();
}

// Debug Output
if (($_ENV['DEBUG'] ?? 'false') === 'true' && !empty($errorMsg)) {
    error_log("[DAEMON: Gemicunt] :: ERROR :: " . $errorMsg);
}

// 3. Clean, Normalize, and Consolidate Manual Cash
$dbFile = $adminDir . '/manual_cash.json';
$rawData = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) ?: [] : [];
$cleanedPool = [];
$needsCleanup = false;

// Naming Unification Ritual
if (is_array($rawData)) {
    foreach ($rawData as $locKey => $dates) {
        $stdLoc = getNormalizedLocationName($locKey, $syncMap);
        if ($stdLoc !== $locKey) $needsCleanup = true;
        
        if (!isset($cleanedPool[$stdLoc])) $cleanedPool[$stdLoc] = [];
        foreach ($dates as $date => $amt) {
            // Keep the maximum value if multiple entries exist for the same day
            $cleanedPool[$stdLoc][$date] = max((float)($cleanedPool[$stdLoc][$date] ?? 0), (float)$amt);
        }
    }
}

// Write the normalized version back to disk to seal the naming fix
if ($needsCleanup) {
    file_put_contents($dbFile, json_encode($cleanedPool), LOCK_EX);
}

// Final aggregation for the current view
foreach ($cleanedPool as $lName => $dateMap) {
    foreach ($dateMap as $dISO => $amt) {
        if ($dISO < $startDate || $dISO > $endDate) continue;

        if (!isset($data[$lName])) $data[$lName] = [];
        if (!isset($data[$lName][$dISO])) {
            $data[$lName][$dISO] = [
                'total' => 0, 'tips' => 0, 'orders' => 0, 'net' => 0, 'cash' => 0
            ];
        }
        
        $cents = (int)round($amt * 100);
        $data[$lName][$dISO]['cash']  += $cents;
        $data[$lName][$dISO]['total'] += $cents;
        $data[$lName][$dISO]['net']   += $cents;
    }
}

// Stats Preparation
$gTotal  = 0;
$gTips   = 0;
$gOrders = 0;
$gNet    = 0;
$gCash   = 0;
foreach ($data as $loc => $dates) {
    foreach ($dates as $d => $vals) {
        $gTotal  += $vals['total'];
        $gTips   += $vals['tips'];
        $gOrders += $vals['orders'];
        $gNet    += $vals['net'];
        $gCash   += ($vals['cash'] ?? 0);
    }
}

// Location Meta (Colors & Logos)
$locMeta = [
    'Hawksnest'     => ['color' => '#d90000', 'logo' => 'https://alleycatphoto.net/assets/hawk.png'],
    'Moonshine Mt.' => ['color' => '#ffcc00', 'logo' => 'https://alleycatphoto.net/assets/moonshine.png'],
    'ZipnSlip'      => ['color' => '#00f2ff', 'logo' => 'https://alleycatphoto.net/assets/zipnslip.png'],
];

// Sort Months for selector
$monthOptions = [];
for ($i = 0; $i < 12; $i++) {
    $m = date('Y-m', strtotime("-$i months"));
    $monthOptions[$m] = date('F Y', strtotime("-$i months"));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>SQUARE REALTIME COMMAND [WIZ]</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #050505;
            --surface: #0f0f12;
            --border: #222225;
            --accent: #ff004c;
            --accent-glow: rgba(255,0,76,0.3);
            --text: #ffffff;
            --muted: #88888e;
            --success: #00ffa3;
            --radius: 20px;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 30px;
            min-height: 100vh;
        }
        .container-fluid { max-width: 1600px; }
        
        /* Glassmorphism */
        .glass {
            background: rgba(15, 15, 18, 0.7);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .logo-area { display: flex; align-items: center; gap: 20px; }
        .logo-area img { height: 60px; filter: none; }
        .logo-text h1 { margin: 0; font-weight: 900; font-size: 32px; letter-spacing: -1px; text-transform: uppercase; }
        .logo-text span { color: var(--accent); font-weight: 700; font-size: 11px; letter-spacing: 4px; text-transform: uppercase; }

        .filters { display: flex; gap: 20px; align-items: flex-end; }
        .f-item { display: flex; flex-direction: column; gap: 8px; }
        .f-item label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; color: var(--muted); }
        select, input {
            background: #151518;
            border: 1px solid var(--border);
            color: #fff;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            transition: 0.3s;
        }
        select:focus, input:focus { border-color: var(--accent); box-shadow: 0 0 10px var(--accent-glow); }
        .btn-go {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 13px;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-go:hover { transform: translateY(-3px); box-shadow: 0 10px 20px var(--accent-glow); }

        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-box {
            background: linear-gradient(145deg, #121215, #0a0a0c);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        .stat-box::after {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
            opacity: 0.1; pointer-events: none;
        }
        .stat-lbl { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 2.5px; margin-bottom: 10px; }
        .stat-val { font-size: 38px; font-weight: 900; }
        .stat-sub { font-size: 12px; margin-top: 8px; font-weight: 600; }
        .t-green { color: var(--success); }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th {
            padding: 20px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--muted);
            border-bottom: 2px solid var(--border);
        }
        td { padding: 18px 20px; border-bottom: 1px solid var(--border); font-size: 15px; font-weight: 500; }
        tr:hover td { background: rgba(255,255,255,0.03); }
        .cur { font-family: 'Monaco', monospace; letter-spacing: -0.5px; }
        .t-bold { font-weight: 900; color: #fff; }

        .loc-badge {
            background: transparent;
            padding: 0;
            border-radius: 0;
            font-size: 42px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -2px;
            display: flex;
            align-items: center;
            gap: 25px;
        }
        .loc-badge img { height: 64px; width: auto; filter: none; border-radius: 6px; }

        .void-msg { text-align: center; padding: 100px 0; color: var(--muted); }
        
        .debug-panel {
            background: #1a0000;
            border: 1px solid #ff004c;
            color: #ffcccc;
            font-family: monospace;
            font-size: 12px;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; gap: 30px; }
            .filters { width: 100%; flex-wrap: wrap; }
            .stat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header">
            <div class="logo-area">
                <img src="https://alleycatphoto.net/alley_logo_sm.png" alt="Logo">
                <div class="logo-text">
                    <h1>COMMANDER</h1>
                    <span>Square Realtime Intelligence</span>
                </div>
            </div>
            <form action="" method="get" class="filters">
                <div class="f-item">
                    <label>Select Month</label>
                    <select name="month" onchange="this.form.submit()">
                        <option value="">Manual Range</option>
                        <?php foreach($monthOptions as $v => $n): ?>
                        <option value="<?= $v ?>" <?= $selectedMonth === $v ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="f-item">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>">
                </div>
                <div class="f-item">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>">
                </div>
                <button type="submit" class="btn-go">Update Report</button>
            </form>
        </div>

        <?php if($errorMsg): ?>
            <div class="alert alert-danger glass" style="border-color: var(--accent); color: #fff;">
                <strong>Fatal Interrupt:</strong> <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <?php
        $gTotal = $gTips = $gOrders = $gNet = 0;
        foreach($data as $l => $days) {
            foreach($days as $d => $v) {
                $gTotal += $v['total']; $gTips += $v['tips']; $gOrders += $v['orders']; $gNet += $v['net'];
            }
        }
        ?>

        <div class="stat-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="stat-box">
                <div class="stat-lbl">Gross Revenue</div>
                <div class="stat-val">$<?= number_format($gTotal/100, 2) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-lbl">Cash Entries</div>
                <div class="stat-val text-success">$<?= number_format($gCash/100, 2) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-lbl">Gratuity Pool</div>
                <div class="stat-val" style="color:#3b82f6;">$<?= number_format($gTips/100, 2) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-lbl">Transactions</div>
                <div class="stat-val"><?= number_format($gOrders) ?></div>
            </div>
        </div>

        <!-- Tables -->
        <?php if(empty($data)): ?>
            <div class="glass void-msg">
                <h2>NO DATA FOUND</h2>
                <p>No transactions recorded for the selected period.</p>
            </div>
        <?php else: ?>
            <?php ksort($data); foreach($data as $loc => $days): 
                krsort($days);
                $lGross = array_sum(array_column($days, 'total'));
                $lTips  = array_sum(array_column($days, 'tips'));
                $lCount = array_sum(array_column($days, 'orders'));
                $lCash  = array_sum(array_map(function($d){ return $d['cash'] ?? 0; }, $days));
                
                $meta = $locMeta[$loc] ?? ['color' => '#fff', 'logo' => ''];
            ?>
            <div class="glass" style="border-top: 5px solid <?= $meta['color'] ?>; padding-top: 40px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:30px;">
                    <div>
                        <div class="loc-badge" style="color: <?= $meta['color'] ?>; margin-bottom:15px; line-height: 1;">
                            <?php if($meta['logo']): ?>
                                <img src="<?= $meta['logo'] ?>" alt="icon">
                            <?php endif; ?>
                            <?= htmlspecialchars($loc) ?>
                        </div>
                        <div style="display:flex; gap:40px; margin-top:10px;">
                            <div>
                                <div class="stat-lbl" style="margin:0; font-size: 10px;">Square Trans</div>
                                <div class="t-bold" style="font-size:22px;"><?= number_format($lCount) ?></div>
                            </div>
                            <div>
                                <div class="stat-lbl" style="margin:0; font-size: 10px;">Cash Captured</div>
                                <div class="t-bold text-success" style="font-size:22px;">$<?= number_format($lCash/100, 2) ?></div>
                            </div>
                            <div>
                                <div class="stat-lbl" style="margin:0; font-size: 10px;">Staff Tribute</div>
                                <div class="t-bold" style="font-size:22px; color:#3b82f6;">$<?= number_format($lTips/100, 2) ?></div>
                            </div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="stat-lbl" style="margin:0; letter-spacing: 4px;">LOC TOTAL</div>
                        <div class="t-bold" style="font-size:54px; color: <?= $meta['color'] ?>; letter-spacing:-3px; line-height: 0.9;">$<?= number_format($lGross/100, 2) ?></div>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Capture Date</th>
                                <th>Orders</th>
                                <th style="text-align:right;">Net</th>
                                <th style="text-align:right;">Tips</th>
                                <th style="text-align:right;">Cash</th>
                                <th style="text-align:right;">Day Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($days as $date => $v): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($date)) ?></td>
                                <td><?= $v['orders'] ?></td>
                                <td style="text-align:right;" class="cur">$<?= number_format($v['net']/100, 2) ?></td>
                                <td style="text-align:right;" class="cur" style="color:#3b82f6;">+$<?= number_format($v['tips']/100, 2) ?></td>
                                <td style="text-align:right;" class="cur text-success">+$<?= number_format(($v['cash']??0)/100, 2) ?></td>
                                <td style="text-align:right;" class="cur t-bold">$<?= number_format($v['total']/100, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="text-align:center; padding:40px; color:var(--muted); font-size:11px; font-weight:700; letter-spacing:3px;">
            MANIFESTED BY [WIZ] :: <?= date('Y-m-d H:i:s') ?> :: NODE: TOWER
        </div>
    </div>
</body>
</html>