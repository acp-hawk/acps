<?php
//*********************************************************************//
// AlleyCat PhotoStation - Square Payment Link Generator
// Refactored
//*********************************************************************//


use Square\Legacy\SquareClient;
use Square\Legacy\SquareClientBuilder;
use Square\Legacy\Authentication\BearerAuthCredentialsBuilder;
use Square\Legacy\Environment;
use Square\Legacy\Models\CreatePaymentLinkRequest;
use Square\Legacy\Models\QuickPay;
use Square\Legacy\Models\Money;
use Square\Legacy\Models\PrePopulatedData;
use Square\Legacy\Models\CheckoutOptions;
use Square\Legacy\Models\AcceptedPaymentMethods;
use Square\Legacy\Exceptions\ApiException;


/**
 * Creates a Square Payment Link.
 *
 * @param float $amount The total amount for the order (e.g., 10.75).
 * @param string $email The customer's email address.
 * @param string $orderId The unique order ID for this transaction.
 * @param string $transactionId The unique transaction ID (idempotency key).
 * @return \Square\Legacy\Models\PaymentLink|false The Square PaymentLink object on success, false on failure.
 */
function createSquarePaymentLink(float $amount, string $email, string $orderId, string $transactionId) {
    
    // This function assumes vendor/autoload.php has been required.
    // It also assumes Square config vars are available.
    
    // Read Square config from environment with sensible fallbacks (do NOT hardcode tokens in production)
    $token = $_ENV['SQUARE_ACCESS_TOKEN'] ?? getenv('SQUARE_ACCESS_TOKEN') ?: 'null';
    $envName = $_ENV['ENVIRONMENT'] ?? getenv('ENVIRONMENT') ?? 'production';
    $locationId = $_ENV['SQUARE_LOCATION_ID'] ?? getenv('SQUARE_LOCATION_ID') ?? 'L69EQY1WK4M4A';
    
    // The public URL for the payment handler
    $public_pay_url = 'https://alleycatphoto.net/pay/';

    try {
        // Validate token early so we fail with a clear message instead of a low-level auth validation exception
        if (empty($token)) {
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
            $logFile = $logDir . '/square_error.log';
            $logMessage = "[" . date('Y-m-d H:i:s') . "] Square configuration error: missing SQUARE_ACCESS_TOKEN environment variable.\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            return false;
        }
        $client = SquareClientBuilder::init()
            ->environment($envName === 'sandbox' ? Environment::SANDBOX : Environment::PRODUCTION)
            ->bearerAuthCredentials(BearerAuthCredentialsBuilder::init($token))
            ->build();

        // Convert float amount to cents (integer)
        $amount_in_cents = (int)($amount * 100);

        // 1. Build Checkout Options
        $checkout_options = new CheckoutOptions();
        $checkout_options->setAllowTipping(false);
        $checkout_options->setAskForShippingAddress(false);
        
        $redirect_params = http_build_query([
            'transactionId' => $transactionId,
            'orderId' => $orderId
        ]);
        $checkout_options->setRedirectUrl($public_pay_url . '?' . $redirect_params);
        
        $accepted_payment_methods = new AcceptedPaymentMethods();
        $accepted_payment_methods->setApplePay(true);
        $accepted_payment_methods->setGooglePay(true);
        $accepted_payment_methods->setCashAppPay(true);
        
        $checkout_options->setAcceptedPaymentMethods($accepted_payment_methods);

        // 2. Build the QuickPay object with a correctly instantiated Money object
        $price_money = new Money();
        $price_money->setAmount($amount_in_cents);
        $price_money->setCurrency('USD');
        
        $quick_pay = new QuickPay(
            'Alley Cat Photo Order', // Name of the charge
            $price_money,
            $locationId
        );

        // 3. Build Pre-populated data
        $pre_populated_data = new PrePopulatedData();
        $pre_populated_data->setBuyerEmail($email);

        // 4. Build the main request body
        $body = new CreatePaymentLinkRequest();
        $body->setIdempotencyKey($transactionId);
        $body->setDescription('Order ID: ' . $transactionId);
        $body->setQuickPay($quick_pay);
        $body->setCheckoutOptions($checkout_options);
        $body->setPrePopulatedData($pre_populated_data);

        // 5. Make the API call
        $api_response = $client->getCheckoutApi()->createPaymentLink($body);

        if ($api_response->isSuccess()) {
            $paymentLink = $api_response->getResult()->getPaymentLink();
            
            // --- LOGGING GENERATION ---
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
            $logFile = $logDir . '/square_qr_generation.log';
            
            // Capture cart items from session
            $cartSummary = "No items in session";
            if (isset($_SESSION['shopping_cart'])) {
                require_once __DIR__ . '/shopping_cart.class.php';
                if (class_exists('Shopping_Cart')) {
                    $tempCart = new Shopping_Cart('shopping_cart');
                    $cartSummary = "ITEMS ORDERED:\n-----------------------------\n";
                    foreach ($tempCart->getItems() as $code => $qty) {
                        $cartSummary .= "[" . $qty . "] " . $tempCart->getItemName($code) . " (" . $code . ")\n";
                    }
                } else {
                    $cartSummary = json_encode($_SESSION['shopping_cart'], JSON_PRETTY_PRINT);
                }
            }

            $logEntry = sprintf(
                "[%s] QR Generated | OrderID: %s | TransID: %s | Amount: %0.2f | Email: %s | Link: %s\nCart Items:\n%s\n------------------------------------------\n",
                date('Y-m-d H:i:s'),
                $orderId,
                $transactionId,
                $amount,
                $email,
                $paymentLink->getUrl(),
                $cartSummary
            );
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            // --- END LOGGING ---

            return $paymentLink;
        } else {
            // Log errors if possible
            $errors = $api_response->getErrors();
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
            $logFile = $logDir . '/square_error.log';
            $logMessage = "[" . date('Y-m-d H:i:s') . "] Square API Error: " . json_encode($errors) . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            return false;
        }
    } catch (ApiException $e) {
        // Log exceptions
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
        $logFile = $logDir . '/square_error.log';
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Square API Exception: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return false;
    }
}