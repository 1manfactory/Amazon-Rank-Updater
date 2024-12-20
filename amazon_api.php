<?php

namespace MyProject;

use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\api\DefaultApi;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\PartnerType;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\SearchItemsRequest;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\SearchItemsResource;
use Amazon\ProductAdvertisingAPI\v1\Configuration;
use GuzzleHttp\Client;
use Exception;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\GetItemsRequest;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\GetItemsResource;

class AmazonAPI
{
    private int $errorCount = 0;

    public function checkAWSCredentials(): void
    {
        // configure Product Advertising API
        $config = new Configuration();
        $config->setAccessKey(AWS_ACCESS_KEY);
        $config->setSecretKey(AWS_SECRET_KEY);
        $config->setHost('webservices.amazon.de');
        $config->setRegion('eu-west-1');

        // init apiInstance
        $apiInstance = new DefaultApi(new Client(), $config);

        // create simple search request for testing
        $searchItemsRequest = new SearchItemsRequest();
        $searchItemsRequest->setSearchIndex('Books');
        $searchItemsRequest->setKeywords('Harry Potter');
        $searchItemsRequest->setItemCount(1);
        $searchItemsRequest->setPartnerTag(AWS_ASSOCIATE_TAG);
        $searchItemsRequest->setPartnerType(PartnerType::ASSOCIATES);

        $searchItemsRequest->setResources([
            SearchItemsResource::ITEM_INFOTITLE,
            SearchItemsResource::OFFERSLISTINGSPRICE
        ]);

        try {
            // send test/search request
            $response = $apiInstance->searchItems($searchItemsRequest);

            // do we have a valid answer?
            if ($response->getSearchResult() !== null) {
                Debug::log("AWS credentials are valid.");
                return;
            } else {
                Debug::log("Invalid response received from Amazon API.", 'CRITICAL');
                throw new Exception("Invalid response received from Amazon API.");
            }
        } catch (Exception $e) {
            // send email and exit on error
            if (!empty(ERROR_EMAIL_TO)) {
                $to = ERROR_EMAIL_TO;
                $subject = 'AWS Credentials Warning';
                $message = "Warning: AWS credentials validation failed.\n\nError: " . $e->getMessage() . "\n\n" . $e->getTraceAsString();
                $headers = "From: " . ERROR_EMAIL_FROM . "\r\n" .
                    "Reply-To: " . ERROR_EMAIL_REPLY_TO . "\r\n" .
                    "X-Mailer: PHP/" . phpversion();

                mail($to, $subject, $message, $headers);
            }

            Debug::log("Warning: AWS credentials validation failed.\n\nError: " . $e->getMessage() . "\n\n" . $e->getTraceAsString(), 'CRITICAL');

            throw new Exception("AWS credentials are invalid. Exiting program.\n");
        }
    }

    /**
     * Get the rank and the title for a given ASIN
     * @param string $asin
     * @return int|false
     * @throws Exception
     */
    public function getRankAndTitle(string $asin)
    {
        Debug::log("Starting API call to retrieve rank for ASIN: $asin", "DEBUG");

        // configure API client
        $config = new Configuration();
        $config->setAccessKey(AWS_ACCESS_KEY);
        $config->setSecretKey(AWS_SECRET_KEY);
        $config->setHost('webservices.amazon.de');
        $config->setRegion('eu-west-1');

        $apiInstance = new DefaultApi(
            new Client(),
            $config
        );

        // create getItem request
        $getItemsRequest = new GetItemsRequest();
        $getItemsRequest->setItemIds([$asin]);
        $getItemsRequest->setPartnerTag(AWS_ASSOCIATE_TAG);
        $getItemsRequest->setPartnerType(PartnerType::ASSOCIATES);

        Debug::log("Configuring GetItemsRequest with PartnerTag: " . AWS_ASSOCIATE_TAG . " and ASIN: $asin", "DEBUG");

        // set resources
        $getItemsRequest->setResources([
            GetItemsResource::BROWSE_NODE_INFOWEBSITE_SALES_RANK,
            GetItemsResource::ITEM_INFOTITLE
        ]);

        try {
            Debug::log("Sending GetItemsRequest to Amazon PA-API for ASIN: $asin", "DEBUG");
            $response = $apiInstance->getItems($getItemsRequest);

            if ($response->getErrors() !== null) {
                foreach ($response->getErrors() as $error) {
                    Debug::log("Error in API response for ASIN $asin: " . $error->getMessage(), "ERROR");
                }
            } elseif ($response->getItemsResult() === null) {
                Debug::log("No ItemsResult in API response for ASIN: $asin", "WARNING");
            }

            if ($response->getItemsResult() !== null && isset($response->getItemsResult()->getItems()[0])) {
                Debug::log("Item found in ItemsResult for ASIN: $asin", "DEBUG");
            } else {
                Debug::log("No items found in ItemsResult for ASIN: $asin", "WARNING");
            }

            // check for data in response
            if (
                $response->getItemsResult() !== null &&
                isset($response->getItemsResult()->getItems()[0])
            ) {
                $item = $response->getItemsResult()->getItems()[0];

                // do we have WebsiteSalesRank?
                $rank = null;
                if ($item->getBrowseNodeInfo() !== null && $item->getBrowseNodeInfo()->getWebsiteSalesRank() !== null) {

                    Debug::log("WebsiteSalesRank data found for ASIN: $asin", "DEBUG");

                    $websiteSalesRank = $item->getBrowseNodeInfo()->getWebsiteSalesRank();

                    // store all data for debuggin purposes
                    Debug::log("WebsiteSalesRank structure for ASIN $asin: " . print_r($websiteSalesRank, true), "DEBUG");

                    // retrieve sales rank
                    if (method_exists($websiteSalesRank, 'getSalesRank')) {
                        $rank = $websiteSalesRank->getSalesRank();
                        Debug::log("Rank for ASIN $asin: " . $rank, "DEBUG");
                    } else {
                        Debug::log("Rank information not accessible in WebsiteSalesRank structure for ASIN: $asin", "WARNING");
                    }
                }

                // get the title of the product
                $title = $item->getItemInfo()->getTitle()->getDisplayValue() ?: 'Unknown Title';

                if ($rank !== null) {
                    Debug::log("Rank for ASIN $asin ($title): $rank", "INFO");
                    return ['rank' =>$rank, 'title' => $title];
                } else {
                    Debug::log("No rank found for ASIN: $asin", "WARNING");
                    return false;
                }
            } else {
                Debug::log("No rank found for ASIN: $asin", "WARNING");
                return false;
            }
        } catch (Exception $e) {
            Debug::log("Exception encountered while fetching data for ASIN $asin: " . $e->getMessage(), "ERROR");

            $this->errorCount++;
            Debug::log("Error fetching data for ASIN: $asin. " . $e->getMessage(), "ERROR");

            // don't stress your luck, so skip if we have too many API errors
            if ($this->errorCount >= MAX_API_ERRORS) {
                $errorMessage = "Reached maximum number of API errors (" . MAX_API_ERRORS . "). Aborting process.";
                Debug::log($errorMessage, "CRITICAL");
                Debug::sendErrorEmail("Amazon Rank Updater - Critical API Errors", $errorMessage);
                throw new Exception($errorMessage);
            }

            Debug::log("Ending getRank method for ASIN: $asin", "DEBUG");

            return false;
        }
    }
}
