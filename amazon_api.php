<?php

namespace MyProject;

class AmazonAPI
{
    private int $errorCount = 0;

    /**
     * Send a request using cURL
     * @param string $url
     * @param array<string> $headers
     * @param array<string, array<int, string>|string> $body
     * @return string
     * @throws \Exception
     */
    public function sendRequest(string $url, array $headers, array $body): string
    {
        $ch = curl_init($url);

        Debug::log("Sending request to Amazon API: $url", "DEBUG");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL Error: $error");
        }

        curl_close($ch);

        Debug::log("Response:" . print_r($response, true), "DEBUG");

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }

        if (!is_string($response)) {
            throw new \Exception('Expected response to be a string, but received: ' . gettype($response));
        }

        if ($httpCode >= 400) {
            throw new \Exception("HTTP Error: $httpCode. Response: $response");
        }

        return $response;
    }

    /**
     * Get the rank for a given ASIN
     * @param string $asin
     * @return int|false
     * @throws \Exception
     */
    public function getRank(string $asin)
    {
        // API-URL
        $url = "https://" . AWS_HOST . "/paapi5/getitems";

        Debug::log("Fetching rank for ASIN: $asin");

        if ($_SERVER['PHP_LIVE_MODE'] == 0) {
            Debug::log("Test mode: Returning mock rank", "DEBUG");
            return rand(1000, 1000000);
        }

        // Prepare Header
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd'); // Für die Signatur
        $headers = $this->createSignedHeaders($url, $amzDate, $dateStamp, $asin);

        // Payload
        $body = [
            'ItemIds' => [$asin],
            'PartnerTag' => AWS_ASSOCIATE_TAG,
            'PartnerType' => 'Associates',
            'Resources' => [
                'BrowseNodeInfo.WebsiteSalesRank',
                'ItemInfo.Title'
            ]
        ];

        try {
            $response = $this->sendRequest($url, $headers, $body);
        } catch (\Exception $e) {
            $this->errorCount++;
            Debug::log("Error fetching data for ASIN: $asin. " . $e->getMessage(), "ERROR");

            if ($this->errorCount >= MAX_API_ERRORS) {
                $errorMessage = "Reached maximum number of API errors (" . MAX_API_ERRORS . "). Aborting process.";
                Debug::log($errorMessage, "CRITICAL");
                Debug::sendErrorEmail("Amazon Rank Updater - Critical API Errors", $errorMessage);
                throw new \Exception($errorMessage);
            }

            return false;
        }

        // Decode the JSON response
        $responseDecoded = json_decode($response, true);

        // Assert that $responseDecoded is an array
        assert(is_array($responseDecoded));

        if (!is_array($responseDecoded)) {
            $this->errorCount++;
            Debug::log("Failed to decode API response for ASIN: $asin", "ERROR");
            return false;
        }

        // Check if the necessary structure exists and validate arrays
        if (
            isset($responseDecoded['ItemsResult']) &&
            is_array($responseDecoded['ItemsResult']) &&
            isset($responseDecoded['ItemsResult']['Items'][0]) &&
            is_array($responseDecoded['ItemsResult']['Items'][0]) &&
            isset($responseDecoded['ItemsResult']['Items'][0]['BrowseNodeInfo']['WebsiteSalesRank']['Rank'])
        ) {
            $rank = (int)$responseDecoded['ItemsResult']['Items'][0]['BrowseNodeInfo']['WebsiteSalesRank']['Rank'];
        } else {
            Debug::log("No rank found for ASIN: $asin", "WARNING");
            return false;
        }

        // Check if the title is available
        $title = $responseDecoded['ItemsResult']['Items'][0]['ItemInfo']['Title']['DisplayValue'] ?? 'Unknown Title';

        Debug::log("Rank for ASIN $asin ($title): $rank", "INFO");

        return $rank;
    }

    /**
     * Create signed headers for Amazon API request
     * @param string $url
     * @param string $amzDate
     * @param string $dateStamp
     * @param string $asin
     * @return array<string, string>
     */
    private function createSignedHeaders($url, $amzDate, $dateStamp, $asin): array
    {
        $method = 'POST';
        $service = 'execute-api';
        $requestUri = '/paapi5/getitems';

        $payload = json_encode([
            'ItemIds' => [$asin],
            'PartnerTag' => AWS_ASSOCIATE_TAG,
            'PartnerType' => 'Associates',
            'Resources' => [
                'BrowseNodeInfo.WebsiteSalesRank',
                'ItemInfo.Title'
            ]
        ]);

        if ($payload === false) {
            throw new \Exception('Failed to encode payload to JSON.');
        }

        $payloadHash = hash('sha256', $payload);

        $canonicalHeaders = "content-type:application/json\nhost:" . AWS_HOST . "\nx-amz-date:$amzDate\n";
        $signedHeaders = 'content-type;host;x-amz-date';

        $canonicalRequest = "$method\n$requestUri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        $credentialScope = "$dateStamp/" . AWS_REGION . "/$service/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->getSignatureKey(AWS_SECRET_KEY, $dateStamp, AWS_REGION, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential=" . AWS_ACCESS_KEY . "/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        return [
            'Authorization' => $authorizationHeader,
            'Content-Type' => 'application/json',
            'X-Amz-Date' => $amzDate
        ];
    }

    /**
     * Generate HMAC-SHA256 signature key
     * @param string $key
     * @param string $dateStamp
     * @param string $regionName
     * @param string $serviceName
     * @return string
     */
    private function getSignatureKey($key, $dateStamp, $regionName, $serviceName)
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return $kSigning;
    }
}
