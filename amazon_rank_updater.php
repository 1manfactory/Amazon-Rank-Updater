<?php

// Check if the script was called directly
if (php_sapi_name() !== 'cli' || !isset($_SERVER['WRAPPER_SCRIPT'])) {
    die("Error: This script must be run through the wrapper.\n");
}

require_once 'config.php';
require_once 'database.php';
require_once 'amazon_api.php';
require_once 'debug.php';

Debug::init();

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

    $db = new Database();
    $api = new AmazonAPI();
    
    // Set live mode based on environment variable
    $liveMode = isset($_SERVER['PHP_LIVE_MODE']) && $_SERVER['PHP_LIVE_MODE'] == 1;
    $api->setLive($liveMode);
    
    Debug::setVerbose(isset($_SERVER['PHP_VERBOSE']) && $_SERVER['PHP_VERBOSE'] == 1);
    
    if ($liveMode) {
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
            sleep(300); // Wait 5 minutes before retrying
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
