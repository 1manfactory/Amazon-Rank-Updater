<?php
class AmazonAPI {
    private $isLive = false;

    public function setLive($value) {
        $this->isLive = $value;
    }

    public function getRank($asin) {
        Debug::log("Fetching rank for ASIN: $asin");
        
        if (!$this->isLive) {
            Debug::log("Test mode: Returning mock rank", "DEBUG");
            return rand(1000, 1000000);
        }

        $params = [
            'AWSAccessKeyId' => AWS_ACCESS_KEY,
            'AssociateTag' => AWS_ASSOCIATE_TAG,
            'Operation' => 'ItemLookup',
            'ItemId' => $asin,
            'ResponseGroup' => 'SalesRank',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => '2013-08-01'
        ];
        
        ksort($params);
        $stringToSign = $this->buildSignatureString($params);
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, AWS_SECRET_KEY, true));
        $params['Signature'] = $signature;
        
        $url = 'http://webservices.amazon.com/onca/xml?' . http_build_query($params);
        
        Debug::log("Sending request to Amazon API: $url", "DEBUG");
        
        $response = @file_get_contents($url);
        if ($response === false) {
            Debug::log("Error fetching data for ASIN: $asin", "ERROR");
            return false;
        }
        
        $xml = simplexml_load_string($response);
        if (isset($xml->Items->Item->SalesRank)) {
            $rank = (int)$xml->Items->Item->SalesRank;
            Debug::log("Rank for ASIN $asin: $rank", "INFO");
            return $rank;
        }
        
        Debug::log("No rank found for ASIN: $asin", "WARNING");
        return false;
    }

    private function buildSignatureString($params) {
        $stringToSign = "GET\nwebservices.amazon.com\n/onca/xml\n";
        $stringToSign .= http_build_query($params);
        return $stringToSign;
    }
}
