<?php
chdir(__DIR__);

// Set action before headers
$_GET['action'] = 'tick_mailer';

// Capture headers
ob_start();

// Include spooler
require 'config/api/spooler.php';

// Get output
$output = ob_get_clean();
echo $output;