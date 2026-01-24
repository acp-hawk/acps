<?php
// update_env.php - handles .env file update from admin panel (only whitelisted vars)
$allowed = ['SYSTEM_NAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'];
$envPath = realpath(__DIR__ . '/../.env.local.');

function get_env_vars($path, $allowed) {
    $vars = array_fill_keys($allowed, '');
    if ($path && file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
                $key = $m[1];
                $val = $m[2];
                if (in_array($key, $allowed)) {
                    $vars[$key] = $val;
                }
            }
        }
    }
    return $vars;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vars = get_env_vars($envPath, $allowed);
    foreach ($allowed as $key) {
        $formKey = 'env_' . strtolower(str_replace('SYSTEM_', '', $key));
        if (isset($_POST[$formKey])) {
            $vars[$key] = $_POST[$formKey];
        }
    }
    // Write back only allowed vars
    $lines = [];
    foreach ($vars as $k => $v) {
        $lines[] = "$k=$v";
    }
    file_put_contents($envPath, implode("\n", $lines));
    echo json_encode(['status' => 'success', 'message' => 'Settings saved.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(get_env_vars($envPath, $allowed));
    exit;
}
http_response_code(405);
?>