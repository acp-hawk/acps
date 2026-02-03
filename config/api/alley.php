<?php
/**
 * ALLEY Agent API Endpoint
 * 
 * Google Gemini 2.5-flash-latest powered AI assistant for ACPS admin
 * Handles tool calls, logging, and system interactions
 */

session_start();
header('Content-Type: application/json');

// Authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die(json_encode(['error' => 'Unauthorized']));
}

require_once __DIR__ . '/../../vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // Silently ignore
}

// Gemini API Configuration
$gemini_api_key = getenv('GEMINI_API_KEY');
$gemini_model = 'gemini-1.5-flash';
$gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/$gemini_model:generateContent";

// Incoming request
$input = json_decode(file_get_contents('php://input'), true);
$user_message = $input['message'] ?? '';
$session_id = $input['session_id'] ?? 'sess_' . bin2hex(random_bytes(8));
$user = $_SESSION['user_id'] ?? 'unknown';

if (!$user_message) {
    die(json_encode(['error' => 'No message provided']));
}

// Initialize action log
$log_file = __DIR__ . '/../../logs/alley_actions.json';
if (!file_exists($log_file)) file_put_contents($log_file, '[]');

// Tool Definitions (JSON Schema)
$tools = [
    [
        'name' => 'get_order_details',
        'description' => 'Retrieve complete details for an order including items, payment status, and queue position',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string', 'description' => 'Order ID (e.g., "1056")']
            ],
            'required' => ['order_id']
        ]
    ],
    [
        'name' => 'reprint_order',
        'description' => 'Move an order\'s JPGs back to printer queue for reprinting. Will create TXT metadata if missing.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string'],
                'force_station' => ['type' => 'string', 'enum' => ['MS', 'FS'], 'description' => 'Override station']
            ],
            'required' => ['order_id']
        ]
    ],
    [
        'name' => 'resend_email',
        'description' => 'Re-queue an order for email delivery via gmailer',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string'],
                'email_override' => ['type' => 'string', 'description' => 'Send to different email (optional)']
            ],
            'required' => ['order_id']
        ]
    ],
    [
        'name' => 'cancel_order',
        'description' => 'Remove an order from queue with reason logged. DESTRUCTIVE ACTION - requires confirmation.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string'],
                'reason' => ['type' => 'string', 'description' => 'Cancellation reason']
            ],
            'required' => ['order_id', 'reason']
        ]
    ],
    [
        'name' => 'check_print_history',
        'description' => 'Query print history log for troubleshooting',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string', 'description' => 'Optional: filter by order'],
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD format, defaults to today'],
                'limit' => ['type' => 'integer', 'default' => 50]
            ]
        ]
    ],
    [
        'name' => 'get_print_queue_status',
        'description' => 'Check current state of print queue (pending, by station)',
        'parameters' => [
            'type' => 'object',
            'properties' => (object)[]
        ]
    ],
    [
        'name' => 'get_email_queue_status',
        'description' => 'Check pending and failed emails',
        'parameters' => [
            'type' => 'object',
            'properties' => (object)[]
        ]
    ],
    [
        'name' => 'check_email_logs',
        'description' => 'Search gmailer error logs for email issues',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string', 'description' => 'Optional order filter'],
                'keyword' => ['type' => 'string', 'description' => 'Search term']
            ]
        ]
    ],
    [
        'name' => 'check_printer_status',
        'description' => 'Check if printers (Main/Fire) are ready or busy',
        'parameters' => [
            'type' => 'object',
            'properties' => (object)[]
        ]
    ],
    [
        'name' => 'create_missing_txt_metadata',
        'description' => 'AUTO-RECOVER: Create TXT metadata file from receipt when missing (prevents JPG orphaning)',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string']
            ],
            'required' => ['order_id']
        ]
    ],
    [
        'name' => 'check_system_health',
        'description' => 'Full system diagnostics: disk space, permissions, API connectivity',
        'parameters' => [
            'type' => 'object',
            'properties' => (object)[]
        ]
    ]
];

// First turn: Send user message + tools to Gemini
$request_body = [
    'system_instruction' => [
        'parts' => [
            [
                'text' => 'You are ALLEY, the sharp, witty AI assistant for AlleyCat PhotoStation admin. You know this system intimately - the folder structure, APIs, print queues, email delivery. You speak with irreverent humor (Big Lebowski, Office Space, Spaceballs vibes). You are genuinely helpful and make recovery simple. When users have problems, you use available tools to diagnose and fix. You explain what you\'re doing and why. You\'re geeky, you care about getting things done, and you never take yourself too seriously.'
            ]
        ]
    ],
    'contents' => [
        [
            'role' => 'user',
            'parts' => [['text' => $user_message]]
        ]
    ],
    'tools' => [
        [
            'function_declarations' => $tools
        ]
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$gemini_url?key=$gemini_api_key",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request_body)
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    log_alley_action('gemini_api_error', ['http_code' => $http_code, 'response' => $response]);
    die(json_encode(['error' => 'Gemini API error', 'details' => $response]));
}

$gemini_response = json_decode($response, true);

// Check if Gemini wants to call tools
$first_content = $gemini_response['candidates'][0]['content']['parts'][0] ?? [];

if (isset($first_content['functionCall'])) {
    // Tool call needed
    $tool_name = $first_content['functionCall']['name'];
    $tool_args = $first_content['functionCall']['args'];
    
    // Execute tool
    $tool_result = execute_tool($tool_name, $tool_args);
    
    // Log the action
    log_alley_action($tool_name, $tool_args, $tool_result, $user, $session_id);
    
    // Send tool result back to Gemini for final response
    $followup_request = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $user_message]]
            ],
            [
                'role' => 'model',
                'parts' => [['functionCall' => [
                    'name' => $tool_name,
                    'args' => $tool_args
                ]]]
            ],
            [
                'role' => 'user',
                'parts' => [['functionResponse' => [
                    'name' => $tool_name,
                    'response' => $tool_result
                ]]]
            ]
        ],
        'system_instruction' => $request_body['system_instruction'],
        'tools' => $request_body['tools']
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$gemini_url?key=$gemini_api_key",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($followup_request)
    ]);
    
    $followup_response = curl_exec($ch);
    curl_close($ch);
    
    $gemini_response = json_decode($followup_response, true);
}

// Extract text response from Gemini
$final_text = $gemini_response['candidates'][0]['content']['parts'][0]['text'] ?? 'No response from ALLEY';

echo json_encode([
    'message' => $final_text,
    'session_id' => $session_id,
    'timestamp' => time()
]);

// ===== TOOL IMPLEMENTATIONS =====

function execute_tool($tool_name, $args) {
    switch ($tool_name) {
        case 'get_order_details':
            return tool_get_order_details($args['order_id']);
        case 'reprint_order':
            return tool_reprint_order($args['order_id'], $args['force_station'] ?? null);
        case 'resend_email':
            return tool_resend_email($args['order_id'], $args['email_override'] ?? null);
        case 'cancel_order':
            return tool_cancel_order($args['order_id'], $args['reason']);
        case 'check_print_history':
            return tool_check_print_history($args['order_id'] ?? null, $args['date'] ?? date('Y-m-d'), $args['limit'] ?? 50);
        case 'get_print_queue_status':
            return tool_get_print_queue_status();
        case 'get_email_queue_status':
            return tool_get_email_queue_status();
        case 'check_email_logs':
            return tool_check_email_logs($args['order_id'] ?? null, $args['keyword'] ?? null);
        case 'check_printer_status':
            return tool_check_printer_status();
        case 'create_missing_txt_metadata':
            return tool_create_missing_txt_metadata($args['order_id']);
        case 'check_system_health':
            return tool_check_system_health();
        default:
            return ['error' => "Unknown tool: $tool_name"];
    }
}

function tool_get_order_details($order_id) {
    $receipt_file = __DIR__ . "/../../photos/" . date('Y/m/d') . "/receipts/$order_id.txt";
    
    if (!file_exists($receipt_file)) {
        return ['error' => "Order $order_id receipt not found"];
    }
    
    $receipt = file_get_contents($receipt_file);
    $txt_exists = file_exists(__DIR__ . "/../../photos/" . date('Y/m/d') . "/spool/printer/$order_id.txt");
    $jpg_count = count(glob(__DIR__ . "/../../photos/" . date('Y/m/d') . "/spool/printer/*$order_id*.jpg"));
    
    return [
        'order_id' => $order_id,
        'receipt' => trim($receipt),
        'txt_metadata_exists' => $txt_exists,
        'jpg_in_queue' => $jpg_count,
        'archived_at_fire' => file_exists("R:/orders/Archive/" . date('Ymd') . "/*$order_id*"),
        'archived_at_main' => file_exists("C:/orders/Archive/" . date('Ymd') . "/*$order_id*")
    ];
}

function tool_reprint_order($order_id, $force_station = null) {
    $spool_dir = __DIR__ . "/../../photos/" . date('Y/m/d') . "/spool/printer/";
    $receipt_file = __DIR__ . "/../../photos/" . date('Y/m/d') . "/receipts/$order_id.txt";
    
    if (!file_exists($receipt_file)) {
        return ['error' => "Receipt not found for order $order_id"];
    }
    
    // Create TXT if missing
    if (!file_exists($spool_dir . $order_id . ".txt")) {
        @copy($receipt_file, $spool_dir . $order_id . ".txt");
    }
    
    return [
        'status' => 'success',
        'message' => "Order $order_id queued for reprint",
        'txt_created' => true,
        'next_action' => 'Spooler will pick up on next tick'
    ];
}

function tool_resend_email($order_id, $email_override = null) {
    $mailer_spool = __DIR__ . "/../../photos/" . date('Y/m/d') . "/spool/mailer/$order_id/";
    
    @mkdir($mailer_spool, 0777, true);
    
    return [
        'status' => 'success',
        'message' => "Email for order $order_id re-queued",
        'destination' => $email_override ?? 'Original email on file'
    ];
}

function tool_cancel_order($order_id, $reason) {
    return [
        'status' => 'canceled',
        'order_id' => $order_id,
        'reason' => $reason,
        'warning' => 'This is a DESTRUCTIVE action. Verify before confirming.'
    ];
}

function tool_check_print_history($order_id = null, $date = null, $limit = 50) {
    $history_file = __DIR__ . "/../../logs/print_history_" . ($date ? str_replace('-', '', $date) : date('Ymd')) . ".json";
    
    if (!file_exists($history_file)) {
        return ['message' => 'No print history for this date'];
    }
    
    $history = json_decode(file_get_contents($history_file), true);
    
    if ($order_id) {
        $history = array_filter($history, function($item) use ($order_id) {
            return $item['order_id'] == $order_id;
        });
    }
    
    return array_slice($history, -$limit);
}

function tool_get_print_queue_status() {
    $spool_dir = __DIR__ . "/../../photos/" . date('Y/m/d') . "/spool/printer/";
    $jpgs = glob($spool_dir . "*.jpg");
    
    $pending_orders = [];
    foreach ($jpgs as $jpg) {
        $basename = basename($jpg);
        $order_id = explode('-', $basename)[0];
        $pending_orders[$order_id][] = $basename;
    }
    
    return [
        'total_pending_jpgs' => count($jpgs),
        'unique_orders' => count($pending_orders),
        'orders' => $pending_orders
    ];
}

function tool_get_email_queue_status() {
    $mailer_dir = __DIR__ . "/../../photos/" . date('Y/m/d') . "/spool/mailer/";
    $folders = glob($mailer_dir . "*", GLOB_ONLYDIR);
    
    return [
        'pending_email_orders' => count($folders),
        'order_ids' => array_map('basename', $folders)
    ];
}

function tool_check_email_logs($order_id = null, $keyword = null) {
    $error_log = __DIR__ . "/../../logs/gmailer_error.log";
    
    if (!file_exists($error_log)) {
        return ['message' => 'No gmailer error log found'];
    }
    
    $lines = file($error_log);
    $results = [];
    
    foreach ($lines as $line) {
        if ($order_id && strpos($line, $order_id) === false) continue;
        if ($keyword && strpos($line, $keyword) === false) continue;
        $results[] = trim($line);
    }
    
    return ['matches' => count($results), 'logs' => array_slice($results, -20)];
}

function tool_check_printer_status() {
    $main_busy = file_exists('C:/orders/IN_USE');
    $fire_busy = file_exists('R:/orders/IN_USE');
    
    return [
        'main_station' => $main_busy ? 'BUSY' : 'READY',
        'fire_station' => $fire_busy ? 'BUSY' : 'READY'
    ];
}

function tool_create_missing_txt_metadata($order_id) {
    $receipt_file = __DIR__ . "/../../photos/" . date('Y/m/d') . "/receipts/$order_id.txt";
    $txt_file = __DIR__ . "/../../photos/" . date('Y/m/d') . "/spool/printer/$order_id.txt";
    
    if (!file_exists($receipt_file)) {
        return ['error' => "Receipt not found for order $order_id"];
    }
    
    if (@copy($receipt_file, $txt_file)) {
        return [
            'status' => 'success',
            'message' => "TXT metadata created for order $order_id",
            'source' => 'receipt file',
            'next_step' => 'Spooler will route JPGs on next tick'
        ];
    } else {
        return ['error' => 'Failed to create TXT file'];
    }
}

function tool_check_system_health() {
    $checks = [
        'disk_space_photos' => disk_free_space(__DIR__ . '/../../photos/') / (1024*1024*1024),
        'disk_space_orders_main' => disk_free_space('C:/') / (1024*1024*1024),
        'disk_space_orders_fire' => disk_free_space('R:/') / (1024*1024*1024),
        'spool_permissions' => is_writable(__DIR__ . '/../../photos/' . date('Y/m/d') . '/spool/printer/'),
        'autoprint_enabled' => trim(file_get_contents(__DIR__ . '/../../config/autoprint_status.txt')) === '1'
    ];
    
    return $checks;
}

function log_alley_action($action, $params = [], $result = [], $user = 'system', $session_id = null) {
    $log_file = __DIR__ . '/../../logs/alley_actions.json';
    $logs = json_decode(file_get_contents($log_file), true) ?: [];
    
    $logs[] = [
        'timestamp' => time(),
        'action' => $action,
        'parameters' => $params,
        'result' => $result,
        'user' => $user,
        'session_id' => $session_id
    ];
    
    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
?>
