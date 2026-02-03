// --- State ---
let state = {
    email: '',
    onsite: 'yes',
    address: { name:'', street:'', city:'', state:'', zip:'' },
    skipDelivery: false,
    total: 0,
    isCheckoutProcessing: false  // PREVENT CONCURRENT CHECKOUTS
};

// --- Navigation ---
function goToView(id) {
    $('.app-view').removeClass('active');
    $('#' + id).addClass('active');
    
    // Always hide keyboard on view change
    if(window.ModernKeyboard && ModernKeyboard.hide) {
        ModernKeyboard.hide();
    } else if(window.jsKeyboard && jsKeyboard.hide) {
        jsKeyboard.hide();
    }
}

function showLoader(msg) {
    $('#loader-text').text(msg || 'Processing...');
    $('#loader-overlay').addClass('visible');
}
function hideLoader() { $('#loader-overlay').removeClass('visible'); }

function showErrorModal(msg) {
    $('#modal-error-msg').html(msg);
    $('#modal-error').removeClass('hidden');
}

// --- Step 1: Email ---
function handleEmailSubmit() {
    const email = $('#input-email').val().trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!email || !emailRegex.test(email)) {
        showErrorModal('Please enter a valid<br>email address.');    
        return;
    }

    state.email = email;

    if (state.skipDelivery) {
        initPayment();
    } else {
        goToView('view-delivery');
    }
}

// --- Step 2: Delivery ---
function selectPickup() {
    state.onsite = 'yes';
    initPayment();
}

function selectPickupFromModal() {
    $('#modal-mail-confirm').addClass('hidden');
    selectPickup();
}

function selectMailConfirm() {
    $('#modal-mail-confirm').removeClass('hidden');
}

function confirmMail() {
    $('#modal-mail-confirm').addClass('hidden');
    state.onsite = 'no';
    goToView('view-address');
}

// --- Step 3: Address ---
function validateAddress() {
    const name = $('#input-name').val();
    const addr = $('#input-addr').val();
    const city = $('#input-city').val();
    const st   = $('#input-state').val();
    const zip  = $('#input-zip').val();

    if(!name || !addr || !city || !st || !zip) {
        showErrorModal("All address fields<br>are required.");       
        return;
    }

    showLoader('Validating Address...');

    $.ajax({
        type: 'POST',
        url: 'validate_address.php',
        data: { txtAddr: addr, txtCity: city, txtState: st, txtZip: zip },
        dataType: 'json',
        success: function(resp) {
            hideLoader();
            if (resp.status === 'success') {
                state.address.name = name;
                state.address.street = resp.validatedAddress.street; 
                state.address.city = resp.validatedAddress.city;     
                state.address.state = resp.validatedAddress.state;   

                let fullZip = resp.validatedAddress.zipCode;
                if(resp.validatedAddress.zipPlus4) fullZip += '-' + resp.validatedAddress.zipPlus4;
                state.address.zip = fullZip;

                initPayment();
            } else {
                showErrorModal(resp.message || "Address invalid.");  
            }
        },
        error: function() {
            hideLoader();
            showErrorModal("Validation error.<br>Please check connection.");
        }
    });
}

// --- Step 4: Payment ---
function initPayment() {
    goToView('view-payment');
    fetchQR();
    initCardReader();
}

function fetchQR() {
    const email = state.email;
    const total = window.acps_base_total;

    if (!email || total <= 0) {
        console.error("Cannot generate QR: Missing email or total.");
        return;
    }

    $('#qr-placeholder').html('<p style="color:black; font-weight:bold;">Generating QR...</p>');

    $.ajax({
        type: 'POST',
        url: 'cart_generate_qr.php',
        data: { email: email, total: total },
        dataType: 'json',
        success: function(resp) {
            if (resp.status === 'success') {
                $('#qr-placeholder').html('<img src="' + resp.qr_url + '" alt="QR Code" />');
                $('#square_order_id').val(resp.order_id);
                startQrPolling(resp.order_id);
            } else {
                $('#qr-placeholder').html('<p style="color:red;">QR Error</p>');
                showErrorModal(resp.message || "Could not generate QR code.");
            }
        },
        error: function(xhr, status, error) {
            console.error("QR Generation Error:", error);
            $('#qr-placeholder').html('<p style="color:red;">Connection Error</p>');
        }
    });
}

// Card Reader Logic
function initCardReader() {
    if (window.CardReader && !window._cardReaderInitialized) {       
        window._cardReaderInitialized = true;
        var reader = new CardReader();
        reader.observe(window);
        reader.cardRead(function(value) {
            processSwipe(value);
        });
    }
}

// --- NEW PROCESSORS USING CHECKOUT.PHP ---

function processSwipe(swipeData) {
    // PREVENT CONCURRENT CHECKOUT CALLS
    if (state.isCheckoutProcessing) {
        console.warn("Checkout already processing, ignoring duplicate swipe");
        return;
    }
    
    state.isCheckoutProcessing = true;
    showLoader('Processing Card...');

    try {
        const parts = swipeData.split("^");
        const nameParts = parts[1]?.split("/") || ["",""];
        const cardNum = parts[0]?.substring(1) || "";
        const lastPart = parts[2] || "";
        const expYear = lastPart.substring(0,2);
        const expMonth = lastPart.substring(2,4);

        const formData = new FormData();
        formData.append('payment_method', 'credit');
        formData.append('swipe_data', swipeData);
        formData.append('card_num', cardNum);
        formData.append('exp_month', expMonth);
        formData.append('exp_year', expYear);
        formData.append('email', state.email);
        formData.append('amount', window.acps_cc_totaltaxed); // Credit card includes tax and service fee
        
        // Add Common Fields
        appendCommonFields(formData);

        fetch('config/api/checkout.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            state.isCheckoutProcessing = false;  // UNLOCK
            if (data.status === 'success') {
                window.location.href = `thankyou.php?order=${data.order_id}&status=paid&onsite=${state.onsite}`;
            } else {
                hideLoader();
                showErrorModal(data.message || "Card Declined");
            }
        })
        .catch(err => {
            state.isCheckoutProcessing = false;  // UNLOCK
            hideLoader();
            console.error(err);
            showErrorModal("Payment Error. Try Again.");
        });

    } catch(e) {
        state.isCheckoutProcessing = false;  // UNLOCK
        hideLoader();
        showErrorModal("Card Read Error.<br>Please Try Again.");     
    }
}

function processCash() {
    // PREVENT CONCURRENT CHECKOUT CALLS
    if (state.isCheckoutProcessing) {
        console.warn("Checkout already processing, ignoring duplicate cash submission");
        return;
    }
    
    state.isCheckoutProcessing = true;
    showLoader('Creating Order...');
    
    // Cash Amount (Base only, no tax)
    const baseAmt = window.acps_amount_without_tax;
    
    const formData = new FormData();
    formData.append('payment_method', 'cash');
    formData.append('amount', baseAmt); // Cash pending uses base amount only
    formData.append('email', state.email);
    
    appendCommonFields(formData);

    fetch('config/api/checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        state.isCheckoutProcessing = false;  // UNLOCK
        if (data.status === 'success') {
            window.location.href = `thankyou.php?order=${data.order_id}&status=pending&onsite=${state.onsite}`;
        } else {
            hideLoader();
            showErrorModal(data.message || "Order Error");
        }
    })
    .catch(err => {
        state.isCheckoutProcessing = false;  // UNLOCK
        hideLoader();
        console.error(err);
        showErrorModal("Connection Error.");
    });
}

function appendCommonFields(formData) {
    formData.append('delivery_method', state.onsite === 'yes' ? 'pickup' : 'mail');
    formData.append('name', state.address.name);
    formData.append('address', state.address.street);
    formData.append('city', state.address.city);
    formData.append('state', state.address.state);
    formData.append('zip', state.address.zip);
}

function debugSwipe() {
    const debugData = '%B4111111111111111^SMITH/PAUL^25121010000000000000?';
    processSwipe(debugData);
}

// QR Polling - Poll Square order status and trigger checkout when paid
function startQrPolling(squareOrderId) {
    if (!squareOrderId) {
        console.error("No Square order ID provided for polling");
        return;
    }
    
    let pollingInterval = null;
    let isProcessing = false;  // LOCAL flag for this polling instance
    let pollCount = 0;
    const maxPolls = 600; // 10 minutes at 1-second intervals

    async function checkPaymentStatus() {
        // PREVENT CONCURRENT CHECKOUTS AT GLOBAL LEVEL
        if (state.isCheckoutProcessing || isProcessing || !squareOrderId || pollCount >= maxPolls) return;
        
        pollCount++;

        try {
            // Poll the Square order status via our API
            const response = await fetch(`config/api/check_square_order.php?order_id=${encodeURIComponent(squareOrderId)}`, {
                method: 'GET',
                cache: 'no-cache',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) return;

            const data = await response.json();
            
            // Check if payment was received
            if (data.status === 'success' && data.is_paid) {
                // SECONDARY GUARD: Check if another concurrent tick already proceeding
                if (state.isCheckoutProcessing || isProcessing) {
                    console.warn("Checkout already in progress, ignoring duplicate payment detection");
                    return;
                }
                
                // LOCK BOTH GLOBAL AND LOCAL FLAGS BEFORE CLEARING INTERVAL
                isProcessing = true;
                state.isCheckoutProcessing = true;
                
                clearInterval(pollingInterval);
                console.log("QR Payment confirmed for Square order " + squareOrderId);
                showLoader("Processing QR Payment...");

                // Call checkout with square_token to create order number
                const formData = new FormData();
                formData.append('payment_method', 'qr');
                formData.append('email', state.email);
                formData.append('amount', (data.total_amount / 100)); // Square returns cents, convert to dollars
                formData.append('square_token', squareOrderId); // Pass Square order ID for verification
                
                appendCommonFields(formData);

                fetch('config/api/checkout.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    state.isCheckoutProcessing = false;  // UNLOCK GLOBAL
                    if (data.status === 'success') {
                        // Display big yellow order number
                        showOrderNumber(data.order_id);

                        // Force clear cart via dedicated AJAX call for reliability
                        $.post('cart_action.php?action=clear');

                        setTimeout(() => {
                            window.location.href = `thankyou.php?order=${data.order_id}&status=paid&onsite=${state.onsite}`;
                        }, 3000);
                    } else {
                        hideLoader();
                        showErrorModal(data.message || "Checkout failed");
                    }
                })
                .catch(err => {
                    state.isCheckoutProcessing = false;  // UNLOCK GLOBAL
                    hideLoader();
                    console.error("Checkout error:", err);
                    showErrorModal("Connection error during checkout");
                });
            }
        } catch (error) {
            console.error("QR polling error:", error);
        }
    }

    // Poll every 1 second
    pollingInterval = setInterval(checkPaymentStatus, 1000);
}

function showOrderNumber(orderId) {
    // Display big yellow order number on screen
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        font-family: Arial, sans-serif;
    `;
    
    const numberDisplay = document.createElement('div');
    numberDisplay.style.cssText = `
        font-size: 150px;
        font-weight: bold;
        color: #FFD700;
        text-shadow: 3px 3px 6px rgba(0,0,0,0.8);
    `;
    numberDisplay.textContent = orderId;
    
    overlay.appendChild(numberDisplay);
    document.body.appendChild(overlay);
}


// Init Logic
$(document).ready(function() {
    if (typeof window.acps_skip_delivery !== 'undefined') {
        state.skipDelivery = window.acps_skip_delivery;
    }
    if (typeof window.acps_total !== 'undefined') {
        state.total = window.acps_total;
    }

    if(window.jsKeyboard) {
        jsKeyboard.init("virtualKeyboard");
    }

    if (window.acps_is_retry) {
        state.email = window.acps_retry_email || '';
        state.onsite = window.acps_retry_onsite || 'yes';
        state.address.name = window.acps_retry_name || '';
        state.address.street = window.acps_retry_addr || '';
        state.address.city = window.acps_retry_city || '';
        state.address.state = window.acps_retry_state || '';
        state.address.zip = window.acps_retry_zip || '';
        setTimeout(function() { initPayment(); }, 100);
    }
});
