<?php
//*********************************************************************//
// ACPS90 - AlleyCat PhotoStation v9.0 - Debug Console                //
// Real-time log viewer with manual test controls                      //
// Responsive two-column layout: Controls (Left) | Logs (Right)        //
//**************************
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['status' => 'error', 'message' => 'Vendor autoload missing. Run composer install.']);
    exit;
}
require_once $autoload;
try { $dotenv = Dotenv\Dotenv::createImmutable(realpath(__DIR__ . '/../')); $dotenv->safeLoad(); } catch (Exception $e) {}


if (isset($_GET['action']) && $_GET['action'] === 'clear_logs') {
    $logFile = __DIR__ . '/../logs/gmailer_error.log';
    if (file_exists($logFile)) {
        file_put_contents($logFile, "");
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}
$admin_email = getenv('ADMIN_EMAIL');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPS90 Debug Console</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            line-height: 1.5;
        }

        .debug-container {
            display: flex;
            height: 100vh;
            flex-direction: row;
        }

        /* LEFT PANEL: CONTROLS */
        .debug-controls {
            flex: 0 0 350px;
            background: #1a1a1a;
            border-right: 1px solid #333;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .debug-controls h2 {
            font-size: 16px;
            color: #c41e3a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            border-bottom: 2px solid #c41e3a;
            padding-bottom: 8px;
        }

        .control-section {
            background: #0f0f0f;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
        }

        .control-section label {
            display: block;
            font-size: 13px;
            color: #aaa;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .control-section input,
        .control-section select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            background: #1a1a1a;
            border: 1px solid #444;
            border-radius: 4px;
            color: #fff;
            font-size: 13px;
        }

        .control-section input:focus,
        .control-section select:focus {
            outline: none;
            border-color: #4cf;
            box-shadow: 0 0 0 3px rgba(76, 207, 255, 0.1);
        }

        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .button-group.full {
            grid-template-columns: 1fr;
        }

        button {
            padding: 10px 14px;
            border: 1px solid #444;
            border-radius: 4px;
            background: #2a2a2a;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        button:hover {
            background: #3a3a3a;
            border-color: #555;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background: #c41e3a;
            color: #fff;
            border-color: #c41e3a;
        }

        .btn-primary:hover {
            background: #e63946;
            border-color: #e63946;
        }

        .btn-success {
            background: #2a7a4a;
            border-color: #4aaa6a;
            color: #aaffaa;
        }

        .btn-success:hover {
            background: #3a9a5a;
            border-color: #5abf7a;
        }

        .btn-danger {
            background: #7a2a2a;
            border-color: #aa4a4a;
            color: #ffaaaa;
        }

        .btn-danger:hover {
            background: #9a3a3a;
            border-color: #bb5a5a;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.success {
            background: #1a4a2a;
            color: #8aff8a;
            border: 1px solid #4aaa6a;
        }

        .status-badge.error {
            background: #4a1a1a;
            color: #ffaaaa;
            border: 1px solid #aa4a4a;
        }

        .status-badge.info {
            background: #1a2a4a;
            color: #aaccff;
            border: 1px solid #4a6aaa;
        }

        /* RIGHT PANEL: LOGS */
        .debug-logs {
            flex: 1;
            background: #050505;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .log-header {
            background: #1a1a1a;
            border-bottom: 1px solid #333;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .log-header h2 {
            font-size: 16px;
            color: #c41e3a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        .log-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .log-controls button {
            padding: 8px 12px;
            font-size: 11px;
        }

        .log-viewer {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 15px 20px;
            font-family: "SF Mono", Monaco, "Inconsolata", "Fira Code", monospace;
            font-size: 12px;
            line-height: 1.6;
        }

        .log-line {
            padding: 4px 0;
            white-space: pre-wrap;
            word-break: break-word;
            color: #aaa;
        }

        .log-line.gmailer_init {
            color: #79dfff;
        }

        .log-line.success {
            color: #8aff8a;
        }

        .log-line.error {
            color: #ff8787;
        }

        .log-line.warning {
            color: #ffc184;
        }

        .log-line.timestamp {
            color: #666;
        }

        .log-line.order-id {
            color: #d6ffd6;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #1a1a1a;
        }

        ::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .debug-controls {
                flex: 0 0 300px;
            }

            .button-group {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .debug-container {
                flex-direction: column;
            }

            .debug-controls {
                flex: 0 0 auto;
                max-height: 40vh;
                border-right: none;
                border-bottom: 1px solid #333;
            }

            .debug-logs {
                flex: 1;
                min-height: 60vh;
            }
        }

        .section-title {
            font-size: 13px;
            color: #c41e3a;
            font-weight: 600;
            margin-top: 15px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .input-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .input-row input {
            flex: 1;
            margin-bottom: 0;
        }

        .input-row button {
            flex: 0 0 auto;
            width: auto;
            min-width: 60px;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <!-- LEFT PANEL: CONTROLS -->
        <div class="debug-controls">
            <div>
                <h2>🛠️ Debug Console</h2>
                <p style="font-size: 12px; color: #888; margin: 0;">Manual testing & order monitoring</p>
            </div>

            <!-- Create Test Order -->
            <div class="control-section">
                <div class="section-title">Create Test Order</div>
                <label>Email</label>
                <input type="email" id="testEmail" placeholder="test@example.com" value="<?php echo htmlspecialchars($admin_email); ?>">
                
                <label>Amount ($)</label>
                <input type="number" id="testAmount" placeholder="25.00" value="25.00" step="0.01" min="0">
                
                <label>Payment Method</label>
                <select id="testPaymentMethod">
                    <option value="cash">Cash</option>
                    <option value="square">Square</option>
                    <option value="qr">QR Code</option>
                </select>

                <div class="button-group full">
                    <button class="btn-primary" onclick="createTestOrder()">Create Order</button>
                </div>
                <div id="orderStatus" style="font-size: 11px; color: #aaa; margin-top: 10px;"></div>
            </div>

            <!-- Trigger Gmailer -->
            <div class="control-section">
                <div class="section-title">Test Email Delivery</div>
                <label>Order ID</label>
                <input type="number" id="gmailerOrderId" placeholder="1030" min="1">
                
                <div class="button-group full">
                    <button class="btn-success" onclick="triggerGmailer()">Trigger Gmailer</button>
                </div>
                <div id="gmailerStatus" style="font-size: 11px; color: #aaa; margin-top: 10px;"></div>
            </div>

            <!-- Manual Actions -->
            <div class="control-section">
                <div class="section-title">Manual Actions</div>
                
                <label>Order ID for Action</label>
                <input type="number" id="actionOrderId" placeholder="1030" min="1">
                
                <div class="button-group">
                    <button class="btn-success" onclick="markOrderPaid()">Mark Paid</button>
                    <button class="btn-danger" onclick="voidOrder()">Void Order</button>
                </div>
                <div id="actionStatus" style="font-size: 11px; color: #aaa; margin-top: 10px;"></div>
            </div>

            <!-- Manual Payments -->
            <div class="control-section">
                <div class="section-title">Manual Payments</div>
                
                <label>Order ID</label>
                <input type="number" id="paymentOrderId" placeholder="1031" min="1">
                
                <label>Amount ($)</label>
                <input type="number" id="paymentAmount" placeholder="25.00" step="0.01" min="0">
                
                <div class="button-group">
                    <button onclick="simulateCashPayment()" style="background: #333; border-color: #555;">💵 Cash</button>
                    <button onclick="simulateQRPayment()" style="background: #333; border-color: #555;">📱 QR</button>
                </div>
                
                <div class="button-group full" style="margin-top: 8px;">
                    <button onclick="simulateTerminalPayment()" style="background: #333; border-color: #555;">💳 Terminal</button>
                </div>
                <div id="paymentStatus" style="font-size: 11px; color: #aaa; margin-top: 10px;"></div>
            </div>

            <!-- Trigger Mailer Spooler -->
            <div class="control-section">
                <div class="section-title">Spooler Control</div>
                
                <div class="button-group full">
                    <button class="btn-primary" onclick="triggerMailerSpooler()">Trigger Mailer Tick</button>
                </div>
                <div id="spoolerStatus" style="font-size: 11px; color: #aaa; margin-top: 10px;"></div>
            </div>

            <!-- Mail Queue Monitor -->
            <div class="control-section">
                <div class="section-title">📬 Mail Queue</div>
                <div id="mailQueueStatus" style="font-size: 12px; margin-bottom: 10px;">
                    <span id="queueCount" style="color: #ff8787;">Loading...</span>
                </div>
                <div id="mailQueueList" style="max-height: 250px; overflow-y: auto; margin-bottom: 10px; border: 1px solid #333; border-radius: 4px; padding: 8px; background: #0a0a0a;">
                    <p style="color: #666; text-align: center; padding: 20px 0;">No stuck orders</p>
                </div>
                <div class="button-group full">
                    <button class="btn-primary" onclick="loadMailQueue()">Refresh Queue</button>
                </div>
                <div id="queueActionStatus" style="font-size: 11px; color: #aaa; margin-top: 10px;"></div>
            </div>

            <!-- Logs Control -->
            <div class="control-section">
                <div class="section-title">Log Control</div>
                
                <div class="button-group full">
                    <button onclick="clearLogs()">Clear All Logs</button>
                </div>

                <div style="margin-top: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="autoScrollLog" checked style="width: auto; margin: 0;">
                        <span style="margin: 0;">Auto Scroll</span>
                    </label>
                </div>

                <div style="margin-top: 15px;">
                    <label>Refresh Rate (ms)</label>
                    <input type="number" id="refreshRate" value="1000" min="500" max="10000" step="100">
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: LOGS -->
        <div class="debug-logs">
            <div class="log-header">
                <h2>📋 Event Log Stream</h2>
                <div class="log-controls">
                    <button onclick="pauseLogs()" id="pauseBtn">Pause</button>
                    <button onclick="refreshLogs()">Refresh</button>
                </div>
            </div>
            <div class="log-viewer" id="logViewer">
                <div class="log-line timestamp">Loading logs...</div>
            </div>
        </div>
    </div>

    <script>
        let logsPaused = false;
        let autoScroll = true;
        let refreshInterval = null;

        // Format log lines with syntax highlighting
        function formatLogLine(line) {
            if (!line.trim()) return { html: '', class: '' };

            let cls = '';
            let formattedLine = line;

            // Color-code by type
            if (line.includes('SUCCESS')) cls = 'success';
            else if (line.includes('ERROR') || line.includes('FATAL')) cls = 'error';
            else if (line.includes('WARNING')) cls = 'warning';
            else if (line.includes('GMAILER_INIT')) cls = 'gmailer_init';

            // Highlight timestamps
            formattedLine = formattedLine.replace(/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/, '<span class="timestamp">$1</span>');
            
            // Highlight order IDs
            formattedLine = formattedLine.replace(/Order (\d+)/g, 'Order <span class="order-id">$1</span>');

            return { html: formattedLine, class: cls };
        }

        // Refresh logs from server
        async function refreshLogs() {
            if (logsPaused) return;

            try {
                const resp = await fetch('/logs/gmailer_error.log');
                const text = await resp.text();
                const lines = text.split('\n').filter(l => l.trim());
                
                const viewer = document.getElementById('logViewer');
                viewer.innerHTML = '';

                // Show last 100 lines
                const displayLines = lines.slice(-100);

                displayLines.forEach(line => {
                    const formatted = formatLogLine(line);
                    const div = document.createElement('div');
                    div.className = 'log-line ' + formatted.class;
                    div.innerHTML = formatted.html;
                    viewer.appendChild(div);
                });

                // Auto scroll to bottom
                if (autoScroll) {
                    viewer.scrollTop = viewer.scrollHeight;
                }
            } catch (err) {
                console.error('Failed to load logs:', err);
                document.getElementById('logViewer').innerHTML = '<div class="log-line error">Failed to load logs</div>';
            }
        }

        function pauseLogs() {
            logsPaused = !logsPaused;
            const btn = document.getElementById('pauseBtn');
            btn.textContent = logsPaused ? 'Resume' : 'Pause';
            btn.style.background = logsPaused ? '#7a2a2a' : '#2a2a2a';
        }

        function clearLogs() {
            if (!confirm('Clear all logs?')) return;

            fetch('?action=clear_logs', { method: 'POST' })
                .then(() => {
                    document.getElementById('logViewer').innerHTML = '<div class="log-line success">Logs cleared</div>';
                    refreshLogs();
                })
                .catch(err => console.error('Failed to clear logs:', err));
        }

        // Create test order
        async function createTestOrder() {
            const email = document.getElementById('testEmail').value;
            const amount = document.getElementById('testAmount').value;
            const method = document.getElementById('testPaymentMethod').value;
            const statusEl = document.getElementById('orderStatus');

            if (!email || !amount) {
                statusEl.innerHTML = '<span class="status-badge error">Missing required fields</span>';
                return;
            }

            statusEl.innerHTML = '<span class="status-badge info">Creating...</span>';

            try {
                const resp = await fetch('/config/api/checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        payment_method: method,
                        email: email,
                        amount: amount
                    }).toString()
                });

                const data = await resp.json();
                if (data.order_id) {
                    statusEl.innerHTML = `<span class="status-badge success">Order ${data.order_id} created</span>`;
                    document.getElementById('gmailerOrderId').value = data.order_id;
                } else {
                    statusEl.innerHTML = `<span class="status-badge error">Failed: ${data.message || 'Unknown error'}</span>`;
                }
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">Error: ${err.message}</span>`;
            }
        }

        // Trigger gmailer
        async function triggerGmailer() {
            const orderId = document.getElementById('gmailerOrderId').value;
            const statusEl = document.getElementById('gmailerStatus');

            if (!orderId) {
                statusEl.innerHTML = '<span class="status-badge error">Enter order ID</span>';
                return;
            }

            statusEl.innerHTML = '<span class="status-badge info">Processing...</span>';

            try {
                const resp = await fetch(`/gmailer.php?order=${orderId}`, { method: 'POST' });
                const text = await resp.text();
                
                if (text.includes('SUCCESS')) {
                    statusEl.innerHTML = '<span class="status-badge success">Gmailer executed</span>';
                } else {
                    statusEl.innerHTML = '<span class="status-badge warning">Check logs for details</span>';
                }
                refreshLogs();
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">Error: ${err.message}</span>`;
            }
        }

        // Mark order paid
        async function markOrderPaid() {
            const orderId = document.getElementById('actionOrderId').value;
            const statusEl = document.getElementById('actionStatus');

            if (!orderId) {
                statusEl.innerHTML = '<span class="status-badge error">Enter order ID</span>';
                return;
            }

            statusEl.innerHTML = '<span class="status-badge info">Processing...</span>';

            try {
                const resp = await fetch('/config/api/order_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        order: orderId,
                        action: 'paid',
                        autoprint: '1'
                    }).toString()
                });

                const data = await resp.json();
                if (data.status === 'success') {
                    statusEl.innerHTML = '<span class="status-badge success">Order marked paid</span>';
                } else {
                    statusEl.innerHTML = `<span class="status-badge error">${data.message || 'Failed'}</span>`;
                }
                refreshLogs();
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">Error: ${err.message}</span>`;
            }
        }

        // Void order
        async function voidOrder() {
            const orderId = document.getElementById('actionOrderId').value;
            const statusEl = document.getElementById('actionStatus');

            if (!orderId || !confirm('Void this order?')) return;

            statusEl.innerHTML = '<span class="status-badge info">Processing...</span>';

            try {
                const resp = await fetch('/config/api/order_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        order: orderId,
                        action: 'void'
                    }).toString()
                });

                const data = await resp.json();
                if (data.status === 'success') {
                    statusEl.innerHTML = '<span class="status-badge success">Order voided</span>';
                } else {
                    statusEl.innerHTML = `<span class="status-badge error">${data.message || 'Failed'}</span>`;
                }
                refreshLogs();
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">Error: ${err.message}</span>`;
            }
        }

        // Trigger mailer spooler
        async function triggerMailerSpooler() {
            const statusEl = document.getElementById('spoolerStatus');
            statusEl.innerHTML = '<span class="status-badge info">Processing...</span>';

            try {
                const resp = await fetch('/trigger_mailer_v2.php');
                const data = await resp.json();
                if (data.triggered && data.triggered.length > 0) {
                    statusEl.innerHTML = `<span class="status-badge success">Triggered: ${data.triggered.join(', ')}</span>`;
                } else {
                    statusEl.innerHTML = '<span class="status-badge info">No orders in queue</span>';
                }
                refreshLogs();
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">Error: ${err.message}</span>`;
            }
        }

        // Simulate Cash Payment
        async function simulateCashPayment() {
            const orderId = document.getElementById('paymentOrderId').value;
            const amount = document.getElementById('paymentAmount').value;
            const statusEl = document.getElementById('paymentStatus');

            if (!orderId || !amount) {
                statusEl.innerHTML = '<span class="status-badge error">Enter Order ID and Amount</span>';
                return;
            }

            statusEl.innerHTML = '<span class="status-badge info">Processing cash payment...</span>';

            try {
                const resp = await fetch('/config/api/order_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        order: orderId,
                        action: 'paid',
                        payment_type: 'cash',
                        amount: amount,
                        autoprint: '1'
                    }).toString()
                });

                const data = await resp.json();
                if (data.status === 'success') {
                    statusEl.innerHTML = '<span class="status-badge success">💵 Cash payment processed</span>';
                } else {
                    statusEl.innerHTML = `<span class="status-badge error">${data.message || 'Failed'}</span>`;
                }
                refreshLogs();
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">Error: ${err.message}</span>`;
            }
        }

        // Simulate QR Payment
        async function simulateQRPayment() {
            const orderId = document.getElementById('paymentOrderId').value;
            const amount = document.getElementById('paymentAmount').value;
            const statusEl = document.getElementById('paymentStatus');

            if (!orderId || !amount) {
                statusEl.innerHTML = '<span class="status-badge error">Enter Order ID and Amount</span>';
                return;
            }

            statusEl.innerHTML = '<span class="status-badge info">Processing QR payment...</span>';

            try {
                const resp = await fetch('/config/api/order_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        order: orderId,
                        action: 'paid',
                        payment_type: 'qr',
                        amount: amount,
                        autoprint: '1'
                    }).toString()
                });

                const data = await resp.json();
                if (data.status === 'success') {
                    statusEl.innerHTML = '<span class="status-badge success">📱 QR payment processed</span>';
                } else {
                    statusEl.innerHTML = `<span class="status-badge error">${data.message || 'Failed'}</span>`;
                }
                refreshLogs();
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">Error: ${err.message}</span>`;
            }
        }

        // Simulate Terminal Payment
        async function simulateTerminalPayment() {
            const orderId = document.getElementById('paymentOrderId').value;
            const amount = document.getElementById('paymentAmount').value;
            const statusEl = document.getElementById('paymentStatus');

            if (!orderId || !amount) {
                statusEl.innerHTML = '<span class="status-badge error">Enter Order ID and Amount</span>';
                return;
            }

            statusEl.innerHTML = '<span class="status-badge info">Processing terminal payment...</span>';

            try {
                const resp = await fetch('/config/api/order_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        order: orderId,
                        action: 'paid',
                        payment_type: 'terminal',
                        amount: amount,
                        autoprint: '1'
                    }).toString()
                });

                const data = await resp.json();
                if (data.status === 'success') {
                    statusEl.innerHTML = '<span class="status-badge success">💳 Terminal payment processed</span>';
                } else {
                    statusEl.innerHTML = `<span class="status-badge error">${data.message || 'Failed'}</span>`;
                }
                refreshLogs();
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">Error: ${err.message}</span>`;
            }
        }

        // ========== MAIL QUEUE FUNCTIONS ==========
        
        // Load and display mail queue
        async function loadMailQueue() {
            const listEl = document.getElementById('mailQueueList');
            const countEl = document.getElementById('queueCount');
            
            listEl.innerHTML = '<p style="color: #666; text-align: center; padding: 20px 0;">Loading queue...</p>';
            
            try {
                const resp = await fetch('/config/api/mail_queue.php?action=list');
                const data = await resp.json();
                
                if (data.status !== 'success' || !data.orders || data.orders.length === 0) {
                    countEl.innerHTML = '<span style="color: #8aff8a;">✓ Queue empty</span>';
                    listEl.innerHTML = '<p style="color: #666; text-align: center; padding: 20px 0;">No stuck orders</p>';
                    return;
                }
                
                const lockedOrders = data.orders.filter(o => o.locked);
                countEl.innerHTML = `<span style="color: #ff8787;">⚠ ${lockedOrders.length} stuck orders</span>`;
                
                listEl.innerHTML = '';
                
                lockedOrders.forEach(order => {
                    const div = document.createElement('div');
                    div.style.cssText = 'padding: 8px; border-bottom: 1px solid #333; font-size: 11px;';
                    
                    const ageMin = Math.floor(order.lock_age_seconds / 60);
                    const ageSec = order.lock_age_seconds % 60;
                    
                    div.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="color: #c41e3a; font-weight: bold;">Order #${order.order_id}</div>
                                <div style="color: #aaa; margin-top: 2px;">${order.email}</div>
                                <div style="color: #666; margin-top: 2px; font-size: 10px;">
                                    Stuck: ${ageMin}m ${ageSec}s | ${order.images} images
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button onclick="retryMailOrder(${order.order_id})" style="padding: 4px 8px; font-size: 10px; background: #c41e3a; border: 1px solid #ff8787; border-radius: 3px; cursor: pointer; color: white;">
                                    Retry
                                </button>
                                <button onclick="unlockMailOrder(${order.order_id})" style="padding: 4px 8px; font-size: 10px; background: #333; border: 1px solid #555; border-radius: 3px; cursor: pointer; color: #aaa;">
                                    Unlock
                                </button>
                            </div>
                        </div>
                    `;
                    listEl.appendChild(div);
                });
                
            } catch (err) {
                countEl.innerHTML = '<span style="color: #ff8787;">Error loading queue</span>';
                listEl.innerHTML = `<div style="color: #ff8787; padding: 10px; font-size: 11px;">${err.message}</div>`;
            }
        }
        
        // Retry sending a mail order
        async function retryMailOrder(orderId) {
            const statusEl = document.getElementById('queueActionStatus');
            statusEl.innerHTML = `<span class="status-badge info">⏳ Retrying order ${orderId}...</span>`;
            
            try {
                const resp = await fetch(`/config/api/mail_queue.php?action=retry&order_id=${orderId}`);
                const data = await resp.json();
                
                if (data.status === 'success') {
                    statusEl.innerHTML = `<span class="status-badge success">✓ Order ${orderId} retry queued - check logs</span>`;
                    setTimeout(loadMailQueue, 1000);
                } else {
                    statusEl.innerHTML = `<span class="status-badge error">✗ ${data.message}</span>`;
                }
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">✗ Error: ${err.message}</span>`;
            }
        }
        
        // Unlock a stuck mail order
        async function unlockMailOrder(orderId) {
            const statusEl = document.getElementById('queueActionStatus');
            statusEl.innerHTML = `<span class="status-badge info">🔓 Unlocking order ${orderId}...</span>`;
            
            try {
                const resp = await fetch(`/config/api/mail_queue.php?action=unlock&order_id=${orderId}`);
                const data = await resp.json();
                
                if (data.status === 'success') {
                    statusEl.innerHTML = `<span class="status-badge success">✓ Order ${orderId} unlocked</span>`;
                    setTimeout(loadMailQueue, 500);
                } else {
                    statusEl.innerHTML = `<span class="status-badge error">✗ ${data.message}</span>`;
                }
            } catch (err) {
                statusEl.innerHTML = `<span class="status-badge error">✗ Error: ${err.message}</span>`;
            }
        }

        // Setup auto refresh
        document.getElementById('autoScrollLog').addEventListener('change', (e) => {
            autoScroll = e.target.checked;
        });

        document.getElementById('refreshRate').addEventListener('change', (e) => {
            const rate = parseInt(e.target.value);
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(refreshLogs, rate);
        });

        // Initial load and setup
        loadMailQueue();  // Load mail queue on startup
        refreshLogs();
        refreshInterval = setInterval(refreshLogs, 1000);
    </script>
</body>
</html>
