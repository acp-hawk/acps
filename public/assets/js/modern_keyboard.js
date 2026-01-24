(function() {
    const DOMAINS = ['@gmail.com', '@yahoo.com', '@outlook.com', '@hotmail.com', '@icloud.com'];
    const ROWS = [
        ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
        ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
        ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
        ['z', 'x', 'c', 'v', 'b', 'n', 'm', '.', '@', '-', '_']
    ];

    let activeInput = null;
    let isVisible = false;

    // --- HTML Structure ---
    function renderKeyboard() {
        const container = document.getElementById('virtualKeyboard');
        if (!container) return;

        let html = '';

        // Close Button
        const closeIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
        html += `<button class="mk-close-btn" data-action="close">${closeIcon}</button>`;

        // Shortcuts
        html += '<div class="mk-shortcuts">';
        DOMAINS.forEach(domain => {
            html += `<button class="mk-btn-shortcut" data-key="${domain}">${domain}</button>`;
        });
        html += '</div>';

        // Grid
        html += '<div class="mk-grid">';
        ROWS.forEach(row => {
            html += '<div class="mk-row">';
            row.forEach(char => {
                html += `<button class="mk-key" data-key="${char}">${char}</button>`;
            });
            html += '</div>';
        });

        // Action Row
        html += '<div class="mk-row" style="margin-top: 0.5rem;">';
        
        // Space
        html += `<button class="mk-key mk-key-space" data-action="space">SPACE</button>`;
        
        // Backspace (Lucide Delete Icon SVG)
        const deleteIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path><line x1="18" y1="9" x2="12" y2="15"></line><line x1="12" y1="9" x2="18" y2="15"></line></svg>`;
        html += `<button class="mk-key mk-key-action mk-key-delete" data-action="backspace">${deleteIcon}</button>`;
        
        // Submit (Lucide Check Icon SVG)
        const checkIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
        html += `<button class="mk-key mk-key-submit" data-action="submit">${checkIcon}</button>`;
        
        html += '</div>'; // End Action Row
        html += '</div>'; // End Grid

        container.innerHTML = html;
        attachEvents(container);
    }

    // --- Logic ---
    function attachEvents(container) {
        // Use touchstart for faster response on touch screens, click for mouse
        const eventType = 'click'; // keeping it simple, add 'touchstart' handling if needed for kiosk responsiveness

        // Keys & Shortcuts
        const buttons = container.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.addEventListener(eventType, (e) => {
                e.preventDefault();
                e.stopPropagation(); // Prevent losing focus if we were careful, but we might need to re-focus
                
                const key = btn.getAttribute('data-key');
                const action = btn.getAttribute('data-action');

                if (key) handleKey(key);
                else if (action) handleAction(action);
            });
        });
    }

    function handleKey(char) {
        if (!activeInput) return;
        const start = activeInput.selectionStart || activeInput.value.length;
        const end = activeInput.selectionEnd || activeInput.value.length;
        const val = activeInput.value;

        // Insert char
        activeInput.value = val.substring(0, start) + char + val.substring(end);
        
        // Move cursor
        const newPos = start + char.length;
        try {
            activeInput.setSelectionRange(newPos, newPos);
        } catch(e) {
            // Ignore for email types etc if they fail
        }
        
        activeInput.focus();
        // Trigger change event for listeners
        $(activeInput).trigger('change');
    }

    function handleAction(action) {
        if (action === 'close') {
            hide();
            return;
        }

        if (!activeInput) return;

        if (action === 'backspace') {
            const start = activeInput.selectionStart || activeInput.value.length;
            const end = activeInput.selectionEnd || activeInput.value.length;
            const val = activeInput.value;

            if (start === end && start > 0) {
                // Remove char before cursor
                activeInput.value = val.substring(0, start - 1) + val.substring(end);
                try {
                    activeInput.setSelectionRange(start - 1, start - 1);
                } catch(e) {}
            } else {
                // Remove selection
                activeInput.value = val.substring(0, start) + val.substring(end);
                try {
                    activeInput.setSelectionRange(start, start);
                } catch(e) {}
            }
        } 
        else if (action === 'space') {
            handleKey(' ');
        }
        else if (action === 'submit') {
            // Trigger the primary action for the current view
            // In pay.php context:
            if (activeInput.id === 'input-email') {
                if (window.handleEmailSubmit) window.handleEmailSubmit();
            } else if (['input-name', 'input-addr', 'input-city', 'input-zip'].includes(activeInput.id)) {
                // If we are in address view, maybe validate?
                // Or just hide keyboard?
                if (window.validateAddress) window.validateAddress();
            }
            hide(); // Hide keyboard after submit
        }
        
        // activeInput.focus(); // Do not refocus after submit
        $(activeInput).trigger('change');
    }

    // --- Visibility & Focus Management ---
    function show() {
        const el = document.getElementById('virtualKeyboard');
        if (el) {
            el.classList.add('active');

            // Toggle shortcuts based on input type
            const shortcuts = el.querySelector('.mk-shortcuts');
            if (shortcuts) {
                // Check if activeInput exists and is email-like
                const isEmail = activeInput && (
                    (activeInput.type && activeInput.type.toLowerCase() === 'email') || 
                    (activeInput.id && activeInput.id.indexOf('email') !== -1)
                );

                if (isEmail) {
                    shortcuts.style.display = 'flex';
                } else {
                    shortcuts.style.display = 'none';
                }
            }
        }
        document.body.classList.add('keyboard-open'); 
        isVisible = true;
    }

    function hide() {
        const el = document.getElementById('virtualKeyboard');
        if (el) el.classList.remove('active');
        document.body.classList.remove('keyboard-open'); // Remove class from body
        isVisible = false;
    }

    // --- Initialization ---
    window.initModernKeyboard = function() {
        renderKeyboard();

        // Attach to inputs
        $('input[type="text"], input[type="email"], input[type="number"]').on('focus', function() {
            activeInput = this;
            show();
        });

        // Hide when clicking outside? (Optional, maybe risky for kiosk)
        /*
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#virtualKeyboard') && !e.target.closest('input')) {
                hide();
            }
        });
        */
    };

    // Expose global API if needed
    window.ModernKeyboard = {
        show: show,
        hide: hide
    };

    // Compatibility Layer for acps.js legacy calls
    window.jsKeyboard = {
        hide: hide,
        init: function() { /* Already handled by ModernKeyboard init */ }
    };

    $(document).ready(function() {
        window.initModernKeyboard();
    });

})();
