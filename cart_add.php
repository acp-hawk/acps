<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('shopping_cart.class.php');
$Cart = new Shopping_Cart('shopping_cart');

$thisPhoto = $_GET['p'] ?? "0001";
$photoID = basename($thisPhoto, ".jpg");

$fourby=$fiveby=$eightby=$email=0;
$isEdit = false;
foreach ($Cart->getItems() as $code=>$q){
	list($prod,$pid)=explode('-',$code);
	if($pid==$photoID){
		if($prod=='4x6')$fourby=$q;
		if($prod=='5x7')$fiveby=$q;
		if($prod=='8x10')$eightby=$q;
		if($prod=='EML')$email=$q;
		if($q > 0) $isEdit = true;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add to Cart Modal</title>
    <!-- Google Fonts for the requested 'Poppins' look -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --color-primary: #00e676; /* Vibrant Green */
            --color-promo: #ff3300;   /* Red for Promo Box Border/Text */
            --color-bundle: #ffcc00;  /* Specific Yellow */
            --bg-dark: #0a0a0a;
            --bg-panel: #141414;
            --bg-card: #1f1f1f;
            --border-color: #333;
            --font-stack: 'Poppins', sans-serif;
            --btn-size: 44px;
        }

        /* Basic Resets & Scrollbar */
        * { box-sizing: border-box; }
        
        body {
            background: #000;
            margin: 0;
            padding: 0;
            font-family: var(--font-stack);
            color: #fff;
            overflow: hidden;
        }



        /* --- Layout --- */
        .cart-modal-wrap {
            display: flex !important;
            flex-direction: row !important;
            height: 80vh !important;
            width: 100%;
            padding: 0.5rem;
            box-sizing: border-box;
            gap: 0.5rem;
            justify-content: flex-start;
            align-items: stretch;
        }

        /* Controls Panel (Left) */
        .cart-modal-panel-left {
            flex: 0 0 40% !important;
            padding: 0.5rem;
            background: var(--bg-panel);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
        }

        /* Image Panel (Right) */
        .cart-modal-panel-right {
            flex: 0 0 60% !important;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            min-height: 400px;
        }

        .cart-modal-panel-right img {
            max-width: 95%;
            max-height: 95%;
            width: auto;
            height: auto;
            object-fit: contain;
            box-shadow: 0 0 30px rgba(0,0,0,0.5);
        }

        /* --- Promo Box --- */
        .promo-box {
            border: 1px solid var(--color-promo); /* Red Border */
            background: #181818;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .promo-title {
            color: var(--color-promo); /* Red Text */
            font-weight: 700;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.2rem;
        }
        .cart-note-text {
            display: block;
            font-size: 0.8rem;
            opacity: 0.8;
            text-transform: none;
            letter-spacing: 0;
            font-weight: 400;
            color: #ccc !important;
            margin-bottom: 0.8rem;
            font-style: italic;
        }
        .promo-list {
            margin: 0;
            padding-left: 0;
            list-style: none;
            /* Changed to match the bundle text style */
            color: var(--color-bundle); 
            font-weight: 600;
            font-size: 0.85rem;
            line-height: 1.6;
            letter-spacing: 0.5px;
        }
        .promo-list li::before {
            content: "•";
            color: var(--color-promo);
            font-weight: bold;
            display: inline-block; 
            width: 1em;
            margin-left: 0;
        }

        /* --- Digital Add-on Box --- */
        .digital-addon-box {
            background: #181818; /* Matched to Promo Box background */
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }
        .digital-addon-box.active {
            border-color: var(--color-primary);
            /* Keeping background consistent #181818 or slightly tinted if active? 
               User requested same background as Discount one. */
            background: #181818; 
            box-shadow: 0 0 20px rgba(0, 230, 118, 0.1);
        }
        .digital-addon-content {
            display: flex;
            align-items: center;
            width: 100%;
            gap: 15px;
        }
        .digital-addon-icon {
            color: var(--color-primary);
            display: flex;
            align-items: center;
        }
        .digital-addon-text {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .digital-addon-text strong {
            font-size: 1.15rem;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .digital-addon-text small {
            /* Changed to match bundle text style */
            color: var(--color-bundle);
            font-weight: 600;
            font-size: 0.85rem;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }
        .digital-addon-price {
            text-align: right;
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--color-primary);
            margin-top: 0.5rem;
            height: 1.5rem;
        }
        .price-blink {
            animation: text-flash 0.5s ease-out;
        }
        @keyframes text-flash {
            0% { opacity: 0; transform: scale(1.2); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* --- Prints Grid --- */
        .v2-section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border-bottom: 1px solid #333;
            padding-bottom: 8px;
        }
        .v2-prints-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 0.5rem;
        }
        .v2-print-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.1rem 0.1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: 0.2s;
            position: relative;
        }
        .v2-print-card:hover {
            border-color: #666;
            background: #252525;
        }
        
        .v2-print-header {
            width: 90%;
            display: flex;
            flex-direction: row; 
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
            padding: 0 2px;
        }
        .v2-p-label {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }
        .v2-p-price {
            font-size: 1.2rem;
            color: var(--color-primary);
            font-weight: 700;
            margin: 0;
        }
        .v2-p-bundle {
            font-size: 0.75rem;
            color: var(--color-bundle);
            margin-bottom: 8px;
            height: 1.2em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 100%;
            text-align: center;
        }

        /* Kiosk Qty Controls */
        .v2-qty-compact {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 0; 
            width: 100%;
            padding-bottom: 4px;
        }
        
        .v2-btn-mini {
            width: var(--btn-size);
            height: var(--btn-size);
            border-radius: 8px; 
            background: #000000;
            color: #fff;
            border: 1px solid #9d0000;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex; 
            align-items: center; 
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .v2-btn-mini:active {
            transform: translateY(2px);
            box-shadow: none;
        }
        .v2-btn-mini:hover { 
            background: #444; 
            border-color: #9c0000;
        }
        .v2-btn-mini.add:hover {
            background: #00331a;
            border-color: var(--color-primary);
            color: var(--color-primary);
        }
        
        .v2-input-mini {
            width: 50px;
            height: var(--btn-size);
            background: #111;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: #fff;
            font-size: 1.1rem;
            text-align: center;
            font-weight: 600;
            padding: 0;
            font-family: var(--font-stack);
            margin: 0 2px;
        }
        .v2-input-mini:focus { 
            outline: none; 
            border-color: var(--color-primary);
        }

        /* --- Footer --- */
        .v2-footer {
            margin-top: auto;
            padding-top: 0.5rem;
            border-top: 2px solid var(--border-color);
        }
        .v2-subtotal {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            background: #1a1a1a;
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid #333;
        }
        .v2-subtotal span:first-child {
            color: #aaa;
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .v2-subtotal-val {
            color: var(--color-primary);
            font-weight: 700;
            font-size: 2.4rem;
            line-height: 1;
            text-shadow: 0 0 10px rgba(0, 230, 118, 0.2);
        }
        .v2-actions {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
        }
        .v2-btn {
            padding: 1.2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            border: none;
            transition: 0.2s;
            font-family: var(--font-stack);
            letter-spacing: 1px;
        }
        .v2-btn-primary {
            background: var(--color-primary);
            color: #000;
        }
        .v2-btn-primary:hover {
            background: #00c865;
            box-shadow: 0 0 20px rgba(0, 230, 118, 0.4);
        }
        .v2-btn-primary:disabled {
            background: #444;
            color: #888;
            cursor: not-allowed;
            box-shadow: none;
        }
        .v2-btn-secondary {
            background: transparent;
            border: 2px solid #444;
            color: #888;
        }
        .v2-btn-secondary:hover {
            border-color: #fff;
            color: #fff;
        }

        /* --- Switch Toggle (Larger for Kiosk) --- */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #333;
            transition: .4s;
            border-radius: 34px;
            border: 1px solid #555;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 24px; width: 24px;
            left: 4px; bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--color-primary); border-color: var(--color-primary); }
        input:checked + .slider:before { transform: translateX(26px); }

        /* Override input styling */
        .cart-modal-wrap input[type="text"] {
            width: 45px !important;
            text-align: center !important;
            border: 1px solid #9d0000 !important;
            background: #111 !important;
            color: #fff !important;
            border-radius: 6px !important;
            padding: 0.3rem 0.3rem !important;
            font-size: 1.2rem !important;
            font-weight: bold !important;
        }

        /* Responsive Fixes */
        @media (max-width: 900px) {
            .cart-modal-wrap { flex-direction: column-reverse; overflow-y: auto; padding: 0.25rem; }
            .cart-modal-panel-left { flex: none; width: 100%; height: auto; border-right: none; padding: 0.25rem; }
            .cart-modal-panel-right { height: 350px; flex: none; }
            .v2-btn-mini { width: 40px; height: 40px; font-size: 2.2rem; }
            .v2-input-mini { width: 40px; height: 40px; font-size: 1.2rem; }
        }
    </style>
</head>
<body class="cart-modal-body">

<div id="mainWrap" class="cart-modal-wrap">
    
    <!-- LEFT: Controls -->
    <div class="left-panel cart-modal-panel-left">
        <!-- Promo Box -->
        <div class="promo-box">
            <div class="promo-title">Discounts</div>
            <div class="cart-note-text">(Note: volume discounts are bundle-based and applied automatically)</div>
            
            <ul class="promo-list">
                <li>4×6: $8 each — or 5 for $25</li>
                <li>5×7: $12 each — or 3 for $30</li>
                <li>Digital Image: $15.00 ea. / 5 or more $7 ea.</li>
                <li>Digital Image of same printed photo only $3</li>
            </ul>
        </div>

        <form name="frmCart" action="cart.php" target="cart" method="post">
            <input type="hidden" name="photoID" value="<?php echo $photoID; ?>">

                    <!-- Digital Option -->
                    <div class="digital-addon-box" id="digitalBox">
                        <div class="digital-addon-content">
                            <div class="digital-addon-icon">
                                <i class="fa-regular fa-envelope fa-2x"></i>
                            </div>
                            <div class="digital-addon-text">
                                <strong>Get the digital image</strong>
                                <small id="eml-desc">High-resolution file sent to your email</small>
                            </div>
                            <div class="digital-addon-toggle">
                                <label class="switch">
                                    <input type="checkbox" name="EML" id="EML" value="1" <?php if($email>0)echo'checked';?> onchange="updateSubtotal()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        <div id="pEML" class="digital-addon-price">$15.00</div>
                    </div>

                    <!-- Prints Grid -->
                    <div class="v2-section-title">Select Prints</div>
                    <div class="v2-prints-container">
                        <!-- 4x6 -->
                        <div class="v2-print-card">
                            <div class="v2-print-header">
                                <div class="v2-p-label">4×6</div>
                                <!-- This ID is now here for dynamic price -->
                                <div class="v2-p-price" id="p4x6">$0.00</div>
                            </div>
                            <div class="v2-p-bundle">5 for $25</div>
                            <div class="v2-qty-compact">
                                <button type="button" class="v2-btn-mini" onclick="changeQty('4x6',-1)">−</button>
                                <input type="text" name="4x6" id="4x6" class="v2-input-mini" value="<?php echo $fourby;?>" readonly>
                                <button type="button" class="v2-btn-mini add" onclick="changeQty('4x6',1)">+</button>
                            </div>
                        </div>

                        <!-- 5x7 -->
                        <div class="v2-print-card">
                            <div class="v2-print-header">
                                <div class="v2-p-label">5×7</div>
                                <!-- This ID is now here for dynamic price -->
                                <div class="v2-p-price" id="p5x7">$0.00</div>
                            </div>
                            <div class="v2-p-bundle">3 for $30</div>
                            <div class="v2-qty-compact">
                                <button type="button" class="v2-btn-mini" onclick="changeQty('5x7',-1)">−</button>
                                <input type="text" name="5x7" id="5x7" class="v2-input-mini" value="<?php echo $fiveby;?>" readonly>
                                <button type="button" class="v2-btn-mini add" onclick="changeQty('5x7',1)">+</button>
                            </div>
                        </div>

                        <!-- 8x10 -->
                        <div class="v2-print-card">
                            <div class="v2-print-header">
                                <div class="v2-p-label">8×10</div>
                                <!-- This ID is now here for dynamic price -->
                                <div class="v2-p-price" id="p8x10">$0.00</div>
                            </div>
                            <div class="v2-p-bundle"></div>
                            <div class="v2-qty-compact">
                                <button type="button" class="v2-btn-mini" onclick="changeQty('8x10',-1)">−</button>
                                <input type="text" name="8x10" id="8x10" class="v2-input-mini" value="<?php echo $eightby;?>" readonly>
                                <button type="button" class="v2-btn-mini add" onclick="changeQty('8x10',1)">+</button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="v2-footer">
                        <div class="v2-subtotal">
                            <span>Total Due</span>
                            <span class="v2-subtotal-val" id="subtotal-val">$0.00</span>
                        </div>
                        <div class="v2-actions">
                            <button type="submit" class="v2-btn v2-btn-primary" id="submitBtn">
                                <?php echo $isEdit ? 'UPDATE CART' : 'ADD TO CART'; ?>
                            </button>
                            <button type="button" class="v2-btn v2-btn-secondary" onclick="window.top.closeCartModal(); return false;">
                                Cancel
                            </button>
                        </div>
                    </div>
            </form>
        </div>
        <!-- RIGHT: Big Image -->
        <div class="right-panel cart-modal-panel-right">
            <img src="<?php echo htmlspecialchars($thisPhoto); ?>" alt="Event Photo Preview">
        </div>
    </div>

<script>
    // Logic preserved from original request
    function changeQty(id, delta){
        const el = document.getElementById(id);
        let v = parseInt(el.value || 0) + delta;
        if (v < 0) v = 0;
        el.value = v;
        updateSubtotal();
    }

    function updateSubtotal(){
        let total = 0;
        const qty4 = parseInt(document.getElementById('4x6').value || 0);
        const qty5 = parseInt(document.getElementById('5x7').value || 0);
        const qty8 = parseInt(document.getElementById('8x10').value || 0);
        const eml = document.getElementById('EML').checked ? 1 : 0;

        const price4 = 8.00;
        const price5 = 12.00;
        const price8 = 20.00;

        // 4x6: $8 each or 5 for $25
        let t4 = 0;
        if (qty4 > 0) {
            const bundle4 = Math.floor(qty4 / 5);
            const remain4 = qty4 % 5;
            t4 = (bundle4 * 25) + (remain4 * price4);
        }

        // 5x7: $12 each or 3 for $30
        let t5 = 0;
        if (qty5 > 0) {
            const bundle5 = Math.floor(qty5 / 3);
            const remain5 = qty5 % 3;
            t5 = (bundle5 * 30) + (remain5 * price5);
        }

        let t8 = qty8 * price8;

        let emlPrice = 15.00;
        if (eml && (qty4 > 0 || qty5 > 0 || qty8 > 0)) emlPrice = 3.00;
        const tE = eml ? emlPrice : 0;

        total = t4 + t5 + t8 + tE;

        // UI Updates
        const emlCard = document.getElementById('digitalBox');
        if (eml) {
            emlCard.classList.add('active');
        } else {
            emlCard.classList.remove('active');
        }

        const emlDesc = document.getElementById('eml-desc');
        const emlPriceTag = document.getElementById('pEML');
        
        // Logic: Discount to $3 only if prints are in cart (qty > 0)
        if (qty4 > 0 || qty5 > 0 || qty8 > 0) {
            if (emlPriceTag.textContent !== '$3.00') {
                emlPriceTag.classList.remove('price-blink');
                void emlPriceTag.offsetWidth; // trigger reflow
                emlPriceTag.classList.add('price-blink');
            }
            emlDesc.innerHTML = "Bundle applied! <span style='color:var(--color-primary); font-weight:bold;'>Save $12.00</span>";
            emlPriceTag.textContent = '$3.00'; 
            emlPriceTag.style.color = 'var(--color-primary)';
        } else {
            if (emlPriceTag.textContent !== '$15.00') {
                emlPriceTag.classList.remove('price-blink');
                void emlPriceTag.offsetWidth; // trigger reflow
                emlPriceTag.classList.add('price-blink');
            }
            emlDesc.textContent = "High-resolution file sent to your email";
            emlPriceTag.textContent = '$15.00'; 
            emlPriceTag.style.color = 'var(--color-primary)'; /* Kept Green as requested */
        }

        // Update the Dynamic Prices next to the Labels
        // Logic: If Qty is 0, show $0.00. If Qty > 0, show Calculated Total
        document.getElementById('p4x6').textContent = '$' + t4.toFixed(2);
        document.getElementById('p5x7').textContent = '$' + t5.toFixed(2);
        document.getElementById('p8x10').textContent = '$' + t8.toFixed(2);
        
        document.getElementById('subtotal-val').textContent = '$' + total.toFixed(2);

        // Disable button if total is 0
        const submitBtn = document.getElementById('submitBtn');
        if (total <= 0) {
            submitBtn.disabled = true;
        } else {
            submitBtn.disabled = false;
        }
    }

    // Prevent duplicate submissions and close modal after form submission
    (function() {
        let formSubmitted = false;
        const form = document.forms['frmCart'];
        const submitBtn = document.getElementById('submitBtn');
        
        form.addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            
            formSubmitted = true;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            
            // Close modal after short delay to allow form to submit to cart iframe
            setTimeout(function() {
                if (window.top && window.top.closeCartModal) {
                    window.top.closeCartModal();
                }
            }, 400);
        });
    })();

    // Initialize
    document.addEventListener('DOMContentLoaded', ()=>{
        updateSubtotal();
    });
</script>

</body>
</html>
