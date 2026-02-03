<?php
// ACPS 9.0 Thank You / Status Page
require_once "admin/config.php";
$orderID = $_GET['order'] ?? '0000';
$status = $_GET['status'] ?? 'pending'; 
$station = $_GET['station'] ?? 'MS';
$isOnsite = $_GET['onsite'] ?? 'yes';

$title = "ORDER RECEIVED";
$color = "#ffff66"; 
$message = "Please see them at the counter to complete your order."; // Restored verbiage

if ($status === 'paid') {
    $title = "APPROVED";
    $color = "#6F0"; 
    $message = "Your order is being processed now!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Order #<?php echo htmlspecialchars($orderID); ?> | AlleyCat Photo</title>
<link rel="stylesheet" href="/public/assets/css/acps.css">
<style>
    body { background: #0a0a0a; color: white; text-align: center; font-family: 'Poppins', sans-serif; }
    .status-title { font-size: 3rem; font-weight: 900; margin-top: 50px; color: <?php echo $color; ?>; }
    
    /* VERY LARGE AND VERY GREEN */
    .order-display { 
        font-size: 10rem; 
        font-weight: 900; 
        margin: 10px 0; 
        color: #6F0; /* Neon Green */
        font-family: monospace; 
        line-height: 1;
        text-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
    }
    
    .sub-msg { font-size: 1.8rem; color: #ccc; margin-bottom: 40px; font-weight: 600; }
    .btn-return {
        padding: 20px 50px;
        font-size: 1.5rem;
        background: #333;
        color: white;
        border: 2px solid #555;
        border-radius: 8px;
        cursor: pointer;
        text-transform: uppercase;
        font-weight: bold;
    }
    .btn-return:hover { background: #444; border-color: #fff; }
</style>
<script>
    setTimeout(() => { window.location.href = '/'; }, 60000);
</script>
</head>
<body>

    <div class="logo-section" style="margin-top: 40px;">
      <img src="/public/assets/images/alley_logo_sm.png" alt="Alley Cat Photo" width="300">
    </div>

    <h1 class="status-title"><?php echo $title; ?></h1>
    
    <div style="font-size: 1.5rem; color: #888; text-transform: uppercase; letter-spacing: 2px;">Your Order Number</div>
    <div class="order-display"><?php echo htmlspecialchars($orderID); ?></div>

    <div class="sub-msg">
        <?php echo $message; ?>
        <?php if ($status === 'paid' && $isOnsite === 'yes'): ?>
            <div style="color: #6F0; margin-top:10px;">Prints ready in minutes.</div>
        <?php endif; ?>
    </div>

    <button class="btn-return" onclick="window.location.href='/'">Return to Gallery</button>

</body>
</html>
