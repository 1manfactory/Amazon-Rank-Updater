<?php

namespace MyProject;

require_once __DIR__ . '/vendor/autoload.php';

function validateAWSCredentials(): void
{
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
            if (in_array($value, $placeholders)) {
                $message = "$key is not properly configured.";
                Debug::log($message, "CRITICAL");
                throw new \Exception($message);
            }
        }
        Debug::log("AWS credentials configuration passed.");
    } catch (\Exception $e) {
        Debug::log("AWS credentials configuration failed: " . $e->getMessage(), "CRITICAL");
        Debug::sendErrorEmail("Amazon Rank Updater - Configuration Error", "AWS credentials configuration failed: " . $e->getMessage());
        exit(1);
    }
}


function checkAndCreateTables(): void
{
    $db = new Database();

    try {
        $db->checkSourceTable();
        Debug::log("Source table check passed.");
    } catch (\Exception $e) {
        Debug::log("Source table check failed: " . $e->getMessage(), "CRITICAL");
        exit(1);
    }

    if (!$db->checkTargetTable()) {
        Debug::log("Target table does not exist. Attempting to create...");
        try {
            $db->createTargetTable();
            Debug::log("Target table created successfully.");
        } catch (\Exception $e) {
            Debug::log("Failed to create target table: " . $e->getMessage(), "CRITICAL");
            Debug::log("Please run the following SQL to create the table manually:");
            Debug::log($db->getCreateTableStatement());
            exit(1);
        }
    } else {
        Debug::log("Target table exists.");
    }
}

function main(): void
{
    checkAndCreateTables();
    validateAWSCredentials();
    $amazonAPI = new AmazonAPI();
    $amazonAPI->checkAWSCredentials();
    //$amazonAPI->getRank('B004LOWNOM'); // ok
    //$amazonAPI->getRank('B004TM9J8Y'); // nicht ok
    //$amazonAPI->getRank('B00597TCBW'); // nicht ok
    //exit(1);

    
    $db = new Database();


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

                $rank = false;

                if ($_SERVER['PHP_LIVE_MODE'] == 1) {
                    $rank = $amazonAPI->getRank($asin);
                } elseif ($_SERVER['PHP_DEBUG_MODE'] == 1) {
                    // fake mockdata
                    $rank = rand(1000, 1000000);
                }

                if ($rank !== false) {
                    $db->updateRank($asin, $rank);
                    Debug::log("Updated ASIN: $asin, Rank: $rank");
                }

                sleep(API_REQUEST_INTERVAL);
            }

            Debug::log("Completed update cycle. Waiting for next day...");
            sleep(DAILY_WAIT_TIME);
        } catch (\Exception $e) {
            $errorMessage = "Critical error in main loop: " . $e->getMessage();
            Debug::log($errorMessage, "CRITICAL");
            Debug::sendErrorEmail("Amazon Rank Updater - Critical Error", $errorMessage);
            exit(1);
        }
    }
}
