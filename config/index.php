<?php
// Gemicunt Master Control Console
// Independent View - /config/index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPS90 Master Control v9.0</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" href="/favicon.ico">
    <style>
        .health-bar {
            background: #111;
            border-bottom: 1px solid #333;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        .health-item { display: flex; align-items: center; gap: 6px; color: #888; }
        .health-dot { width: 8px; height: 8px; border-radius: 50%; background: #444; }
        .health-dot.ok { background: #00c853; box-shadow: 0 0 5px #00c853; }
        .health-dot.err { background: #d50000; box-shadow: 0 0 5px #d50000; }
        .connect-btn { 
            background: #4285f4; color: white; border: none; padding: 4px 10px; 
            border-radius: 4px; cursor: pointer; font-size: 0.8rem; display: none; 
        }
        .connect-btn:hover { background: #357abd; }
        
        /* Debug Panel Styles */
        .debug-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 0;
            background: #0a0a0a;
            border-top: 2px solid #333;
            transition: height 0.3s ease;
            display: flex;
            flex-direction: column;
            z-index: 10000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.5);
        }
        
        .debug-panel.open {
            height: calc(100vh - 60px);
        }
        
        .debug-panel-header {
            background: #1a1a1a;
            border-bottom: 1px solid #333;
            padding: 8px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        .debug-panel-header h3 {
            margin: 0;
            color: #c41e3a;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .debug-panel-header button {
            background: #333;
            border: 1px solid #555;
            color: #aaa;
            padding: 4px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .debug-panel-header button:hover {
            background: #444;
            color: #fff;
        }
        
        .debug-panel-content {
            flex: 1;
            overflow: hidden;
            display: flex;
        }
        
        .debug-panel iframe {
            border: none;
            width: 100%;
            height: 100%;
        }
        
        .debug-toggle-btn {
            position: fixed;
            bottom: 15px;
            right: 15px;
            background: #c41e3a;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            z-index: 9999;
            transition: all 0.2s ease;
        }
        
        .debug-toggle-btn:hover {
            background: #ff3c4f;
            transform: scale(1.05);
        }
        
        .debug-toggle-btn.open {
            bottom: calc(50vh + 15px);
        }
    </style>
</head>
<body>

    <header class="app-header">
        <div class="logo-area">
            <img src="/logo.png" alt="ACPS Logo" class="app-logo">
            <h1 class="app-title">Master Control</h1>
        </div>
        <div class="header-controls">
            <div class="control-group">
                <span class="label">Auto Print:</span>
                <label class="switch">
                    <input type="checkbox" id="autoprint-toggle" checked>
                    <span class="slider round"></span>
                </label>
            </div>
            <div class="control-group">
                <span class="label">Auto Refresh:</span>
                <label class="switch">
                    <input type="checkbox" id="autorefresh-toggle" checked>
                    <span class="slider round"></span>
                </label>
                <div id="refresh-countdown" class="refresh-countdown">10</div>
            </div>
            <div id="clock" class="clock">00:00:00</div>
            <button id="refreshBtn" class="btn btn-icon" title="Refresh Now">↻</button>
            <button onclick="toggleDebugPanel()" class="btn btn-icon" title="Debug Console" style="margin-left:5px; color:#666;">⚙</button>
        </div>
    </header>

    <div class="health-bar" id="health-bar">
        <div style="display:flex; gap:20px;">
            <div class="health-item">
                <div class="health-dot" id="gmail-dot"></div>
                <span>Gmail API</span>
            </div>
            <div class="health-item">
                <div class="health-dot" id="drive-dot"></div>
                <span>Google Drive</span>
            </div>
            <div class="health-item" id="account-info">
                <span>Account: <span id="account-email">Checking...</span></span>
            </div>
        </div>
        <a href="api/google_auth.php?action=start" class="connect-btn" id="connect-btn" target="_blank">Connect Google Account</a>
    </div>

    <main class="app-shell">
        <div class="orders-panel">
            <div class="date-header">
                📅 <?php echo date('F j, Y'); ?>
            </div>

            <div class="panel-header">
                <div class="tabs">
                    <div class="tab-item tab-pending active" data-tab="pending">Pending <span class="tab-count">(0)</span></div>
                    <div class="tab-item tab-paid" data-tab="paid">Paid <span class="tab-count">(0)</span></div>
                    <div class="tab-item tab-void" data-tab="void">Void <span class="tab-count">(0)</span></div>
                    <div class="tab-item tab-all" data-tab="all">All <span class="tab-count">(0)</span></div>
                    <div class="tab-item tab-printer" data-tab="printer">Printer <span class="tab-count" style="display:none">(0)</span></div>
                    <div class="tab-item tab-mailer" data-tab="mailer">Mailer <span class="tab-count" style="display:none">(0)</span></div>
                </div>
                <div class="panel-actions">
                    <span id="status-text" class="status-text">Ready</span>
                </div>
            </div>

            <div class="tab-activity active" id="tab-activity">
                <div class="activity-dot" id="activity-indicator"></div>
                <span id="activity-text">Ready</span>
            </div>

            <div class="orders-list" id="orders-list">
                <!-- Orders or Queue Items injected here by app.js -->
            </div>
        </div>
    </main>

    <!-- Receipt Modal -->
    <div class="modal" id="receipt-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Receipt #</h2>
                <button class="modal-close" onclick="App.closeModal()">×</button>
            </div>
            <div class="modal-body">
                <pre id="receipt-content">Loading...</pre>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="document.querySelector('.modal-print-btn').click()">🖨️ Print Receipt</button>
                <button class="btn btn-secondary modal-print-btn" onclick="App.closeModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Debug Panel -->
    <div class="debug-panel" id="debugPanel">
        <div class="debug-panel-header">
            <h3>🛠️ Debug Console</h3>
            <button onclick="toggleDebugPanel()">Close</button>
        </div>
        <div class="debug-panel-content">
            <iframe src="debug.php" id="debugFrame"></iframe>
        </div>
    </div>

    <!-- jQuery needed for the script below -->
    <script src="/public/assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // Debug Panel Toggle
        function toggleDebugPanel() {
            const panel = document.getElementById('debugPanel');
            panel.classList.toggle('open');
            
            // If opening, scroll the iframe to top
            if (panel.classList.contains('open')) {
                setTimeout(() => {
                    const frame = document.getElementById('debugFrame');
                    if (frame && frame.contentWindow) {
                        try {
                            frame.contentWindow.scrollTo(0, 0);
                        } catch (e) {
                            // Cross-origin, ignore
                        }
                    }
                }, 100);
            }
        }
    </script>
</body>
</html>


