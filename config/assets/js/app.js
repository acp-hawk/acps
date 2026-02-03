const App = {
    state: {
        orders: [],
        isLoading: false,
        lastUpdate: null,
        autoRefresh: true,
        viewMode: 'pending', 
        autoPrint: true,
        countdown: 10,
        healthCheckInterval: null,
        currentActivity: null,  // Track current activity for UI
        spoolerCounts: { printer: 0, mailer: 0 }
    },

    elements: {
        list: null,
        clock: null,
        status: null,
        modal: null,
        modalTitle: null,
        receiptContent: null,
        refreshBtn: null,
        autoPrintToggle: null,
        autoRefreshToggle: null,
        refreshCountdown: null,
        tabs: [],
        gmailDot: null,
        driveDot: null,
        accountEmail: null,
        connectBtn: null,
        tabActivity: null,
        activityIndicator: null,
        activityText: null
    },

    init() {
        this.elements.list = document.getElementById('orders-list');
        this.elements.clock = document.getElementById('clock');
        this.elements.status = document.getElementById('status-text');
        this.elements.modal = document.getElementById('receipt-modal');
        this.elements.modalTitle = document.getElementById('modal-title');
        this.elements.receiptContent = document.getElementById('receipt-content');
        this.elements.refreshBtn = document.getElementById('refreshBtn');
        this.elements.autoPrintToggle = document.getElementById('autoprint-toggle');       
        this.elements.autoRefreshToggle = document.getElementById('autorefresh-toggle');   
        this.elements.refreshCountdown = document.getElementById('refresh-countdown');     
        
        this.elements.gmailDot = document.getElementById('gmail-dot');
        this.elements.driveDot = document.getElementById('drive-dot');
        this.elements.accountEmail = document.getElementById('account-email');
        this.elements.connectBtn = document.getElementById('connect-btn');
        this.elements.tabActivity = document.getElementById('tab-activity');
        this.elements.activityIndicator = document.getElementById('activity-indicator');
        this.elements.activityText = document.getElementById('activity-text');

        this.elements.tabs = document.querySelectorAll('.tab-item');
        this.elements.tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                this.switchTab(tab.dataset.tab);
            });
        });

        this.updateClock();
        setInterval(() => this.updateClock(), 1000);

        this.fetchOrders();
        this.startRefreshTimer();

        this.tickSpooler();
        setInterval(() => this.tickSpooler(), 1500);

        this.checkHealth();
        setInterval(() => this.checkHealth(), 30000);

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auth') === 'success') {
            console.log("Auth Success Detected. Refreshing Health...");
            this.checkHealth();
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        this.elements.refreshBtn.addEventListener('click', () => {
            this.state.countdown = 10; 
            this.fetchOrders();
            this.checkHealth();
        });

        if (this.elements.autoPrintToggle) {
            this.elements.autoPrintToggle.addEventListener('change', (e) => {
                this.toggleAutoPrint(e.target.checked);
            });
        }

        if (this.elements.autoRefreshToggle) {
            this.elements.autoRefreshToggle.addEventListener('change', (e) => {
                this.state.autoRefresh = e.target.checked;
                if (this.state.autoRefresh) {
                    this.startRefreshTimer();
                } else {
                    this.elements.refreshCountdown.textContent = '--';
                }
            });
        }

        document.querySelectorAll('.close-btn').forEach(btn => {
            btn.addEventListener('click', () => this.closeModal());
        });

        this.elements.modal.addEventListener('click', (e) => {
            if (e.target === this.elements.modal) this.closeModal();
        });

        // Silent Print Action
        const printBtn = document.getElementById('print-btn');
        if (printBtn) {
            printBtn.addEventListener('click', () => {
                const titleText = this.elements.modalTitle.textContent;
                const orderId = titleText.replace('Receipt #', '').trim();
                
                if (orderId) {
                    const originalText = printBtn.innerText;
                    printBtn.innerText = 'Printing...';
                    printBtn.disabled = true;

                    fetch(`api/spooler.php?action=print_receipt&order=${encodeURIComponent(orderId)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                printBtn.innerText = 'Sent!';
                                printBtn.classList.remove('btn-primary');
                                printBtn.classList.add('btn-success');
                                setTimeout(() => {
                                    this.closeModal();
                                    printBtn.innerText = originalText;
                                    printBtn.disabled = false;
                                    printBtn.classList.remove('btn-success');
                                    printBtn.classList.add('btn-primary');
                                }, 1500);
                            } else {
                                alert('Print failed: ' + data.message);
                                printBtn.innerText = 'Retry';
                                printBtn.disabled = false;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Network error printing receipt.');
                            printBtn.innerText = 'Retry';
                            printBtn.disabled = false;
                        });
                }
            });
        }
    },

    switchTab(tabName) {
        this.state.viewMode = tabName;
        this.elements.tabs.forEach(t => {
            if (t.dataset.tab === tabName) t.classList.add('active');
            else t.classList.remove('active');
        });
        
        // Reset activity based on tab
        if (tabName === 'printer') {
            this.updateActivity('Checking printer queue...', 'connecting');
        } else if (tabName === 'mailer') {
            this.updateActivity('Checking email queue...', 'connecting');
        } else {
            this.updateActivity('Ready', 'processing');
        }
        
        this.render();
    },

    startRefreshTimer() {
        if (this.refreshInterval) clearInterval(this.refreshInterval);
        this.state.countdown = 10;
        this.refreshInterval = setInterval(() => {
            if (!this.state.autoRefresh) return;
            this.state.countdown--;
            if (this.state.countdown <= 0) {
                this.fetchOrders(true);
                this.state.countdown = 10;
            }
            if (this.elements.refreshCountdown) this.elements.refreshCountdown.textContent = this.state.countdown;
        }, 1000);
    },

    updateClock() {
        if (this.elements.clock) {
            const now = new Date();
            this.elements.clock.textContent = now.toLocaleTimeString('en-US', { hour12: false });
        }
    },

    async checkHealth() {
        try {
            const res = await fetch('api/spooler.php?action=health_check');
            const data = await res.json();
            if (data.gmail === 'connected') {
                this.elements.gmailDot.classList.add('ok');
                this.elements.gmailDot.classList.remove('err');
            } else {
                this.elements.gmailDot.classList.add('err');
                this.elements.gmailDot.classList.remove('ok');
            }
            if (data.drive === 'connected') {
                this.elements.driveDot.classList.add('ok');
                this.elements.driveDot.classList.remove('err');
            } else {
                this.elements.driveDot.classList.add('err');
                this.elements.driveDot.classList.remove('ok');
            }
            this.elements.accountEmail.textContent = data.account || 'Unknown';
            if (data.gmail !== 'connected' || data.drive !== 'connected' || !data.token_exists) {
                this.elements.connectBtn.style.display = 'block';
            } else {
                this.elements.connectBtn.style.display = 'none';
            }
        } catch (e) {
            console.error("Health check failed", e);
        }
    },

    async fetchOrders(silent = false) {
        if (!silent) {
            this.state.isLoading = true;
            this.updateStatus('Scanning...', 'warning-color');
        }
        try {
            const res = await fetch(`api/orders.php?_=${Date.now()}`);
            const data = await res.json();
            if (data.status === 'ok') {
                this.state.orders = data.orders;
                if (data.autoprint !== undefined && this.elements.autoPrintToggle) {       
                    this.state.autoPrint = data.autoprint;
                    this.elements.autoPrintToggle.checked = this.state.autoPrint;
                }
                this.updateTabCounts();
                this.render();
                this.updateStatus('Live', 'success-color');
            } else {
                console.error(data.message);
                this.updateStatus('Error', 'danger-color');
            }
        } catch (err) {
            console.error(err);
            this.updateStatus('Network Error', 'danger-color');
        } finally {
            this.state.isLoading = false;
        }
    },

    updateTabCounts() {
        const pending = this.state.orders.filter(o => o.type === 'Cash Pending').length;   
        const paid = this.state.orders.filter(o => o.type === 'Paid' || o.payment_method === 'square').length;
        const voided = this.state.orders.filter(o => o.type === 'Void').length;
        const all = this.state.orders.length;
        const elPending = document.querySelector('.tab-pending .tab-count');
        const elPaid = document.querySelector('.tab-paid .tab-count');
        const elVoid = document.querySelector('.tab-void .tab-count');
        const elAll = document.querySelector('.tab-all .tab-count');
        if (elPending) elPending.textContent = `(${pending})`;
        if (elPaid) elPaid.textContent = `(${paid})`;
        if (elVoid) elVoid.textContent = `(${voided})`;
        if (elAll) elAll.textContent = `(${all})`;
    },

    async toggleAutoPrint(enabled) {
        this.state.autoPrint = enabled;
        try {
            const formData = new FormData();
            formData.append('status', enabled ? '1' : '0');
            await fetch('../admin/admin_set_autoprint.php', { method: 'POST', body: formData });
        } catch (e) {
            console.error('Failed to toggle auto print', e);
            if (this.elements.autoPrintToggle) this.elements.autoPrintToggle.checked = !enabled;
        }
    },

    updateStatus(text, colorVar) {
        if (!this.elements.status) return;
        this.elements.status.textContent = text;
        this.elements.status.style.color = '';
        if (colorVar) {
             const map = { 'success-color': '#00c853', 'warning-color': '#ffab00', 'danger-color': '#d50000' };
             this.elements.status.style.color = map[colorVar] || '#ccc';
        }
    },

    tickSpooler() {
        fetch('api/spooler.php?action=status')
            .then(r => r.json())
            .then(data => {
                this.updateBadge('printer', data.printer_count);
                this.updateBadge('mailer', data.mailer_count);
                const printerTab = document.querySelector('.tab-printer');
                if (data.alert_level === 'warning') {
                    printerTab.classList.add('alert-warning');
                    printerTab.classList.remove('alert-critical');
                } else if (data.alert_level === 'critical') {
                    printerTab.classList.add('alert-critical');
                    printerTab.classList.remove('alert-warning');
                } else {
                    printerTab.classList.remove('alert-warning', 'alert-critical');
                }
                if (this.state.viewMode === 'printer') this.renderPrinterQueue(data.printer_items, data.print_history);
                if (this.state.viewMode === 'mailer') this.renderMailerQueue(data.mailer_items, data.email_history);
            })
            .catch(err => console.error("Spooler status error", err));

        fetch('api/spooler.php?action=tick_printer')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log("Sent to printer: " + data.moved);
                    this.updateStatus('Printing: ' + data.moved, 'success-color');
                }
            })
            .catch(err => console.error("Spooler tick error", err));
            
        // MAIL WATCHDOG
        fetch('api/spooler.php?action=tick_mailer')
            .then(r => r.json())
            .then(data => {
                if (data.triggered && data.triggered.length > 0) {
                    console.log("Triggered stuck emails:", data.triggered);
                    this.updateStatus('Retrying Emails...', 'warning-color');
                }
            })
            .catch(err => console.error("Mailer tick error", err));
    },

    updateBadge(type, count) {
        const el = document.querySelector(`.tab-${type} .tab-count`);
        if (el) {
            el.innerText = `(${count})`;
            el.style.display = 'inline'; // Always show count? Or hide if 0? User said "Pending (0)" so always show.
            // Wait, pending/paid/void show (0). So I should show (0).
            el.style.display = 'inline'; 
        }
    },

    updateActivity(message, type = 'processing') {
        this.state.currentActivity = message;
        if (this.elements.activityText) {
            this.elements.activityText.textContent = message;
        }
        if (this.elements.activityIndicator) {
            // Remove all activity classes
            this.elements.activityIndicator.classList.remove('connecting', 'sending', 'uploading', 'processing');
            // Add the appropriate class
            this.elements.activityIndicator.classList.add(type);
        }
    },

    renderPrinterQueue(queueItems, historyItems) {
        const container = document.getElementById('orders-list');
        if (!container || this.state.viewMode !== 'printer') return;
        
        // Update activity indicator for printer tab
        if (queueItems && queueItems.length > 0) {
            this.updateActivity(`Processing ${queueItems.length} print${queueItems.length !== 1 ? 's' : ''}...`, 'uploading');
        } else {
            this.updateActivity('Printer ready', 'processing');
        }
        
        let html = '<div class="spooler-section">';
        html += '<h3 style="color:#ffab00; border-bottom:1px solid #333; padding-bottom:5px;">Active Print Queue</h3>';
        if (queueItems && queueItems.length > 0) {
            html += queueItems.map(item => `
                <div class="queue-item" style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #222; background:#111;">
                    <span style="font-family:monospace; color:#eee;">${item}</span>
                    <span class="badge badge-warning">Queued</span>
                </div>
            `).join('');
        } else {
            html += '<div class="empty-state" style="padding:15px; color:#666;">Queue is empty. Printer is hungry.</div>';
        }
        html += '<h3 style="color:#00c853; border-bottom:1px solid #333; padding-bottom:5px; margin-top:30px;">Recent Prints</h3>';
        if (historyItems && historyItems.length > 0) {
            html += historyItems.map(item => `
                <div class="history-item" style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #222;">
                    <div>
                        <div style="font-family:monospace; color:#ccc;">${item.file}</div>
                        <div style="font-size:11px; color:#666;">Order #${item.order_id} � ${new Date(item.timestamp * 1000).toLocaleTimeString()}</div>
                    </div>
                    <button onclick="App.handleSpoolAction('retry_print', '${item.file}')" class="btn btn-cash" style="padding:5px 12px; font-size:12px;">Reprint</button>
                </div>
            `).join('');
        } else {
            html += '<div class="empty-state" style="padding:15px; color:#666;">No print history yet today.</div>';
        }
        html += '</div>';
        container.innerHTML = html;
    },

    renderMailerQueue(queueItems, historyItems) {
        const container = document.getElementById('orders-list');
        if (!container || this.state.viewMode !== 'mailer') return;
        
        // Update activity indicator for mailer tab
        if (queueItems && queueItems.length > 0) {
            this.updateActivity(`Sending ${queueItems.length} email${queueItems.length !== 1 ? 's' : ''}...`, 'sending');
        } else {
            this.updateActivity('Email queue ready', 'processing');
        }
        
        let html = '<div class="spooler-section">';
        html += '<h3 style="color:#29b6f6; border-bottom:1px solid #333; padding-bottom:5px;">Sending Queue</h3>';
        if (queueItems && queueItems.length > 0) {
            html += queueItems.map(item => `
                <div class="queue-item" id="queue-item-${item}" style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #222; background:#111;">
                    <span style="font-family:monospace; color:#eee;">Order #${item}</span>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span class="badge badge-info" id="badge-${item}"><span class="spinner" style="width:12px;height:12px;border-width:2px;"></span> Sending...</span>
                        <button id="force-btn-${item}" onclick="App.forceMailerRetry('${item}')" class="btn btn-danger" style="padding:5px 12px; font-size:12px; background:#ff5252; border:1px solid #ff5252;">Force Send</button>
                    </div>
                </div>
            `).join('');
        } else {
            html += '<div class="empty-state" style="padding:15px; color:#666;">No emails pending.</div>';
        }
        html += '<h3 style="color:#aaa; border-bottom:1px solid #333; padding-bottom:5px; margin-top:30px;">Sent Archive (Today)</h3>';
        if (historyItems && historyItems.length > 0) {
            html += historyItems.map(item => `
                <div class="history-item" style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid #222;">
                    <div>
                        <div style="font-weight:bold; color:#ccc;">Order #${item.order_id}</div>
                        <div style="font-size:12px; color:#888;">${item.email}</div>
                        <div style="font-size:11px; color:#555;">Sent: ${new Date(item.time * 1000).toLocaleTimeString()}</div>
                    </div>
                    <button onclick="App.handleSpoolAction('retry_mail', '${item.order_id}')" class="btn btn-square" style="padding:5px 12px; font-size:12px;">Resend</button>
                </div>
            `).join('');
        } else {
            html += '<div class="empty-state" style="padding:15px; color:#666;">No emails sent yet today.</div>';
        }
        html += '</div>';
        container.innerHTML = html;
    },

    async forceMailerRetry(orderId) {
        const btn = document.getElementById(`force-btn-${orderId}`);
        const badge = document.getElementById(`badge-${orderId}`);
        const originalText = btn.innerText;
        
        // Remove spinner and show "Please wait..."
        if (badge) {
            badge.style.display = 'none';
        }
        
        btn.innerText = '⏳ Please wait...';
        btn.disabled = true;
        btn.style.background = '#ffb300';
        btn.style.color = '#000';
        btn.style.fontWeight = 'bold';
        
        try {
            const resp = await fetch(`/config/api/mail_queue.php?action=retry&order_id=${encodeURIComponent(orderId)}`);
            const data = await resp.json();
            
            if (data.status === 'success') {
                // Check if there was an error in the output
                if (data.output && data.output.includes('ERROR') || data.output.includes('Exception')) {
                    btn.innerText = `✗ Error: Check logs`;
                    btn.style.background = '#f44336';
                    btn.style.color = '#fff';
                    console.error(`Order ${orderId} gmailer output:`, data.output);
                    
                    setTimeout(() => {
                        if (badge) badge.style.display = '';
                        btn.innerText = originalText;
                        btn.disabled = false;
                        btn.style.background = '#ff5252';
                        btn.style.color = '#fff';
                        btn.style.fontWeight = 'normal';
                    }, 4000);
                } else {
                    btn.innerText = '✓ Sent! Refreshing...';
                    btn.style.background = '#4caf50';
                    btn.style.color = '#fff';
                    
                    // Wait a moment then refresh queue
                    await new Promise(resolve => setTimeout(resolve, 1500));
                    this.tickSpooler();
                    
                    // Button will be removed when queue refreshes
                }
            } else {
                btn.innerText = `✗ ${data.message || 'Error'}`;
                btn.style.background = '#f44336';
                btn.style.color = '#fff';
                
                // Restore after showing error
                setTimeout(() => {
                    if (badge) badge.style.display = '';
                    btn.innerText = originalText;
                    btn.disabled = false;
                    btn.style.background = '#ff5252';
                    btn.style.color = '#fff';
                    btn.style.fontWeight = 'normal';
                }, 3000);
            }
        } catch (err) {
            console.error('Force retry error:', err);
            btn.innerText = `✗ Network error`;
            btn.style.background = '#f44336';
            btn.style.color = '#fff';
            
            setTimeout(() => {
                if (badge) badge.style.display = '';
                btn.innerText = originalText;
                btn.disabled = false;
                btn.style.background = '#ff5252';
                btn.style.color = '#fff';
                btn.style.fontWeight = 'normal';
            }, 3000);
        }
    },

    async handleSpoolAction(action, target) {
        if (!confirm(`Are you sure you want to ${action.replace('_', ' ')} for ${target}?`)) return;
        const btn = event.target;
        const originalText = btn.innerText;
        btn.innerText = '...';
        btn.disabled = true;
        try {
            let url = '';
            if (action === 'retry_print') url = `api/spooler.php?action=retry_print&file=${encodeURIComponent(target)}`;
            if (action === 'retry_mail') url = `api/spooler.php?action=retry_mail&order_id=${encodeURIComponent(target)}`;
            const res = await fetch(url);
            const data = await res.json();
            if (data.status === 'success') {
                this.tickSpooler();
                btn.innerText = 'Done';
                setTimeout(() => { btn.innerText = originalText; btn.disabled = false; }, 2000);
            } else {
                alert('Error: ' + data.message);
                btn.innerText = 'Failed';
                btn.disabled = false;
            }
        } catch (e) {
            console.error(e);
            alert('Action failed (Network).');
            btn.innerText = 'Error';
            btn.disabled = false;
        }
    },

    async handleAction(action, orderId, btn = null, extraParams = {}) {
        if (action === 'void' && !confirm('Void this order?')) return;
        if (btn) { btn.disabled = true; btn.dataset.originalHtml = btn.innerHTML; btn.innerHTML = '<span class="spinner"></span>'; }
        try {
            const formData = new FormData();
            formData.append('order', orderId);
            formData.append('action', action);
            formData.append('autoprint', this.state.autoPrint ? '1' : '0');
            for (const key in extraParams) formData.append(key, extraParams[key]);
            await new Promise(resolve => setTimeout(resolve, 2000));
            await fetch('api/order_action.php', { method: 'POST', body: formData });
            await new Promise(resolve => setTimeout(resolve, 500));
            this.fetchOrders();
        } catch (e) {
            console.error(e);
            alert('Action failed.');
            if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.originalHtml || 'Retry'; }
        }
    },

    async handleSquare(orderId, amount, btn) {
        let cancelBtn = null;
        this.state.autoRefresh = false;
        if (this.elements.autoRefreshToggle) this.elements.autoRefreshToggle.checked = false;
        if (this.elements.refreshCountdown) this.elements.refreshCountdown.textContent = '--';
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Sending...';
        cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn btn-void action-btn';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.style.marginLeft = '8px';
        cancelBtn.style.display = 'none';
        btn.parentNode.appendChild(cancelBtn);
        try {
            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('order_id', orderId);
            formData.append('amount', amount);
            const res = await fetch('api/terminal.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') {
                const checkoutId = data.checkout_id;
                cancelBtn.style.display = '';
                cancelBtn.onclick = async () => {
                    cancelBtn.disabled = true;
                    cancelBtn.textContent = 'Cancelling...';
                    try {
                        const formData = new FormData();
                        formData.append('action', 'cancel');
                        formData.append('checkout_id', checkoutId);
                        await fetch('api/terminal.php', { method: 'POST', body: formData });
                    } catch (e) {}
                };
                this.pollSquare(checkoutId, orderId, btn, originalText, cancelBtn);        
            } else {
                alert('Square Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
                this.resumeRefresh();
            }
        } catch (e) {
            console.error(e);
            btn.disabled = false;
            btn.innerHTML = originalText;
            this.resumeRefresh();
        }
    },

    async handleQR(orderId, btn) {
        const ref = prompt('Enter QR Payment Reference (e.g. Venmo ID, CashApp Name):');   
        if (!ref) return;
        await this.handleAction('paid', orderId, btn, { payment_method: 'qr', transaction_id: ref });
    },

    pollSquare(checkoutId, orderId, btn, originalText, cancelBtn) {
        let attempts = 0;
        const maxAttempts = 100;
        const interval = setInterval(async () => {
            attempts++;
            const dots = '.'.repeat((attempts % 3) + 1);
            btn.innerHTML = `Waiting${dots}`;
            if (cancelBtn) cancelBtn.style.display = '';
            try {
                const formData = new FormData();
                formData.append('action', 'poll');
                formData.append('checkout_id', checkoutId);
                const res = await fetch('api/terminal.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') {
                    const status = data.terminal_status;
                    if (status === 'COMPLETED') {
                        clearInterval(interval);
                        btn.innerHTML = 'SUCCESS';
                        btn.className = 'btn btn-cash action-btn';
                        if (cancelBtn) cancelBtn.remove();
                        await this.handleAction('paid', orderId, null, { payment_method: 'square', transaction_id: data.payment_id });
                        setTimeout(() => { this.resumeRefresh(); }, 1000);
                    } else if (status === 'CANCELED' || status === 'FAILED') {
                        clearInterval(interval);
                        btn.innerHTML = 'FAILED';
                        btn.className = 'btn btn-void action-btn';
                        if (cancelBtn) cancelBtn.remove();
                        setTimeout(() => { btn.disabled = false; btn.innerHTML = originalText; btn.className = 'btn btn-square action-btn'; this.resumeRefresh(); }, 3000);
                    } else {
                        if (attempts > maxAttempts) {
                            clearInterval(interval);
                            btn.innerHTML = 'TIMEOUT';
                            if (cancelBtn) cancelBtn.remove();
                            setTimeout(() => { btn.disabled = false; btn.innerHTML = originalText; this.resumeRefresh(); }, 3000);
                        }
                    }
                }
            } catch (e) { console.error("Polling error", e); }
        }, 3000);
    },

    resumeRefresh() {
        this.state.autoRefresh = true;
        if (this.elements.autoRefreshToggle) this.elements.autoRefreshToggle.checked = true;
        this.startRefreshTimer();
    },

    async openReceipt(filename, orderId) {
        if (this.elements.modal) this.elements.modal.classList.add('open');
        if (this.elements.modalTitle) this.elements.modalTitle.textContent = `Receipt #${orderId}`;
        if (this.elements.receiptContent) this.elements.receiptContent.textContent = "Loading receipt data...";
        try {
            const res = await fetch(`api/receipt.php?file=${encodeURIComponent(filename)}`);
            const data = await res.json();
            if (data.status === 'ok') {
                this.elements.receiptContent.textContent = data.content;
            } else {
                this.elements.receiptContent.textContent = "Error: " + data.message;       
            }
        } catch (e) {
            if (this.elements.receiptContent) this.elements.receiptContent.textContent = "Network error fetching receipt.";
        }
    },

    closeModal() {
        if (this.elements.modal) this.elements.modal.classList.remove('open');
    },

        render() {
        if (this.state.viewMode === 'printer' || this.state.viewMode === 'mailer') {       
            if (!document.querySelector('.spooler-section')) {
                this.elements.list.innerHTML = `<div class="loading-state"><p>Loading ${this.state.viewMode} data...</p></div>`;
            }
            return;
        }

        let filteredOrders = [];
        if (this.state.viewMode === 'all') {
            filteredOrders = this.state.orders;
        } else if (this.state.viewMode === 'pending') {
            filteredOrders = this.state.orders.filter(o => o.type === 'Cash Pending');     
        } else if (this.state.viewMode === 'paid') {
            filteredOrders = this.state.orders.filter(o => o.type === 'Paid' || o.payment_method === 'square');
        } else if (this.state.viewMode === 'void') {
            filteredOrders = this.state.orders.filter(o => o.type === 'Void');
        }

        if (filteredOrders.length === 0) {
            this.elements.list.innerHTML = '<div class="loading-state"><p>No orders found.</p></div>';
            return;
        }

        let html = '';

        filteredOrders.forEach(order => {
            // Determine Styling
            let borderColor = '#444'; // Default
            if (order.type === 'Paid') borderColor = '#28a745'; // Green
            if (order.payment_method === 'square') borderColor = '#007bff'; // Blue
            if (order.type === 'Void') borderColor = '#dc3545'; // Red
            if (order.type === 'Cash Pending') borderColor = '#ffc107'; // Yellow

            // Station Icon
            const stationIcon = order.station === 'FS' ? '🔥' : '📷';
            const stationClass = order.station === 'FS' ? 'text-fire' : 'text-main';

            // Buttons
            const isPaidOrVoid = (order.type === 'Paid' || order.type === 'Void' || order.payment_method === 'square');
            let actionsHtml = '';

            if (!isPaidOrVoid) {
                actionsHtml += `
                    <button class="btn btn-square action-btn" data-action="square" data-id="${order.id}" data-amount="${order.cc_totaltaxed}">Square</button>
                    <button class="btn btn-cash action-btn" data-action="cash" data-id="${order.id}">Cash</button>
                    <button class="btn btn-void action-btn" data-action="void" data-id="${order.id}">Void</button>
                `;
            } else {
                actionsHtml += `<span class="status-pill ${order.type.toLowerCase()}">${order.type}</span>`;
            }
            actionsHtml += `<button class="btn btn-receipt view-receipt-btn" data-file="${order.filename}" data-id="${order.id}">Receipt</button>`;

            // Elapsed Time
            let elapsedHtml = '';
            if (order.timestamp) {
                const now = Math.floor(Date.now() / 1000);
                const diff = Math.floor((now - order.timestamp) / 60);
                let elapsedClass = 'elapsed-time';
                if (diff > 20) elapsedClass += ' late';
                elapsedHtml = `<span class="${elapsedClass}">${diff}m</span>`;
            }

            // HTML Structure (Flexbar)
            html += `
                <div class="order-bar" style="border-left: 5px solid ${borderColor};">
                    <div class="bar-section section-id">
                        <span class="station-icon ${stationClass}">${stationIcon}</span>
                        <span class="order-id">#${order.id}</span>
                    </div>
                    
                    <div class="bar-section section-time">
                        <span class="time-text">${order.time}</span>
                        ${elapsedHtml}
                    </div>

                    <div class="bar-section section-customer">
                        <span class="customer-email" title="${order.name}">${order.name || '<span class="muted">Unknown</span>'}</span>
                    </div>

                    <div class="bar-section section-total">
                        <span class="total-text">${Number(order.total).toFixed(2)}</span>
                    </div>

                    <div class="bar-section section-actions">
                        ${actionsHtml}
                    </div>
                </div>
            `;
        });

        this.elements.list.innerHTML = html;

        this.elements.list.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = btn.dataset.action;
                const id = btn.dataset.id;
                if (action === 'cash') this.handleAction('paid', id, btn);       
                if (action === 'void') this.handleAction('void', id, btn);       
                if (action === 'square') this.handleSquare(id, btn.dataset.amount, btn);
                if (action === 'qr') this.handleQR(id, btn);
            });
        });

        this.elements.list.querySelectorAll('.view-receipt-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.openReceipt(btn.dataset.file, btn.dataset.id);
            });
        });
    },
};

document.addEventListener('DOMContentLoaded', () => App.init());


