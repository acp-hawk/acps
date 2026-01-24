<?php
//*********************************************************************//
// AlleyCat PhotoStation - Square Terminal Integration
// Independent API - /config/api/terminal.php
//*********************************************************************//
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/terminal_process_error.log');

require_once __DIR__ . '/../../vendor/autoload.php';

// Load .env variables
if (class_exists('Dotenv\\Dotenv')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->safeLoad();
    } catch (Exception $e) {
        error_log('Failed to load .env: ' . $e->getMessage());
    }
}

use Square\SquareClient;
use Square\Environments;
use Square\Terminal\Checkouts\Requests\CreateTerminalCheckoutRequest;
use Square\Terminal\Checkouts\Requests\GetCheckoutsRequest; // Correct class for Get
use Square\Terminal\Checkouts\Requests\CancelCheckoutsRequest;
use Square\Types\TerminalCheckout;
use Square\Types\Money;
use Square\Types\Currency;
use Square\Types\DeviceCheckoutOptions;

header('Content-Type: application/json');

$server_addy = $_SERVER['HTTP_HOST'] ?? '';
// --- Configuration from .env ---
$accessToken = getenv('SQUARE_ACCESS_TOKEN') ?: '';
$deviceId = ($server_addy == '192.168.2.126') ? getenv('SQUARE_TERMINAL_ID_FIRE') : getenv('SQUARE_TERMINAL_ID');
//$deviceId    = getenv('SQUARE_TERMINAL_ID') ?: '';
$environment = Environments::Production;

if (empty($accessToken) || empty($deviceId)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Missing Square credentials in environment']);
    exit;
}

$client = new SquareClient(
    token: $accessToken,
    options: [
        'baseUrl' => $environment->value,
    ],
);

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $orderId = $_POST['order_id'] ?? '';
        $amount  = floatval($_POST['amount'] ?? 0);

        if (empty($orderId) || $amount <= 0) {
            throw new Exception("Invalid order ID or amount.");
        }

        // Convert amount to cents
        $amountCents = (int)round($amount * 100);
        $idempotencyKey = uniqid('term_', true);

        $request = new CreateTerminalCheckoutRequest([
            'idempotencyKey' => $idempotencyKey,
            'checkout' => new TerminalCheckout([
                'amountMoney' => new Money([
                    'amount' => $amountCents,
                    'currency' => Currency::Usd->value,
                ]),
                'deviceOptions' => new DeviceCheckoutOptions([
                    'deviceId' => $deviceId,
                ]),
                'referenceId' => $orderId, 
                'note' => "Order #$orderId",
            ]),
        ]);

        $response = $client->terminal->checkouts->create($request);

        // Check for transport/logic errors (Square SDK throws exceptions usually, but let's be safe)
        // If SDK 42+, errors might be in response object? The new SDK style is different.
        // Assuming the new client structure as provided in prompt example.
        // The example: $client->terminal->checkouts->create(new Request(...));
        // It returns a response object.

        $checkout = $response->getCheckout();
        
        echo json_encode([
            'status' => 'success',
            'checkout_id' => $checkout->getId(),
            'terminal_status' => $checkout->getStatus(),
        ]);

    } elseif ($action === 'poll') {
        $checkoutId = $_POST['checkout_id'] ?? '';

        if (empty($checkoutId)) {
            throw new Exception("Missing checkout ID.");
        }

        // Correct Get Request format based on updated prompt
        $request = new GetCheckoutsRequest([
            'checkoutId' => $checkoutId,
        ]);
        
        $response = $client->terminal->checkouts->get($request);
        $checkout = $response->getCheckout();
        $status = $checkout->getStatus(); 
        $paymentIds = $checkout->getPaymentIds();
        $paymentId = ($paymentIds && count($paymentIds) > 0) ? $paymentIds[0] : '';

        echo json_encode([
            'status' => 'success',
            'terminal_status' => $status,
            'payment_id' => $paymentId
        ]);

    } elseif ($action === 'cancel') {
        $checkoutId = $_POST['checkout_id'] ?? '';
        if (empty($checkoutId)) throw new Exception("Missing checkout ID.");

        $request = new CancelCheckoutsRequest([
            'checkoutId' => $checkoutId,
        ]);
        $response = $client->terminal->checkouts->cancel($request);
        // Cancel returns CancelTerminalCheckoutResponse
        
        echo json_encode(['status' => 'success', 'message' => 'Cancellation requested.']);

    } else {
        throw new Exception("Invalid action.");
    }

} catch (Exception $e) {
    http_response_code(500);
    // Ensure we always output valid JSON
    $errorMsg = $e->getMessage() ?: 'Unknown error occurred';
    error_log('Terminal API Error: ' . $errorMsg);
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}
