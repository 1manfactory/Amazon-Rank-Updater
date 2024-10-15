<?php

// Check if the script was called directly
if (php_sapi_name() !== 'cli' || !isset($_SERVER['WRAPPER_SCRIPT'])) {
    die("Error: This script must be run through the wrapper.\n");
}

require_once 'config.php';
require_once 'database.php';
require_once 'amazon_api.php';
require_once 'debug.php';

// Initialize Debug and get the log file path
Debug::init();
$logFilePath = Debug::getLogFilePath();

// Display startup information
echo "Amazon Rank Updater\n";
echo "===================\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "Log file: $logFilePath\n";
echo "Environment variables:\n";
echo "PHP_VERBOSE: " . ($_SERVER['PHP_VERBOSE'] == 1 ? 'Set' : 'Not set') . "\n";
echo "PHP_LIVE_MODE: " . ($_SERVER['PHP_LIVE_MODE'] == 1 ? 'Set' : 'Not set') . "\n";
echo "===================\n\n";

function validateAWSCredentials() {
    $credentials = [
        'AWS_ACCESS_KEY' => AWS_ACCESS_KEY,
        'AWS_SECRET_KEY' => AWS_SECRET_KEY,
        'AWS_ASSOCIATE_TAG' => AWS_ASSOCIATE_TAG
    ];

    $placeholders = [
        'YOUR_AWS_ACCESS_KEY',
        'YOUR_AWS_SECRET_KEY',
        'YOUR_ASSOCIATE_TAG'
    ];

    try {
        foreach ($credentials as $key => $value) {
            if (empty($value) || in_array($value, $placeholders)) {
                $message="$key is not properly configured.";
                Debug::log($message, "CRITICAL");
                throw new Exception($message);
            }
        }
        Debug::log("AWS credentials configuration passed.");
    } catch (Exception $e) {
        Debug::log("AWS credentials configuration failed: " . $e->getMessage(), "CRITICAL");
        Debug::sendErrorEmail("Amazon Rank Updater - Configuration Error", "AWS credentials configuration failed: " . $e->getMessage());
        exit(1);
    }
}


function checkAndCreateTables() {
    $db = new Database();
    
    try {
        $db->checkSourceTable();
        Debug::log("Source table check passed.");
    } catch (Exception $e) {
        Debug::log("Source table check failed: " . $e->getMessage(), "CRITICAL");
        exit(1);
    }

    if (!$db->checkTargetTable()) {
        Debug::log("Target table does not exist. Attempting to create...");
        try {
            $db->createTargetTable();
            Debug::log("Target table created successfully.");
        } catch (Exception $e) {
            Debug::log("Failed to create target table: " . $e->getMessage(), "CRITICAL");
            Debug::log("Please run the following SQL to create the table manually:");
            Debug::log($db->getCreateTableStatement());
            exit(1);
        }
    } else {
        Debug::log("Target table exists.");
    }
}

function main() {
    checkAndCreateTables();
    validateAWSCredentials();
    
    $db = new Database();
    $api = new AmazonAPI();
    
      if ($_SERVER['PHP_LIVE_MODE'] == 1) {
        Debug::log("Running in LIVE mode. API calls will be made to Amazon.", "WARNING");
    } else {
        Debug::log("Running in TEST mode. No actual API calls will be made.", "INFO");
    }
    
    while (true) {
        try {
            $asins = $db->getASINsToUpdate();
            
            foreach ($asins as $asin) {
                Debug::log("Processing ASIN: $asin");
                $rank = $api->getRank($asin);
                if ($rank !== false) {
                    $db->updateRank($asin, $rank);
                    Debug::log("Updated ASIN: $asin, Rank: $rank");
                }
                
                sleep(API_REQUEST_INTERVAL);
            }
            
            Debug::log("Completed update cycle. Waiting for next day...");
            sleep(DAILY_WAIT_TIME);
        } catch (Exception $e) {
            $errorMessage = "Critical error in main loop: " . $e->getMessage();
            Debug::log($errorMessage, "CRITICAL");
            Debug::sendErrorEmail("Amazon Rank Updater - Critical Error", $errorMessage);
            exit(1);
        }
    }
}

try {
    main();
} catch (Exception $e) {
    $fatalErrorMessage = "Fatal error: " . $e->getMessage();
    Debug::log($fatalErrorMessage, "CRITICAL");
    Debug::sendErrorEmail("Amazon Rank Updater - Fatal Error", $fatalErrorMessage);
    exit(1);
}
