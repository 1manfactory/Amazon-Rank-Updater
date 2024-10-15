<?php
class AmazonAPI {
    private $errorCount = 0;

    function sendRequest($url, $headers, $body)
    {
        // cURL-Initialisierung
        $ch = curl_init($url);

        Debug::log("Sending request to Amazon API: $url", "DEBUG");
    
        // Konfigurieren Sie cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        // Senden Sie die Anfrage
        $response = curl_exec($ch);

        Debug::log("Response:" . print_r($response, true), "DEBUG");
    
        // Überprüfen Sie auf Fehler
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
    
        // Antwort dekodieren
        $responseDecoded = json_decode($response, true);
    
        // Schließen Sie den cURL-Handle
        curl_close($ch);
    
        return $responseDecoded;
    }

    function getRank($asin)
    {
        // API-URL
        $url = "https://" . AWS_HOST . "/paapi5/getitems";        

        Debug::log("Fetching rank for ASIN: $asin");

        if ($_SERVER['PHP_LIVE_MODE'] == 0) {
            Debug::log("Test mode: Returning mock rank", "DEBUG");
            return rand(1000, 1000000);
        }
    
        // Header vorbereiten, aber die Signatur separat generieren
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd'); // Für die Signatur
        $headers = $this->createSignedHeaders($url, $amzDate, $dateStamp, $asin);
            
        // Payload
        $body = [
            'ItemIds' => [$asin],
            'PartnerTag' => AWS_ASSOCIATE_TAG,
            'PartnerType' => 'Associates',
            'Resources' => [
                'BrowseNodeInfo.WebsiteSalesRank'
            ]
        ];
    
        // Senden Sie die Anfrage mit cURL
        $response = $this->sendRequest($url, $headers, $body);

        if ($response === false) {
            $this->errorCount++;
            Debug::log("Error fetching data for ASIN: $asin", "ERROR");
            if ($this->errorCount >= MAX_API_ERRORS) {
                $errorMessage = "Reached maximum number of API errors (".MAX_API_ERRORS."). Aborting process.";
                Debug::log($errorMessage, "CRITICAL");
                Debug::sendErrorEmail("Amazon Rank Updater - Critical API Errors", $errorMessage);
                throw new Exception($errorMessage);
            }            
            return false;
        }        
    
        // Überprüfen, ob der Rang vorhanden ist
        if (isset($response['ItemsResult']['Items'][0]['BrowseNodeInfo']['WebsiteSalesRank']['Rank'])) {
            return $response['ItemsResult']['Items'][0]['BrowseNodeInfo']['WebsiteSalesRank']['Rank'];
        } else {
            Debug::log("No rank found for ASIN: $asin", "WARNING");
            return false;
        }
    }

    // Funktion zur Erstellung einer Signatur für die Amazon API-Anfrage
    private function createSignedHeaders($url, $amzDate, $dateStamp, $asin)
    {
        $method = 'POST';
        $service = 'execute-api';
        $region = AWS_REGION;
        $host = AWS_HOST;
        $requestUri = '/paapi5/getitems';
        $payloadHash = hash('sha256', json_encode([
            'ItemIds' => [$asin],
            'PartnerTag' => AWS_ASSOCIATE_TAG,
            'PartnerType' => 'Associates',
            'Resources' => [
                'BrowseNodeInfo.WebsiteSalesRank'
            ]
        ]));

        $canonicalHeaders = "content-type:application/json\nhost:$host\nx-amz-date:$amzDate\n";
        $signedHeaders = 'content-type;host;x-amz-date';

        $canonicalRequest = "$method\n$requestUri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        $credentialScope = "$dateStamp/$region/$service/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->getSignatureKey(AWS_SECRET_KEY, $dateStamp, $region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential=" . AWS_ACCESS_KEY . "/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        return [
            'Authorization: ' . $authorizationHeader,
            'Content-Type: application/json',
            'X-Amz-Date: ' . $amzDate
        ];
    }

    // HMAC-SHA256-Schlüsselerstellung
    private function getSignatureKey($key, $dateStamp, $regionName, $serviceName)
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return $kSigning;
    }    

}
