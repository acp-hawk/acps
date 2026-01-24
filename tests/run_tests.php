<?php
/**
 * ACPS 9.0 - Comprehensive Test Suite
 * Tests all payment methods and endpoints
 * 
 * USAGE: 
 *   php tests/run_tests.php [test_name]
 *   php tests/run_tests.php all
 */

class ACPSTest {
    private $base_url = 'http://localhost';
    private $api_base = '/config/api';
    private $results = [];
    private $total = 0;
    private $passed = 0;
    
    public function __construct($base_url = null) {
        if ($base_url) $this->base_url = $base_url;
    }
    
    // HTTP helper
    private function http_post($endpoint, $data) {
        $ch = curl_init($this->base_url . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $response];
    }
    
    private function http_get($endpoint) {
        $ch = curl_init($this->base_url . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $response];
    }
    
    // Test: Checkout API exists and responds
    public function test_checkout_api_alive() {
        echo "TEST: Checkout API is alive...\n";
        $this->total++;
        
        $resp = $this->http_post($this->api_base . '/checkout.php', http_build_query([
            'payment_method' => 'cash',
            'email' => 'test@alleycatphoto.net',
            'delivery_method' => 'pickup',
            'amount' => '50.00',
            'name' => 'Test User',
            'address' => '123 Main St',
            'city' => 'City',
            'state' => 'NC',
            'zip' => '12345'
        ]));
        
        if ($resp['code'] === 200 && strpos($resp['body'], 'success') !== false) {
            echo "  ✓ PASS\n";
            $this->passed++;
            return true;
        } else {
            echo "  ✗ FAIL: HTTP {$resp['code']}\n";
            echo "  Response: " . substr($resp['body'], 0, 200) . "\n";
            return false;
        }
    }
    
    // Test: Orders API returns valid JSON
    public function test_orders_api() {
        echo "TEST: Orders API returns valid JSON...\n";
        $this->total++;
        
        $resp = $this->http_get($this->api_base . '/orders.php');
        
        if ($resp['code'] !== 200) {
            echo "  ✗ FAIL: HTTP {$resp['code']}\n";
            return false;
        }
        
        $json = json_decode($resp['body'], true);
        if (!$json || !isset($json['status'])) {
            echo "  ✗ FAIL: Invalid JSON response\n";
            echo "  Response: " . substr($resp['body'], 0, 200) . "\n";
            return false;
        }
        
        echo "  ✓ PASS: {$json['status']} - " . count($json['orders'] ?? []) . " orders\n";
        $this->passed++;
        return true;
    }
    
    // Test: Spooler tick_printer works
    public function test_spooler_printer() {
        echo "TEST: Spooler tick_printer works...\n";
        $this->total++;
        
        $resp = $this->http_get($this->api_base . '/spooler.php?action=tick_printer');
        
        if ($resp['code'] !== 200) {
            echo "  ✗ FAIL: HTTP {$resp['code']}\n";
            return false;
        }
        
        $json = json_decode($resp['body'], true);
        if (!$json || !isset($json['status'])) {
            echo "  ✗ FAIL: Invalid JSON response\n";
            return false;
        }
        
        echo "  ✓ PASS: Status = {$json['status']}\n";
        $this->passed++;
        return true;
    }
    
    // Test: Spooler tick_mailer works  
    public function test_spooler_mailer() {
        echo "TEST: Spooler tick_mailer works...\n";
        $this->total++;
        
        $resp = $this->http_get($this->api_base . '/spooler.php?action=tick_mailer');
        
        if ($resp['code'] !== 200) {
            echo "  ✗ FAIL: HTTP {$resp['code']}\n";
            return false;
        }
        
        $json = json_decode($resp['body'], true);
        if (!$json || !isset($json['status'])) {
            echo "  ✗ FAIL: Invalid JSON response\n";
            return false;
        }
        
        echo "  ✓ PASS: Status = {$json['status']}, triggered = " . count($json['triggered'] ?? []) . "\n";
        $this->passed++;
        return true;
    }
    
    // Test: Check Square Order API exists
    public function test_check_square_order_api() {
        echo "TEST: Check Square Order API exists...\n";
        $this->total++;
        
        $resp = $this->http_get($this->api_base . '/check_square_order.php?order_id=FqRvrBon9QOLFdwbc5lYg7VbDGNZY');
        
        if ($resp['code'] !== 200) {
            echo "  ✗ FAIL: HTTP {$resp['code']}\n";
            return false;
        }
        
        $json = json_decode($resp['body'], true);
        if (!$json || !isset($json['status'])) {
            echo "  ✗ FAIL: Invalid JSON response\n";
            return false;
        }
        
        echo "  ✓ PASS: Endpoint exists\n";
        $this->passed++;
        return true;
    }
    
    // Test: QR generation API
    public function test_qr_generation() {
        echo "TEST: QR generation API...\n";
        $this->total++;
        
        $resp = $this->http_post('/cart_generate_qr.php', http_build_query([
            'email' => 'test@alleycatphoto.net',
            'total' => '75.00'
        ]));
        
        if ($resp['code'] !== 200) {
            echo "  ✗ FAIL: HTTP {$resp['code']}\n";
            return false;
        }
        
        $json = json_decode($resp['body'], true);
        if (!$json || !isset($json['status'])) {
            echo "  ✗ FAIL: Invalid JSON\n";
            return false;
        }
        
        if ($json['status'] === 'success') {
            echo "  ✓ PASS: QR generated\n";
            $this->passed++;
            return true;
        } else {
            echo "  ✗ FAIL: " . ($json['message'] ?? 'Unknown error') . "\n";
            return false;
        }
    }
    
    public function run_all() {
        echo "\n=== ACPS 9.0 COMPREHENSIVE TEST SUITE ===\n";
        echo "Base URL: {$this->base_url}\n\n";
        
        $this->test_checkout_api_alive();
        echo "\n";
        $this->test_orders_api();
        echo "\n";
        $this->test_spooler_printer();
        echo "\n";
        $this->test_spooler_mailer();
        echo "\n";
        $this->test_check_square_order_api();
        echo "\n";
        $this->test_qr_generation();
        
        echo "\n=== RESULTS ===\n";
        echo "Passed: {$this->passed}/{$this->total}\n";
        echo "Failed: " . ($this->total - $this->passed) . "/{$this->total}\n";
        echo "Pass Rate: " . round(100 * $this->passed / $this->total, 1) . "%\n";
    }
}

$test = new ACPSTest('http://localhost');
$test->run_all();
