<?php
if (session_status() === PHP_SESSION_NONE){session_start();}
include('shopping_cart.class.php');
$Cart = new Shopping_Cart('shopping_cart');

$dirname = "photos/";
$date_path = date('Y/m/d');

// Clear cart handler
if (isset($_GET['a']) && $_GET['a']=='clear'){ $Cart->clearCart(); }

// Handle post updates
if (isset($_POST['photoID'])) {
  $photoID = $_POST['photoID'];
  $Cart->setItemQuantity('4x6-'.$photoID, intval($_POST['4x6'] ?? 0));
  $Cart->setItemQuantity('5x7-'.$photoID, intval($_POST['5x7'] ?? 0));
  $Cart->setItemQuantity('8x10-'.$photoID, intval($_POST['8x10'] ?? 0));
  $Cart->setItemQuantity('EML-'.$photoID, isset($_POST['EML']) ? 1 : 0);
  $Cart->save();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PhotoStation Cart</title>
<script src="/public/assets/js/jquery-3.2.1.min.js"></script>
<script src="/public/assets/js/acps_modal.js"></script>
<style>
body,td,th{font-family:'Poppins', -apple-system, system-ui, sans-serif;color:#eee;font-size:12px;}
body{background-color:#2b2b2b;margin:0;padding:0;}
.cart-sidebar{width:100%;max-width:320px;padding:12px;box-sizing:border-box;color:#e6e6e6;}
.cart-content{padding-bottom:120px;padding-top:92px;}
.cart-top{position:fixed;top:0;left:0;width:100%;background:#000;color:#fff;padding:10px 12px;font-weight:700;z-index:1100;border-bottom:1px solid rgba(255,255,255,0.04);box-sizing:border-box}
.center{text-align:center;}
.discount-line{color:#ff7b7b;text-align:right;font-size:12px;}
.item-row{border-bottom:1px solid rgba(255,255,255,0.06);padding:6px 0 4px 0;font-size:13px;color:#f1f1f1;display:flex;justify-content:center}
.item-thumb{border-radius:4px;box-shadow:0 1px 2px rgba(0,0,0,0.6);width:100%;height:100%;object-fit:cover;display:block}
.item-thumb-container{width:90%;max-width:160px;margin:6px auto}
.thumb-square{width:100%;padding-bottom:100%;position:relative;overflow:hidden;border-radius:6px}
.thumb-square img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover}
.item-card{background:#0b0b0b;padding:6px;border-radius:8px;border:1px solid transparent;display:block;position:relative;max-width:160px;width:100%;box-sizing:border-box;margin:6px auto;overflow:visible}
.meta-column{flex:1;display:flex;flex-direction:column;gap:6px}
.thumb-wrapper{flex:1;padding:5px;box-sizing:border-box}
.thumb-wrapper .thumb-square{width:100%;padding-bottom:100%;margin:6px 0 0 0;border-radius:6px}
.item-card:hover{border-color:#b30000;box-shadow:0 6px 18px rgba(0,0,0,0.6)}
.item-info{flex:1}
.price{color:#7CFC00;font-weight:700;}
.remove-badge{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:#000;border:1px solid rgba(255,255,255,0.04);box-shadow:0 1px 2px rgba(0,0,0,0.6);}
.remove-badge svg{width:16px;height:16px;fill:#ff3b30;opacity:0.95}
.item-card{background:#0b0b0b;padding:6px;border-radius:8px;border:1px solid transparent;display:block;position:relative;max-width:160px;width:100%;box-sizing:border-box;margin:6px auto}
.meta-column{display:flex;flex-direction:column;gap:6px}
.thumb-wrapper{width:100%;padding:4px 0 0 0;box-sizing:border-box}
.thumb-wrapper .thumb-square{width:100%;padding-bottom:100%;position:relative;overflow:hidden;border-radius:6px;margin:4px 0 0 0}
.thumb-square img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover}
.item-card:hover{border-color:#b30000;box-shadow:0 6px 18px rgba(0,0,0,0.6)}
.item-info{flex:1}
.item-header{display:block;position:relative}
.item-title{display:block;margin-right:36px}
.price{color:#7CFC00;font-weight:700;}
.remove-badge{position:absolute;top:8px;right:8px;z-index:1;display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#000;border:1px solid rgba(255,255,255,0.06);box-shadow:0 1px 2px rgba(0,0,0,0.6);padding:2px}
.remove-badge svg{width:14px;height:14px;fill:#ff3b30;opacity:0.95}

/* New-item pulse glow animation for newest items — two quick flashes then fade */
@keyframes pulseGlow {
  0%   { box-shadow: 0 0 0 0 rgba(179,0,0,0); }
  12%  { box-shadow: 0 0 5px 5px rgba(179,0,0,0.95); }
  28%  { box-shadow: 0 0 0 0 rgba(179,0,0,0); }
  45%  { box-shadow: 0 0 5px 5px rgba(179,0,0,0.85); }
  62%  { box-shadow: 0 0 0 0 rgba(179,0,0,0); }
 100%  { box-shadow: 0 0 0 0 rgba(179,0,0,0); }
}
.new-item{animation: pulseGlow 1.6s ease-out both;z-index:5}
.subtotal-line{text-align:right;color:#ddd;margin-top:4px}
.total-line{font-weight:700;font-size:15px;color:#fff}
.cart-footer{position:fixed;bottom:0;left:0;width:100%;background:#000;padding:12px 12px 18px 12px;box-sizing:border-box;border-top:1px solid rgba(255,255,255,0.04);z-index:1100;}
.cart-footer .row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.cart-footer .totals{color:#ddd;font-size:13px}
.cta-btn{background:#333;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,0.5);border:2px solid #b30000}
.checkout-btn{background:#e6a90a;color:#111;padding:8px 12px;border-radius:6px;text-decoration:none;font-weight:700;box-shadow:0 2px 6px rgba(0,0,0,0.5)}
.empty-note{color:#bdbdbd;padding:18px 0;text-align:center}
.header-row{display:flex;justify-content:space-between;align-items:center;padding:8px 4px;border-bottom:1px solid #4a4a4a;margin-top:8px}
.header-row .left{font-weight:700;color:#ddd}
.header-row .right{font-weight:700;color:#ff3b30}
.label-small{color:#cfcfcf;font-size:12px}
.amount-green{color:#7CFC00;font-weight:700}
.total-big{color:#7CFC00;font-weight:900;font-size:22px;text-align:center;margin:8px 0}
.cart-top .top-row{display:flex;flex-direction:column;align-items:center;gap:6px}
.cart-top .heading{font-size:14px;margin-bottom:2px}
.cart-top .cta-wrap{width:100%;display:flex;justify-content:center}
.cart-top .subrow{display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:1px solid rgba(255,255,255,0.03)}
.checkout-full{display:block;width:80%;text-align:center;margin-top:10px}
.photo-list{max-height:calc(100vh - 220px);overflow-y:auto;padding-right:6px}
.photo-list::-webkit-scrollbar{width:8px}
.photo-list::-webkit-scrollbar-track{background:transparent}
.photo-list::-webkit-scrollbar-thumb{background:#6e6e6e;border-radius:4px}
.photo-list{scrollbar-width:thin;scrollbar-color:#6e6e6e transparent}
.photo-list{padding-right:12px;margin-right:-6px}
</style>
</head>
<body>
<div class="cart-sidebar">
<div class="cart-content">
<form action="cart_action.php" method="get" name="frmCart">
<table width="100%" border="0" cellspacing="0" cellpadding="0">

<tr><td colspan="2">
  <div class="cart-top" style="box-sizing:border-box;align-items:center;">
    <div class="top-row">
      <div class="heading">YOUR PHOTOS</div>
      <div class="cta-wrap"><a href="cart.php?a=clear" class="cta-btn">Clear cart</a></div>
    </div>
    <div class="subrow">
      <div class="left" style="font-weight:700;color:#ddd">PHOTO</div>
      <div class="right" style="font-weight:700;color:#ff3b30">REMOVE</div>
    </div>
  </div>
</td></tr>

<?php if ($Cart->hasItems()) : ?>
<tr><td colspan="2"><div class="photo-list">

<?php
$total_price = 0;
$four_count=$five_count=$eight_count=$email_count=0;

// show newest items on top
$items = $Cart->getItems();
if (!empty($items)) { $items = array_reverse($items, true); } else { $items = array(); }
$first_new = true;
foreach ($items as $order_code=>$quantity):
    $price_per = $Cart->getItemPrice($order_code);
    list($prod,$pid)=explode('-',$order_code);

    // track counts
    if($prod=='4x6') $four_count+=$quantity;
    if($prod=='5x7') $five_count+=$quantity;
    if($prod=='8x10') $eight_count+=$quantity;
    if($prod=='EML') $email_count+=$quantity;

    $total_price += $price_per * $quantity;
    // mark newest item(s) — first iteration is newest
    $new_class = '';
    if ($first_new) { $new_class = ' new-item'; $first_new = false; }
?>
<?php
    // Determine display price text (show bundle messaging when quantity break applies)
    $price_display = '$'.number_format($price_per*$quantity,2);
    if ($prod === '4x6' && $quantity >= 5) {
        $price_display = '5 for $25';
    } elseif ($prod === '5x7' && $quantity >= 3) {
        $price_display = '3 for $30';
    } elseif ($prod === 'EML' && $quantity >= 5) {
        $price_display = '5+ at $7 ea';
    }
?>
<div class="item-row">
  <div class="item-card<?php echo $new_class; ?>">
    <div class="meta-column">
      <div class="item-info">
        <div class="item-header">
          <div class="item-title">(<strong><?php echo $quantity; ?></strong>) <?php echo $Cart->getItemName($order_code); ?></div>
          <a href="cart_action.php?remove[]=<?php echo urlencode($order_code); ?>" class="remove-badge" title="Remove item">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M3 6h18v2H3V6zm2 3h2v9H5V9zm4 0h2v9H9V9zm4 0h2v9h-2V9zm4 0h2v9h-2V9zM9 4V3h6v1h5v2H4V4h5z"/>
            </svg>
          </a>
        </div>
        <div class="price"><?php echo $price_display; ?></div>
      </div>
      <a href="#" onclick="window.top.editCart('cart_add.php?p=<?php echo $dirname.$date_path.'/numbered/'.$Cart->getImageName($order_code); ?>'); return false;" class="thumb-wrapper">
        <div class="thumb-square">
          <img src="<?php echo $dirname.$date_path.'/numbered/'.$Cart->getImageName($order_code); ?>" class="item-thumb" alt="photo" />
        </div>
      </a>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div></td></tr>

<?php
//--------------------------------------------------------//
//            APPLY TRUE BUNDLE DISCOUNTS
//--------------------------------------------------------//
$discount_amt = 0;
 $discount_lines = array();

// 4x6: $8 each or 5 for $25 → saves $15 per full bundle of 5
if($four_count >= 5){
  $bundle4 = floor($four_count / 5);
  $discount4 = $bundle4 * 15;
  $discount_amt += $discount4;
  $discount_lines[] = "4x6 Bundle Discount: -$".number_format($discount4,2);
}

// 5x7: $12 each or 3 for $30 → saves $6 per full bundle of 3
if($five_count >= 3){
  $bundle5 = floor($five_count / 3);
  $discount5 = $bundle5 * 6;
  $discount_amt += $discount5;
  $discount_lines[] = "5x7 Bundle Discount: -$".number_format($discount5,2);
}

// Emails: 5 or more → $7 each (handled globally, not per photo)
if($email_count >= 5){
  // For simplicity, apply discount only once message-wise (actual $7 handled in backend if used)
  $discount_lines[] = "Email Volume Discount Applied";
}

//--------------------------------------------------------//
//            CALCULATE TOTAL + TAX
//--------------------------------------------------------//
$subtotal = $total_price - $discount_amt;
$sales_tax = 0.0675 * $subtotal;
$total_due = $subtotal + $sales_tax;
?>

<?php // totals moved to sticky footer ?>

<?php else: ?>
<tr><td colspan="2" class="empty-note">You have no items in your cart.</td></tr>
<?php endif; ?>

</table>
</form>
</div><!-- .cart-content -->

<?php if ($Cart->hasItems()) : ?>
<div class="cart-footer">
  <div class="row">
    <div class="totals" style="flex:1">
      <div style="display:flex;justify-content:space-between;align-items:center"><span class="label-small">SUB TOTAL:</span><span class="amount-green">$<?php echo number_format($subtotal,2); ?></span></div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px"><span class="label-small">TAX:</span><span class="amount-green">$<?php echo number_format($sales_tax,2); ?></span></div>
            <div class="total-big">$<?php echo number_format($total_due,2); ?></div>
            <a class="cta-btn checkout-full" href="#" onclick="window.top.openCheckoutModal(<?php echo $total_due; ?>); return false;">Checkout</a>
          </div>
  </div>
</div>
<?php endif; ?>

</div><!-- .cart-sidebar -->

</body>
</html>
