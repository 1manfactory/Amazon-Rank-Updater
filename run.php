<?php

namespace MyProject;

require_once 'main.php';
require_once 'database.php';
require_once 'config.php';
require_once 'amazon_api.php';
require_once 'debug.php';

// Check if the script was called directly
if (php_sapi_name() !== 'cli' || !isset($_SERVER['WRAPPER_SCRIPT'])) {
    die("Error: This script must be run through the wrapper.\n");
}

// Initialize Debug and get the log file path
Debug::init();
$logFilePath = Debug::getLogFilePath();

echo "Amazon Rank Updater\n";
echo "===================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "Log file: $logFilePath\n";
echo "Environment variables:\n";
echo "PHP_VERBOSE: " . ($_SERVER['PHP_VERBOSE'] == 1 ? 'Set' : 'Not set') . "\n";
echo "PHP_LIVE_MODE: " . ($_SERVER['PHP_LIVE_MODE'] == 1 ? 'Set' : 'Not set') . "\n";
echo "PHP_DEBUG_MODE: " . ($_SERVER['PHP_DEBUG_MODE'] == 1 ? 'Set' : 'Not set') . "\n";
echo "===================\n\n";

try {
    main(); // Starte die Hauptlogik
} catch (\Exception $e) {
    $fatalErrorMessage = "Fatal error: " . $e->getMessage();
    Debug::log($fatalErrorMessage, "CRITICAL");
    Debug::sendErrorEmail("Amazon Rank Updater - Fatal Error", $fatalErrorMessage);
    exit(1);
}
