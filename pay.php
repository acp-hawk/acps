<?php
//*********************************************************************//
// AlleyCat PhotoStation - Unified Checkout & Payment (pay.php)
// Consolidates: checkout.php -> checkout_mailing.php -> cart_process.php
// Designed for Full-Screen Shadowbox / Modal
//*********************************************************************//

// --- 1. SETUP & INIT ---
require_once __DIR__ . '/vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Silently ignore
}

require_once "admin/config.php";
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('shopping_cart.class.php');
$Cart = new Shopping_Cart('shopping_cart');

// --- Check for retry mode ---
$isRetry = isset($_GET['retry']) && $_GET['retry'] == '1';
$retryEmail = $isRetry && isset($_SESSION['retry_email']) ? $_SESSION['retry_email'] : '';
$retryOnsite = $isRetry && isset($_SESSION['retry_onsite']) ? $_SESSION['retry_onsite'] : 'yes';
$retryName = $isRetry && isset($_SESSION['retry_name']) ? $_SESSION['retry_name'] : '';
$retryAddr = $isRetry && isset($_SESSION['retry_addr']) ? $_SESSION['retry_addr'] : '';
$retryCity = $isRetry && isset($_SESSION['retry_city']) ? $_SESSION['retry_city'] : '';
$retryState = $isRetry && isset($_SESSION['retry_state']) ? $_SESSION['retry_state'] : '';
$retryZip = $isRetry && isset($_SESSION['retry_zip']) ? $_SESSION['retry_zip'] : '';

// --- 2. GATHER CART DATA ---
$queryString = $_SERVER['QUERY_STRING']; 
parse_str($queryString, $queryString);

// Initial Total from GET (passed from gallery)
$thisTotal = isset($_GET['amt']) ? floatval($_GET['amt']) : 0.00;

// Analyze Cart Contents (Prints vs Email)
$emlCount = 0;
$otherCount = 0;
foreach ($Cart->items as $order_code => $quantity) {
    if ($quantity < 1) continue;
    list($prod_code, $photo_id) = explode('-', $order_code);
    if (trim($prod_code) == 'EML') {
        $emlCount += $quantity;
    } else {
        $otherCount += $quantity;
    }
}

// Logic: If ALL items are emails (no physical prints), skip delivery & address steps
$skipDelivery = ($emlCount > 0 && $otherCount == 0);

// After cart analysis add this guard
$totalItems = $emlCount + $otherCount;
$invalid_order = false;
if ($thisTotal > 0 && $totalItems == 0) {
    // Someone passed an amount but cart is empty — mark as invalid to prevent phantom orders
    error_log("pay.php: invalid request — amount={$thisTotal} but cart empty");
    $invalid_order = true;
}

// --- 3. PREPARE PAYMENT DATA (For Step 4) ---
// Note: We calculate this early to generate QR code if needed, but display later
// cart_process.php logic: Input is Tax Inclusive
$amount_with_tax = $thisTotal;
$amount_without_tax = $amount_with_tax / 1.0675;
$tax = $amount_with_tax - $amount_without_tax;
$surcharge = $amount_without_tax * 0.035; // 2.9% fee
$cc_total = $amount_without_tax * 1.035;
$cc_totaltaxed = $cc_total * 1.0675;
// $cc_tax = $cc_total * 0.0675; // Unused in display usually

// Generate Order ID atomically if valid
$qr_code_url = null;
$squareOrderId = null;
$orderID = "";

if (!$invalid_order) {
    // existing tax/surcharge/calculation above already computed $cc_totaltaxed
    if ($cc_totaltaxed > 0) {
        $dirname = "photos/";
        $date_path = date('Y/m/d');
        $filename = $dirname . $date_path . "/orders.txt";

        // NOTE: Order ID generation has been CENTRALIZED to config/api/checkout.php
        // Do NOT increment counter here - it will be done by checkout.php when order is finalized
        // This prevents duplicate counter increments and order number skipping
        $orderID = null; // Will be obtained from checkout.php response
        // (Optional) generate QR / Square link here using $orderID and email later when available
    }
}

// New calculation
$savings = $cc_totaltaxed - $amount_without_tax;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout | AlleyCat Photo</title>
    
    <link rel="stylesheet" href="/public/assets/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="/public/assets/css/jsKeyboard.css"> OLD KEYBOARD -->
    <link rel="stylesheet" href="/public/assets/css/modern_keyboard.css"> <!-- NEW KEYBOARD -->
    <link rel="stylesheet" href="/public/assets/css/acps.css"> <!-- Master Styles -->
    
    <!-- Pass PHP vars to JS -->
    <script>
        window.acps_amount_without_tax = <?php echo json_encode($amount_without_tax); ?>;
        window.acps_cc_totaltaxed = <?php echo json_encode($cc_totaltaxed); ?>;
        window.acps_skip_delivery = <?php echo $skipDelivery ? 'true' : 'false'; ?>;
        window.acps_total = <?php echo json_encode($amount_with_tax); ?>;
        window.acps_base_total = <?php echo json_encode($thisTotal); ?>;
        window.acps_is_retry = <?php echo $isRetry ? 'true' : 'false'; ?>;
        window.acps_invalid_order = <?php echo $invalid_order ? 'true' : 'false'; ?>;
        window.acps_retry_email = <?php echo json_encode($retryEmail); ?>;
        window.acps_retry_onsite = <?php echo json_encode($retryOnsite); ?>;
        window.acps_retry_name = <?php echo json_encode($retryName); ?>;
        window.acps_retry_addr = <?php echo json_encode($retryAddr); ?>;
        window.acps_retry_city = <?php echo json_encode($retryCity); ?>;
        window.acps_retry_state = <?php echo json_encode($retryState); ?>;
        window.acps_retry_zip = <?php echo json_encode($retryZip); ?>;
    </script>
</head>
<body>

<div id="pay-app">
    
    <!-- ================= Step 1: EMAIL ================= -->
    <div id="view-email" class="app-view active">
        <div class="logo-section" style="margin-bottom: 3rem;">
            <img src="/public/assets/images/alley_logo_sm.png" alt="Alley Cat Photo" width="250">
        </div>

        <h1>PLEASE ENTER YOUR EMAIL</h1>
        
        <div class="form-container" style="text-align: center;">
            <p style="color:#999; margin-bottom:1.5rem; font-size:1.2rem;">
                Used to send your receipt and any digital photos.
            </p>

            <div class="form-group">
                <input type="email" id="input-email" class="form-input" placeholder="name@example.com" style="text-align:center; font-size: 2rem;">
            </div>

            <div class="form-actions" style="justify-content: center; gap: 1.5rem;">
                <button type="button" class="btn-action" onclick="location.href='/'">RETURN TO GALLERY</button>
                <button type="button" class="btn-action" onclick="handleEmailSubmit()">CONTINUE</button>
            </div>
        </div>
        <!-- <div style="margin-top: 2rem;">
            <a href="/" style="color: #666; text-decoration: none; font-size: 1.2rem;">CANCEL ORDER</a>
        </div> -->
    </div>


    <!-- ================= Step 2: DELIVERY (Pickup/Mail) ================= -->
    <div id="view-delivery" class="app-view">
        <h1>GET YOUR PHOTOS</h1>
        
        <div style="width: 100%; max-width: 800px;">
            <!-- Pick Up Now -->
            <button type="button" class="btn-choice btn-green" onclick="selectPickup()">
                <span class="btn-title">PICK UP NOW</span>
                <span class="btn-subtitle">READY IN MINUTES</span>
                <span class="btn-sub-detail">Get your photos today</span>
            </button>

            <!-- Mail To Me -->
            <button type="button" class="btn-choice btn-white" onclick="selectMailConfirm()">
                <span class="btn-title">MAIL TO ME</span>
                <span class="btn-subtitle">2–3 WEEKS</span>
                <span class="btn-sub-detail">Get them later</span>
            </button>

            <div class="footer-text">Most guests choose Pick Up Now</div>
            
            <div class="form-actions" style="justify-content: center; margin-top: 3rem !important;">
                <button class="btn-action" style="border-color: #444; color: #666;" onclick="goToView('view-email')">BACK</button>
            </div>
        </div>
    </div>
    
    <!-- Modal: Mail Confirmation -->
    <div id="modal-mail-confirm" class="modal-overlay hidden">
        <div class="modal-box">
            <div class="modal-text">
                Just checking —<br><br>
                Mailed photos arrive in <span style="color:#fff">2–3 weeks</span>.<br>
                <span class="modal-highlight">Pick Up Now gets them today.</span>
            </div>
            <button type="button" class="btn-choice btn-green" onclick="selectPickupFromModal()">
                <span class="btn-title" style="font-size: 2rem;">GET THEM TODAY</span>
            </button>
            <button type="button" class="btn-choice btn-white" style="padding: 1rem; margin-bottom: 0;" onclick="confirmMail()">
                <span class="btn-subtitle" style="margin:0; font-size: 1.2rem;">Continue with Mail</span>
            </button>
        </div>
    </div>

    <!-- Modal: Generic Error -->
    <div id="modal-error" class="modal-overlay hidden" style="z-index: 3000;">
        <div class="modal-box" style="border-color: #ff0000; box-shadow: 0 0 50px rgba(153, 0, 0, 0.6);">
            <div class="modal-text" id="modal-error-msg">
                Error Message Here
            </div>
            <button type="button" class="btn-action" onclick="$('#modal-error').addClass('hidden')" style="width: 100%; font-size: 1.5rem; border-color: #ff0000; color: #ff0000;">
                OKAY
            </button>
        </div>
    </div>


    <!-- ================= Step 3: ADDRESS (Only if Mail) ================= -->
    <div id="view-address" class="app-view">
        <h1>ENTER MAILING ADDRESS</h1>
        
        <div class="form-container">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" id="input-name" class="form-input" placeholder="Your Name">
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" id="input-addr" class="form-input" placeholder="Street Address">
            </div>
            <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" id="input-city" class="form-input" placeholder="City">
            </div>
            
            <div class="form-row-flex">
                <div class="form-group col-state">
                    <label class="form-label">State</label>
                    <input type="text" id="input-state" class="form-input" placeholder="ST" maxlength="2" style="text-align:center;">
                </div>
                <div class="form-group col-zip">
                    <label class="form-label">Zip Code</label>
                    <input type="text" id="input-zip" class="form-input" placeholder="12345" maxlength="10">
                </div>
            </div>

            <div class="form-actions" style="justify-content: center; gap: 1.5rem;">
                <button type="button" class="btn-action" onclick="goToView('view-delivery')">BACK</button>
                <button type="button" class="btn-action" onclick="validateAddress()">CONTINUE</button>
            </div>
        </div>
        <!-- Note: Keyboard logic uses #virtualKeyboard from Step 1, moved if needed or fixed at bottom -->
    </div>


    <!-- ================= Step 4: PAYMENT (Swipe/QR) ================= -->
    <div id="view-payment" class="app-view">
        
        <div class="payment-container">
            <!-- Left: QR Code -->
            <div class="payment-left">
                <p class="scan-text" style="color:#eee; font-size:1.2rem; margin-bottom: 1rem;">scan to pay with mobile</p>
                <div id="qr-placeholder" class="qr-box">
                    <?php if ($qr_code_url): ?>
                        <img src="<?php echo $qr_code_url; ?>" alt="QR Code" />
                    <?php else: ?>
                        <p style="color:black; font-weight:bold;">Loading...</p>
                    <?php endif; ?>
                </div>
                <img src="/public/assets/images/pay_icons_250.png" alt="Icons" style="width: 250px; margin-top: 1rem;">
            </div>
            
            <!-- Right: Totals & Actions -->
            <div class="payment-right">
                <div style="text-align: center;">
                    <div class="total-label">TOTAL</div>
                    <div class="total-display">$<?php echo number_format($cc_totaltaxed, 2); ?></div>
                    <div class="sub-details">Includes NC Sales Tax  &amp; $<?php echo number_format($surcharge, 2); ?> Transaction Fee</div>
                </div>

                <div class="pay-actions-fullwidth">
                    <div style="text-align:center; color:#fff; font-size:2.4rem; margin-bottom:40px; text-transform:uppercase; font-weight:bold; line-height: 1.1;">
                        PAY CASH HERE AND SAVE <span style="color:#6F0; font-size:2.4rem;">$<?php echo number_format($savings, 2); ?></span>
                    </div>
                    <button type="button" class="big-pay-btn" id="cashPayBtn" onclick="processCash()">
                        <div class="big-pay-main"><span class="fa fa-money-bill-wave"></span> PAY CASH AT COUNTER</div>
                        <div class="big-pay-sub">SCAN QR TO LEFT TO PAY ON PHONE</div>
                    </button>
                    <button type="button" class="big-cancel-btn" onclick="location.reload()">
                        <div class="big-cancel-main"><span class="fa fa-times-circle"></span> CANCEL</div>
                    </button>
                </div>
<!-- Font Awesome for icons (add to <head> if not present) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


            </div>
        </div>
    </div>

    <!-- Hidden Form for Final Submission -->
    <div id="virtualKeyboard"></div>
</div>

<!-- Loader -->
<div id="loader-overlay">
    <img src="/public/assets/images/loader.gif" width="150">
    <div class="loader-msg" id="loader-text">Processing...</div>
</div>

<!-- Hidden Form for Final Submission -->
<!-- FIXED: Changed action to config/api/checkout.php (new centralized API) -->
<form id="frmFinal" method="post" action="config/api/checkout.php">
    <!-- Variables populated by JS -->
    <input type="hidden" name="email" id="final-email">
    <input type="hidden" name="delivery_method" id="final-onsite" value="pickup">
    <input type="hidden" name="amount" value="<?php echo $thisTotal; ?>">
    <input type="hidden" name="name" id="final-name">
    <input type="hidden" name="address" id="final-addr">
    <input type="hidden" name="city" id="final-city">
    <input type="hidden" name="state" id="final-state">
    <input type="hidden" name="zip" id="final-zip">
    <input type="hidden" name="payment_method" value="cash">
    
    <!-- Swipe Data -->
    <input type="hidden" name="txtSwipeData" id="txtSwipeData">
    <input type="hidden" name="txtFname" id="txtFname">
    <input type="hidden" name="txtLname" id="txtLname">
    <input type="hidden" name="txtCardNum" id="txtCardNum">
    <input type="hidden" name="txtExpMonth" id="txtExpMonth">
    <input type="hidden" name="txtExpYear" id="txtExpYear">
    
    <!-- QR Data -->
    <input type="hidden" name="is_qr_payment" id="is_qr_payment" value="0">
    <input type="hidden" name="square_order_id" id="square_order_id" value="<?php echo htmlspecialchars($squareOrderId ?? ''); ?>">
</form>

<!-- Scripts -->
<script src="/public/assets/js/vendor/jquery-1.9.1.min.js"></script>
<!-- <script src="/public/assets/js/jsKeyboard.js?v=<?php echo time(); ?>"></script> -->
<script src="/public/assets/js/modern_keyboard.js?v=<?php echo time(); ?>"></script>
<script src="/public/assets/js/CardReader.js"></script>
<script src="/public/assets/js/acps.js?v=<?php echo time(); ?>"></script> <!-- Master JS -->


<script>
// On-screen keyboard detection for kiosk (PC full-screen)
(function() {
    let clickedOnKeyboard = false;
    document.addEventListener('mousedown', function(e) {
        if (e.target.closest('#virtualKeyboard')) {
            clickedOnKeyboard = true;
        } else {
            clickedOnKeyboard = false;
        }
    }, true);
    document.addEventListener('focusin', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            document.body.classList.add('keyboard-open');
        }
    });
    document.addEventListener('focusout', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            setTimeout(function() {
                if (clickedOnKeyboard) {
                    return;
                }
                if (!document.querySelector('input:focus, textarea:focus')) {
                    document.body.classList.remove('keyboard-open');
                    if (window.ModernKeyboard && window.ModernKeyboard.hide) {
                        window.ModernKeyboard.hide();
                    }
                }
            }, 100);
        }
    });
})();


// Lock cash/pay button and show spinner, then call real processCash
var _originalProcessCash = null;

// Capture the original function immediately if it exists
if (typeof window.processCash === 'function') {
    _originalProcessCash = window.processCash;
}

window.processCash = function() {
    var btn = document.getElementById('cashPayBtn');
    if (btn) {
        // Stop if already disabled (double-click protection)
        if (btn.disabled || btn.getAttribute('disabled') === 'disabled') {
            console.warn("Cash pay button already disabled, ignoring duplicate click");
            return;
        }

        // Immediate UI Lock
        btn.disabled = true;
        btn.setAttribute('disabled', 'disabled');
        btn.onclick = null; // Kill property handler
        btn.removeAttribute('onclick'); // Kill attribute handler
        
        // Show Spinner
        btn.innerHTML = '<div class="big-pay-main"><span class="fa fa-spinner fa-spin"></span> Processing...</div><div class="big-pay-sub">Please wait</div>';

        // Call original logic
        if (typeof _originalProcessCash === 'function') {
            _originalProcessCash();
        } else {
            console.error("Original processCash function not found.");
            // Fallback: try to find it in case of race condition (unlikely with sync script tags)
            if (typeof window.acps_base_total !== 'undefined') {
               // We could try to reconstruct the redirect here if desperate, 
               // but it's better to rely on the loaded file.
            }
        }
    }
};
</script>

<script>
// Block UI if server flagged an invalid order (amount > 0 but cart empty)
if (window.acps_invalid_order) {
    document.addEventListener('DOMContentLoaded', function() {
        // Disable pay buttons
        var btn = document.getElementById('cashPayBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="big-pay-main"><span class="fa fa-exclamation-triangle"></span> Error</div><div class="big-pay-sub">Order invalid — please return to gallery</div>';
        }
        // Show modal if present
        var modal = document.getElementById('modal-error');
        if (modal) {
            document.getElementById('modal-error-msg').textContent = 'Invalid order: amount present but no items in cart. Please return to gallery.';
            modal.classList.remove('hidden');
        }
    });
}
</script>

</body>
</html>