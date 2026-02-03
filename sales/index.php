<?php
// Load .env if not already loaded
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
    }
}
// Map location names to display names
$locMap = [
    "Hawksnest" => "Hawks Nest",
    "ZipnSlip" => "Zip n Slip",
    "Moonshine" => "Moonshine Mountain",
    // Add more as needed
];

// Read current transactions CSV (daily aggregates or legacy individual)
$csvFile = __DIR__ . '/transactions.csv';
$data = [];
if (file_exists($csvFile)) {
    $handle = fopen($csvFile, 'r');
    $header = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $locName = trim($row[0], " \t\n\r\0\x0B\"");
        $locId = $locMap[$locName] ?? $locName;
        
        if (count($row) == 6) {
            // Legacy format: Location,Time,Order Date,Payment Type,Amount,Order Number
            $date = date('Y-m-d', strtotime(trim($row[1])));
            $type = trim($row[3]);
            $amount = (float)trim($row[4]);
            $orders = 1;
        } else {
            // New format: Location,Order Date,Orders,Payment Type,Amount
            $dateParts = explode('/', trim($row[1]));
            if (count($dateParts) === 3) {
                $date = sprintf('%04d-%02d-%02d', $dateParts[2], $dateParts[0], $dateParts[1]);
            } else {
                continue;
            }
            $orders = (int)trim($row[2]);
            $type = strtolower(trim($row[3]));
            $amount = (float)str_replace(['$', ','], '', trim($row[4]));
        }
        
        if (!isset($data[$locId])) $data[$locId] = [];
        if (!isset($data[$locId][$date])) $data[$locId][$date] = ['credit' => 0, 'cash' => 0, 'orders' => 0];
        
        if (strtolower($type) === 'cash') {
            $data[$locId][$date]['cash'] += $amount;
        } else {
            $data[$locId][$date]['credit'] += $amount;
        }
        $data[$locId][$date]['orders'] += $orders;
    }
    fclose($handle);
}

  // If requested, perform a full rescan of today's receipts and update transactions.csv
  function scan_receipts_for_date(string $datePath): array {
    $base = realpath(__DIR__ . '/../') ?: __DIR__ . '/..';
    $receiptsDir = $base . '/photos/' . $datePath . '/receipts';
    $result = ['cash_total' => 0.0, 'credit_total' => 0.0, 'cash_count' => 0, 'credit_count' => 0];
    if (!is_dir($receiptsDir)) return $result;

    $files = glob($receiptsDir . '/*.txt');
    foreach ($files as $f) {
      $txt = @file_get_contents($f);
      if ($txt === false) continue;
      $txt = strtoupper($txt);

      // Skip internal emails, VOID/DUE orders, and empty orders
      $ignoreTerms = [
        'PHOTOS@ALLEYCATPHOTO.NET',
        'ZIPNSLIP@ALLEYCATPHOTO.COM',
        'HAWKSNEST@ALLEYCATPHOTO.COM',
        'MOONSHINE@ALLEYCATPHOTO.COM',
        'VOID',
        'DUE'
      ];
      foreach ($ignoreTerms as $term) {
        if (strpos($txt, $term) !== false) continue 2;
      }

      // Check for empty items section (no items between ITEMS ORDERED and closing line)
      if (preg_match('/ITEMS ORDERED:\s*----+\s*----+\s*Check out/is',  $txt)) {
          continue;
      }

      // Find SQUARE (credit) paid
      if (preg_match('/SQUARE\s+ORDER:\s*\$?([0-9]+(?:\.[0-9]{1,2})?)\s+PAID/', $txt, $m)) {
        $amt = (float)$m[1];
        $result['credit_total'] += $amt;
        $result['credit_count'] += 1;
        continue;
      }

      // Find CASH paid (ignore VOID or DUE)
      if (preg_match('/CASH\s+ORDER:\s*\$?([0-9]+(?:\.[0-9]{1,2})?)\s+PAID/', $txt, $m)) {
        $amt = (float)$m[1];
        $result['cash_total'] += $amt;
        $result['cash_count'] += 1;
        continue;
      }
    }
    return $result;
  }

  function write_transactions_csv_for_date(string $csvFile, string $location, string $dateDisplay, array $totals) {
    // Read existing rows, except for this location/date rows (we'll replace)
    $rows = [];
    if (file_exists($csvFile)) {
      if (($h = fopen($csvFile, 'r')) !== false) {
        $header = fgetcsv($h);
        while (($r = fgetcsv($h)) !== false) {
          $rows[] = $r;
        }
        fclose($h);
      }
    }

    // Remove rows that match this location and date
    $rows = array_values(array_filter($rows, function($r) use ($location, $dateDisplay) {
      return !(trim($r[0]) === $location && trim($r[1]) === $dateDisplay);
    }));

    // Append cash and credit rows if present
    if ($totals['cash_count'] > 0 || $totals['cash_total'] > 0) {
      $rows[] = [$location, $dateDisplay, $totals['cash_count'], 'Cash', '$' . number_format($totals['cash_total'], 2)];
    }
    if ($totals['credit_count'] > 0 || $totals['credit_total'] > 0) {
      $rows[] = [$location, $dateDisplay, $totals['credit_count'], 'Credit', '$' . number_format($totals['credit_total'], 2)];
    }

    // Write back
    if (($h = fopen($csvFile, 'w')) !== false) {
      fputcsv($h, ['Location', 'Order Date', 'Orders', 'Payment Type', 'Amount']);
      foreach ($rows as $r) fputcsv($h, $r);
      fclose($h);
      return true;
    }
    return false;
  }

  // Public update method now uses GET
  function post_update_to_master(string $urlBase, string $dateISO, array $totals, string $location) {
    $params = http_build_query([
      'date' => $dateISO,
      'cash' => number_format($totals['cash_total'], 2, '.', ''),
      'credit' => number_format($totals['credit_total'], 2, '.', ''),
      'cash_count' => $totals['cash_count'],
      'credit_count' => $totals['credit_count'],
      'location' => $location
    ]);
    $url = rtrim($urlBase, '/') . '/update.php?' . $params;
    // Use GET request for update
    if (!function_exists('curl_init')) {
      @file_get_contents($url);
      return;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    // Log to file
    $log = __DIR__ . '/update_report.log';
    @file_put_contents($log, date('c') . " POSTED: $url -- resp:" . ($resp ?: '') . " err:" . ($err ?: '') . "\n", FILE_APPEND | LOCK_EX);
  }

  // Handle update action from UI
  if (isset($_GET['action']) && $_GET['action'] === 'update') {
    // Determine today's folder
    $datePath = date('Y/m/d');
    $dateDisplay = date('m/d/Y');
    $dateISO = date('Y-m-d');

    // Location identification (prefer LOCATION_NAME, fallback to LOCATION_SLUG, then UNKNOWN)
    $location = getenv('LOCATION_NAME');
    if ($location && trim($location) !== '') {
      $location = trim($location);
    } else if (getenv('LOCATION_SLUG') && trim(getenv('LOCATION_SLUG')) !== '') {
      $location = trim(getenv('LOCATION_SLUG'));
    } else {
      $location = 'UNKNOWN';
    }

    $totals = scan_receipts_for_date($datePath);

    $csvFile = __DIR__ . '/transactions.csv';
    write_transactions_csv_for_date($csvFile, $location, $dateDisplay, $totals);

    // Post to master endpoint on central server
    post_update_to_master('https://alleycatphoto.net/admin', $dateISO, $totals, $location);

    // Redirect back to clean page
    header('Location: ' . basename(__FILE__));
    exit;
  }
//"Zip n Slip",01/19/2026,3,Cash,$52.00
//"Zip n Slip",01/19/2026,8,Credit,$236.31

  // Handle log action from GET request
  //https://alleycatphoto.net/admin/index.php?action=log&date=2026-01-19&location=Zip n Slip&type=Cash&count=3&amount=52.00
  if (isset($_GET['action']) && $_GET['action'] === 'log') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $location = $_GET['location'] ?? 'UNKNOWN';
    $type = ucfirst(strtolower($_GET['type'] ?? 'Cash'));
    $count = (int)($_GET['count'] ?? 0);
    $amount = (float)($_GET['amount'] ?? 0.0);

    // Convert ISO date (YYYY-MM-DD) to m/d/Y if needed, or keep as is if already in that format
    $dateDisplay = (strpos($date, '-') !== false) ? date('m/d/Y', strtotime($date)) : $date;
    
    $csvFile = __DIR__ . '/transactions.csv';
    $rows = [];
    $found = false;

    if (file_exists($csvFile)) {
      if (($h = fopen($csvFile, 'r')) !== false) {
        $header = fgetcsv($h);
        while (($r = fgetcsv($h)) !== false) {
          if (count($r) < 4) continue;
          // Check if this row matches location, date and payment type
          if (trim($r[0]) === $location && trim($r[1]) === $dateDisplay && strcasecmp(trim($r[3]), $type) === 0) {
            $rows[] = [$location, $dateDisplay, $count, $type, '$' . number_format($amount, 2)];
            $found = true;
          } else {
            $rows[] = $r;
          }
        }
        fclose($h);
      }
    }

    if (!$found) {
      $rows[] = [$location, $dateDisplay, $count, $type, '$' . number_format($amount, 2)];
    }

    // Write back to CSV
    if (($h = fopen($csvFile, 'w')) !== false) {
      fputcsv($h, ['Location', 'Order Date', 'Orders', 'Payment Type', 'Amount']);
      foreach ($rows as $r) fputcsv($h, $r);
      fclose($h);
    }

    header('Location: ' . basename(__FILE__));
    exit;
  }
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ACPS Sales Report</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      :root {
        --bg: #070707;
        --border: #242426;
        --text: #e7e7e7;
        --muted: #a7a7a7;
        --accent: #c81c1c;
        --surface: rgba(18, 18, 20, 0.92);
        --radius: 16px;
      }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        padding: 15px;
        font-family: 'Inter', sans-serif;
        background:
          radial-gradient(1200px 700px at 20% -10%, rgba(200, 28, 28, 0.15), transparent 60%),
          linear-gradient(180deg, #050505 0%, #070707 100%);
        color: var(--text);
        min-height: 100vh;
      }
      .wrap { max-width: 1200px; margin: 0 auto; }
      .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding: 16px 20px;
        background: linear-gradient(#121214eb, #0c0c0deb);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      }
      .brand { display: flex; align-items: center; gap: 15px; }
      .brand img { height: 40px; width: auto; }
      .brand-text .title { font-weight: 900; font-size: 18px; letter-spacing: 0.5px; }
      .brand-text .subtitle { color: var(--muted); font-size: 12px; }
      .back-btn {
        color: var(--text);
        text-decoration: none;
        font-weight: 900;
        font-size: 12px;
        border: 1px solid var(--accent);
        padding: 8px 16px;
        border-radius: 12px;
        background: rgba(200, 28, 28, 0.1);
        transition: all 0.2s;
        white-space: nowrap;
      }
      .back-btn:hover { background: rgba(200, 28, 28, 0.2); transform: translateY(-1px); }

      .filters {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
      }
      input[type="date"], input[type="file"] {
        background: #111;
        border: 1px solid var(--border);
        color: #fff;
        padding: 8px 12px;
        border-radius: 8px;
        outline: none;
        font-family: inherit;
        font-size: 13px;
      }
      .btn {
        background: var(--accent);
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 800;
        cursor: pointer;
        transition: opacity 0.2s;
        font-family: inherit;
        font-size: 12px;
        letter-spacing: 0.4px;
      }
      .btn:hover { opacity: 0.85; }
      .btn:disabled { opacity: 0.5; cursor: not-allowed; }

      .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
      }
      .stat-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      }
      .stat-val { font-size: 28px; font-weight: 900; color: #fff; margin: 5px 0; }
      .stat-label {
        color: var(--muted);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 800;
      }

      .card {
        background: #070707;
        border: none;
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(10px);
        margin-bottom: 20px;
      }
      .card-hd {
        padding: 15px;
        border: none;
        background: #070707;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
      }
      .card-hd h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 900;
        color: var(--accent);
        text-transform: uppercase;
        text-align: center;
      }
      .card-hd .loc-total { font-size: 16px; font-weight: 800; color: #fff; }

      table { width: 100%; border-collapse: collapse; font-size: 12px; }
      th {
        text-align: right;
        padding: 8px 10px;
        color: var(--muted);
        font-weight: 900;
        border-bottom: 1px solid #000000;
        text-transform: uppercase;
        font-size: 14px;
      }
      th:first-child { text-align: left; }
      td { padding: 8px 10px; border-bottom: 1px solid #000000; text-align: center; }
      td:first-child { text-align: left; font-weight: 700; }
      td:last-child { text-align: center; }
      tr:last-child td { border-bottom: none; }

      .col-credit { color: #3b82f6; }
      .col-cash { color: #10b981; }
      .col-total { font-weight: normal; color: #fff; }

      tfoot tr { background: rgba(255, 255, 255, 0.05); font-weight: 900; }

      /* Table row styling */
      tbody tr:nth-child(odd) { background-color: #070707 !important; }
      tbody tr:nth-child(even) { background-color: #242426 !important; }
      tbody tr { color: #fff !important; }
      tbody td:nth-child(4), tbody td:nth-child(7), tbody td:nth-child(10), tbody td:nth-child(11) { color: #27ae60 !important; }
      tbody td:last-child { font-weight: bold; }

      /* Vertical borders */
      th, td { border-right: 1px solid #000000 !important; }
      th:last-child, td:last-child { border-right: none !important; }

      /* Header styling */
      thead th { background-color: #070707 !important; }
      thead tr:first-child th { border-bottom: 1px solid #000000 !important; }

      /* Monthly total rows */
      .monthly-total {
        background-color: #1a1a1a !important;
        border-top: 2px solid #27ae60 !important;
        border-bottom: 1px solid #27ae60 !important;
        font-weight: bold !important;
      }
      .monthly-total td {
        color: #27ae60 !important;
        font-size: 13px !important;
      }

      /* Grand total row */
      .grand-total {
        background-color: #0a2a0a !important;
        border-top: 3px solid #27ae60 !important;
        font-weight: bold !important;
        font-size: 16px !important;
      }
      .grand-total td {
        color: #27ae60 !important;
      }

      /* Override Bootstrap table borders */
      .table thead th {
        border-bottom: 1px solid #000000 !important;
      }

      .table td, .table th {
        border-top: none !important;
        border-bottom: 1px solid #000000 !important;
      }

      .month-summary {
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
      }
      .month-summary h2 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: var(--accent);
      }
      .loc-totals {
        font-size: 14px;
        font-weight: 700;
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
      }
      .loc-totals span {
        display: flex;
        align-items: center;
        gap: 4px;
      }
      .month-table {
        margin-top: 10px;
      }

      .warn {
        border: 1px solid var(--border);
        background: rgba(255, 255, 255, 0.04);
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 16px;
        color: var(--muted);
        font-size: 12px;
      }
      .warn b { color: #fff; }

      .upload-card {
        display: none;
        border: 1px solid rgba(200, 28, 28, 0.45);
        background: rgba(200, 28, 28, 0.08);
      }
      .upload-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 10px;
      }
      .mini {
        padding: 10px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: rgba(0, 0, 0, 0.18);
      }
      .mini .label { color: #fff; font-weight: 900; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; }
      .mini .hint { margin-top: 6px; font-size: 12px; opacity: 0.8; }

      .mobile-summary { display: none; }

      @media (max-width: 768px) {
        body { padding: 10px; }
        .header { flex-direction: column; text-align: center; }
        .brand { flex-direction: column; }
        .filters { width: 100%; flex-direction: column; }
        .filters input, .filters button { width: 100%; }
        .upload-grid { grid-template-columns: 1fr; }

        .month-summary {
          flex-direction: column !important;
          text-align: center !important;
        }
        .loc-totals {
          font-size: 12px !important;
          flex-direction: column !important;
          gap: 4px !important;
        }

        /* Table to cards */
        table, thead, tbody, th, td, tr { display: block; }
        thead tr { position: absolute; top: -9999px; left: -9999px; }
        
        tr {
          border: 1px solid var(--border);
          margin: 0 0 10px;
          border-radius: 12px;
          background: rgba(255, 255, 255, 0.02);
          padding: 12px;
          cursor: pointer;
          transition: background 0.2s;
        }
        tr:active { background: rgba(255, 255, 255, 0.06); }

        td {
          border: none;
          position: relative;
          padding-left: 50%;
          text-align: right;
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 8px 0;
        }
        td:before {
          content: attr(data-label);
          font-weight: 800;
          color: var(--muted);
          font-size: 11px;
          text-transform: uppercase;
        }
        
        /* First child (Date) handling */
        td:first-child {
          text-align: left;
          font-weight: 900;
          color: var(--accent);
          border-bottom: none;
          margin-bottom: 0;
          padding-bottom: 0;
          justify-content: space-between;
          width: 100%;
          padding-left: 0;
        }
        td:first-child:before { display: none; }

        /* Mobile Summary Styling */
        .mobile-summary {
          display: flex;
          gap: 8px;
          align-items: center;
          font-size: 13px;
          color: #fff;
          font-weight: 700;
        }
        .mobile-summary .dim { color: var(--muted); font-weight: 400; }
        .mobile-summary .sum-total { color: #fff; }

        /* Expansion Logic */
        tr:not(.expanded) td:not(:first-child) { display: none; }
        
        tr.expanded td:first-child {
          border-bottom: 1px solid var(--border);
          margin-bottom: 10px;
          padding-bottom: 10px;
        }
        
        tr.expanded .mobile-summary { display: none; }

        .month-summary {
          flex-direction: column;
          text-align: center;
        }
        .loc-totals {
          font-size: 12px;
        }
      }
    </style>
  </head>

  <body>
    <div class="wrap">
      <div class="header">
        <div class="brand">
          <img src="https://alleycatphoto.net/alley_logo_sm.png" alt="Alley Cat" />
          <div class="brand-text">
            <div class="title">ALLEYCAT SALES BREAKDOWN</div>
            <div class="subtitle">Credit vs Cash Breakdown</div>
          </div>
        </div>
        <form method="get" style="margin:0;">
          <input type="hidden" name="action" value="update" />
          <button type="submit" class="back-btn">UPDATE REPORT</button>
        </form>
      </div>

      <div class="header" style="margin-bottom: 14px; padding: 12px 20px;">
        <div class="filters">
          <input type="date" id="startDate" />
          <input type="date" id="endDate" />
          <button id="filterBtn" class="btn">Filter</button>
          <button id="resetBtn" class="btn">Reset</button>
        </div>
      </div>

      <div id="summaryContainer" class="summary-grid">
        <!-- Summary cards will be inserted here -->
      </div>

      <div id="cardsContainer">
        <!-- Location cards will be inserted here -->
      </div>

      <div class="warn">
        <b>Note:</b> Data is aggregated from transaction logs. Ensure transactions are properly logged.
      </div>
    </div>

    <script>
      const LOCATIONS = {
        "Moonshine": "Moonshine Mountain",
        "Zipnlip": "Zip n Slip",
        "Hawkmest": "Hawks Nest",
        "UNKNOWN": "Unknown"
      };

      const locOrder = ['Moonshine Mountain', 'Zip n Slip', 'Hawks Nest'];

      // Data from PHP
      const rawData = <?php echo json_encode($data); ?>;

      function formatMoney(cents) {
        const dollars = cents / 100;
        return '$' + dollars.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      function formatDate(yyyyMmDd) {
        const d = new Date(yyyyMmDd + "T00:00:00");
        if (isNaN(d)) return yyyyMmDd;
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, "0");
        const dd = String(d.getDate()).padStart(2, "0");
        return `${mm}/${dd}/${yyyy}`;
      }

      function isInRange(dateISO, startISO, endISO) {
        return dateISO >= startISO && dateISO <= endISO;
      }

      function clampLocId(raw) {
        if (!raw) return "UNKNOWN";
        // Simplified: just return the location name as-is
        return raw;
      }

      function ensureDay(data, locId, dateISO) {
        if (!data[locId]) data[locId] = {};
        if (!data[locId][dateISO]) data[locId][dateISO] = { credit: 0, cash: 0, orders: 0 };
        return data[locId][dateISO];
      }

      function sortDataDescByDate(data) {
        const sorted = {};
        for (const locId of locOrder) {
          sorted[locId] = {};
          if (data[locId]) {
            const dates = Object.keys(data[locId]).sort((a, b) => (a < b ? 1 : a > b ? -1 : 0));
            for (const date of dates) sorted[locId][date] = data[locId][date];
          }
        }
        return sorted;
      }

      function computeGrandTotals(data) {
        let total = 0;
        let orders = 0;
        for (const locId of Object.keys(data)) {
          const byDate = data[locId] || {};
          for (const date of Object.keys(byDate)) {
            const s = byDate[date];
            total += (s.credit || 0) + (s.cash || 0);
            orders += s.orders || 0;
          }
        }
        return { total, orders };
      }

      function computeLocTotals(byDate) {
        let total = 0;
        let orders = 0;
        for (const date of Object.keys(byDate)) {
          const s = byDate[date];
          total += (s.credit || 0) + (s.cash || 0);
          orders += s.orders || 0;
        }
        return { total, orders };
      }

      function renderSummaryCards(data, startISO, endISO) {
        const { total, orders } = computeGrandTotals(data);
        const container = document.getElementById('summaryContainer');
        container.innerHTML = `
          <div class="stat-card">
            <div class="stat-val">${formatMoney(total)}</div>
            <div class="stat-label">Grand Total</div>
          </div>
          <div class="stat-card">
            <div class="stat-val">${orders}</div>
            <div class="stat-label">Total Orders</div>
          </div>
        `;
      }

      function getMonthName(monthKey) {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        try {
          const [year, monthStr] = monthKey.split('-');
          const month = parseInt(monthStr, 10) - 1;
          if (month >= 0 && month < 12) {
            return `${monthNames[month]} ${year}`;
          }
          return `Month ${monthKey}`;
        } catch (e) {
          return `Month ${monthKey}`;
        }
      }

      function renderCombinedTable(data, startISO, endISO) {
        const container = document.getElementById('cardsContainer');
        container.innerHTML = '';

        // Define location order: Moonshine, Zip'n'Slip, Hawks Nest
        const locOrder = ['Moonshine Mountain', 'Zip n Slip', 'Hawks Nest'];

        // Collect all dates
        const allDates = new Set();
        for (const locId of locOrder) {
          const byDate = data[locId] || {};
          for (const date of Object.keys(byDate)) {
            if (!startISO || !endISO || isInRange(date, startISO, endISO)) {
              allDates.add(date);
            }
          }
        }
        const sortedDates = Array.from(allDates).sort((a, b) => (a < b ? 1 : a > b ? -1 : 0)); // Most recent first

        if (!sortedDates.length) {
          container.innerHTML = '<div class="empty">No data available for the selected date range.</div>';
          return;
        }

        // Group dates by month
        const months = {};
        for (const date of sortedDates) {
          const [year, month] = date.split('-');
          const monthKey = `${year}-${month}`;
          if (!months[monthKey]) months[monthKey] = [];
          months[monthKey].push(date);
        }

        // Sort months descending
        const sortedMonths = Object.keys(months).sort((a, b) => (a < b ? 1 : a > b ? -1 : 0));

        // Render each month as a collapsible card
        for (const monthKey of sortedMonths) {
          const monthDates = months[monthKey];
          const [year, month] = monthKey.split('-');
          const monthName = getMonthName(monthKey);

          // Calculate monthly totals
          let monthTotal = 0;
          let monthOrders = 0;
          const locTotals = { 'Moonshine Mountain': 0, 'Zip n Slip': 0, 'Hawks Nest': 0 };
          for (const date of monthDates) {
            let dayTotal = 0;
            let dayOrders = 0;
            for (const locId of locOrder) {
              const byDate = data[locId] || {};
              const day = byDate[date] || { credit: 0, cash: 0, orders: 0 };
              const locDayTotal = day.credit + day.cash;
              dayTotal += locDayTotal;
              dayOrders += day.orders;
              locTotals[locId] += locDayTotal;
            }
            monthTotal += dayTotal;
            monthOrders += dayOrders;
          }

          // Create month card
          const card = document.createElement('div');
          card.className = 'card month-card';

          card.innerHTML = `
            <div class="card-hd month-summary">
              <h2>${monthName.toUpperCase()} <span style="color: var(--muted);">(${monthOrders} Orders)</span></h2>
              <div class="loc-totals">
                <span style="color: #f1c40f;"><img src="/public/assets/images/moonshine.png" style="height:16px; margin-right:4px;">MOON: ${formatMoney(locTotals['Moonshine Mountain'])}</span> |
                <span style="color: #3498db;"><img src="/public/assets/images/zipnslip.png" style="height:16px; margin-right:4px;">ZIP: ${formatMoney(locTotals['Zip n Slip'])}</span> |
                <span style="color: #e74c3c;"><img src="/public/assets/images/hawk.png" style="height:16px; margin-right:4px;">HAWK: ${formatMoney(locTotals['Hawks Nest'])}</span> |
                <span style="color: #27ae60;">TOTAL: ${formatMoney(monthTotal)}</span>
              </div>
            </div>
            <div class="month-table" style="display: none;">
              <table class="table table-striped table-hover">
                <thead>
                  <tr>
                    <th rowspan="2">Date</th>
                    <th colspan="3" style="text-align: center;">Moonshine</th>
                    <th colspan="3" style="text-align: center;">Zip'n'Slip</th>
                    <th colspan="3" style="text-align: center;">Hawks Nest</th>
                    <th rowspan="2" style="text-align: center;">Grand Total</th>
                  </tr>
                  <tr>
                    <th style="color: #f1c40f; text-align: center;">Credit</th><th style="color: #f1c40f; text-align: center;">Cash</th><th style="color: #f1c40f; text-align: center;">Total</th>
                    <th style="color: #3498db; text-align: center;">Credit</th><th style="color: #3498db; text-align: center;">Cash</th><th style="color: #3498db; text-align: center;">Total</th>
                    <th style="color: #e74c3c; text-align: center;">Credit</th><th style="color: #e74c3c; text-align: center;">Cash</th><th style="color: #e74c3c; text-align: center;">Total</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          `;

          const tbody = card.querySelector('tbody');
          const labels = ['Date', 'Moonshine Credit', 'Moonshine Cash', 'Moonshine Total', 'Zip Credit', 'Zip Cash', 'Zip Total', 'Hawks Nest Credit', 'Hawks Nest Cash', 'Hawks Nest Total', 'Grand Total'];

          // Add rows for each date in the month
          for (const date of monthDates) {
            let grandTotal = 0;
            const rowData = [formatDate(date)];
            for (const locId of locOrder) {
              const byDate = data[locId] || {};
              const day = byDate[date] || { credit: 0, cash: 0, orders: 0 };
              const credit = day.credit;
              const cash = day.cash;
              const total = credit + cash;
              rowData.push(credit === 0 ? '' : formatMoney(credit), cash === 0 ? '' : formatMoney(cash), total === 0 ? '' : formatMoney(total));
              grandTotal += total;
            }
            rowData.push(grandTotal === 0 ? '' : formatMoney(grandTotal));

            const tr = document.createElement('tr');
            tr.innerHTML = rowData.map((val, i) => `<td data-label="${labels[i]}">${val}</td>`).join('');
            tbody.appendChild(tr);
          }

          // Add click handler to toggle table
          const summary = card.querySelector('.month-summary');
          const tableDiv = card.querySelector('.month-table');
          summary.addEventListener('click', () => {
            tableDiv.style.display = tableDiv.style.display === 'none' ? 'block' : 'none';
          });

          container.appendChild(card);
        }
      }

      function renderAll(data, startISO, endISO) {
        renderSummaryCards(data, startISO, endISO);
        renderCombinedTable(data, startISO, endISO);
      }

      // Process raw data: convert amounts to cents, map locations
      function processData(raw) {
        const processed = {};
        for (const locId of Object.keys(raw)) {
          processed[locId] = {};
          for (const date of Object.keys(raw[locId])) {
            const day = raw[locId][date];
            processed[locId][date] = {
              credit: Math.round(day.credit * 100),
              cash: Math.round(day.cash * 100),
              orders: day.orders
            };
          }
        }
        return sortDataDescByDate(processed);
      }

      const processedData = processData(rawData);

      // Initial render
      renderAll(processedData);

      // Filter functionality
      document.getElementById('filterBtn').addEventListener('click', () => {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        renderAll(processedData, start, end);
      });

      document.getElementById('resetBtn').addEventListener('click', () => {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        renderAll(processedData);
      });
    </script>
  </body>
</html>