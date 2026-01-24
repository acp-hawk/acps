<?php
/**
 * ACPS90 v9.0 - Check Square Order Status
 * Polls Square API to check if a payment link order has been paid
 * Used by QR polling mechanism to detect payment completion
 */

// Load environment
require_once __DIR__ . '/../../vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Silently ignore if .env doesn't exist
}

use Square\SquareClient;
use Square\Environment;

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

try {
    $orderId = $_GET['order_id'] ?? $_POST['order_id'] ?? '';
    
    if (empty($orderId)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing order_id']);
        exit;
    }
    
    $token = getenv('SQUARE_ACCESS_TOKEN');
    if (empty($token)) {
        echo json_encode(['status' => 'error', 'message' => 'Square not configured']);
        exit;
    }
    
    // Create Square client using the same pattern as square_link.php (Legacy SDK)
    $client = \Square\Legacy\SquareClientBuilder::init()
        ->environment(getenv('ENVIRONMENT') === 'sandbox' ? \Square\Legacy\Environment::SANDBOX : \Square\Legacy\Environment::PRODUCTION)
        ->bearerAuthCredentials(\Square\Legacy\Authentication\BearerAuthCredentialsBuilder::init($token))
        ->build();
    
    // Retrieve the order from Square
    $retrieveResponse = $client->getOrdersApi()->retrieveOrder($orderId);
    
    if ($retrieveResponse->isSuccess()) {
        $order = $retrieveResponse->getResult()->getOrder();
        $state = $order->getState(); // OPEN, COMPLETED, CANCELED
        $totalMoney = $order->getTotalMoney();
        $totalAmount = $totalMoney ? $totalMoney->getAmount() : 0;
        $tenders = $order->getTenders();
        
        // Check if payment was received
        // An order is considered "paid" if:
        // 1. State is COMPLETED, OR
        // 2. Net amount due is 0 AND there are tenders (covers 100% discount orders), OR
        // 3. Total is 0 AND there are tenders (zero-dollar orders with payment method selected)
        $netAmountDue = 0;
        if ($order->getNetAmountDueMoney()) {
            $netAmountDue = $order->getNetAmountDueMoney()->getAmount();
        }
        
        $hasTenders = is_array($tenders) && count($tenders) > 0;
        $isPaid = ($state === 'COMPLETED') || 
                  ($netAmountDue === 0 && $hasTenders) || 
                  ($totalAmount === 0 && $hasTenders);
        
        echo json_encode([
            'status' => 'success',
            'order_id' => $orderId,
            'state' => $state,
            'is_paid' => $isPaid,
            'total_amount' => $totalAmount,
            'net_amount_due' => $netAmountDue,
            'has_tenders' => $hasTenders,
            'tender_count' => $hasTenders ? count($tenders) : 0,
            'message' => $isPaid ? 'Payment received' : 'Awaiting payment'
        ]);
    } else {
        $errors = $retrieveResponse->getErrors();
        $errorMessages = [];
        if (is_array($errors)) {
            foreach ($errors as $error) {
                $errorMessages[] = $error instanceof \Exception ? $error->getMessage() : (string)$error;
            }
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Could not retrieve order',
            'errors' => $errorMessages
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
