1. **php-wrapper.sh**

```bash
#!/bin/bash

# Wrapper script for the Amazon Rank Updater PHP program

# Function for help text
show_help() {
    cat << EOF
Usage: ${0##*/} [OPTION]... [FILE]...
Wrapper for the Amazon Rank Updater PHP program with additional functionality.

Options:
    -h, --help      Display this help and exit
    -v, --verbose   Increase verbosity
    -o, --output    Specify output file
    --debug         Run in debug mode (enabled by default)

Examples:
    ${0##*/} input.txt
    ${0##*/} -v -o output.txt input1.txt input2.txt

For more information, see the full documentation at:
https://example.com/amazon-rank-updater-docs
EOF
}

# Default values
VERBOSE=0
OUTPUT_FILE=""
DEBUG=1  # Debug mode enabled by default
PHP_SCRIPT="amazon_rank_updater.php"  # Name of the PHP script

# Process command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -v|--verbose)
            VERBOSE=1
            shift
            ;;
        -o|--output)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        --debug)
            DEBUG=1
            shift
            ;;
        *)
            break
            ;;
    esac
done

# Check if input files were specified
if [ $# -eq 0 ]; then
    echo "Error: No input files specified." >&2
    show_help
    exit 1
fi

# Set environment variables based on options
[ $VERBOSE -eq 1 ] && export PHP_VERBOSE=1
[ $DEBUG -eq 1 ] && export PHP_DEBUG=1

# Set an environment variable to indicate that the wrapper is being used
export WRAPPER_SCRIPT=1

# Build the command for the PHP script
CMD="php $PHP_SCRIPT"
[ -n "$OUTPUT_FILE" ] && CMD="$CMD -o $OUTPUT_FILE"

# Execute the PHP script with the remaining arguments
if [ $VERBOSE -eq 1 ]; then
    echo "Executing: $CMD $@"
fi

exec $CMD "$@"
```

2. **amazon_rank_updater.php**

```php
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

function main() {
    $db = new Database();
    $api = new AmazonAPI();
    
    // Set this to true to actually perform API calls
    $api->setLive(false);
    
    // Set this to true for detailed debug output
    Debug::setVerbose(true);
    
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
```

3. **config.php**

```php
<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');

// Amazon API configuration
define('AWS_ACCESS_KEY', 'YOUR_AWS_ACCESS_KEY');
define('AWS_SECRET_KEY', 'YOUR_AWS_SECRET_KEY');
define('AWS_ASSOCIATE_TAG', 'YOUR_ASSOCIATE_TAG');

// API limits and time intervals
define('API_REQUEST_INTERVAL', 10); // Seconds between requests
define('DAILY_WAIT_TIME', 86400); // 24 hours in seconds

// E-mail configuration
define('ERROR_EMAIL_TO', 'your-email@example.com');
```

4. **database.php**

```php
<?php
class Database {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8");
    }

    public function getASINsToUpdate() {
        $sql = "SELECT DISTINCT tm.amazon_asin 
                FROM titlemeta tm
                LEFT JOIN rank r ON tm.amazon_asin = r.asin AND r.date = CURDATE()
                WHERE r.asin IS NULL
                LIMIT 1000";
        $result = $this->conn->query($sql);
        
        if ($result === false) {
            throw new Exception("Database query error: " . $this->conn->error);
        }
        
        $asins = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $asins[] = $row["amazon_asin"];
            }
        }
        return $asins;
    }

    public function updateRank($asin, $rank) {
        $sql = "INSERT INTO rank (asin, date, rank) VALUES (?, CURDATE(), ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        $stmt->bind_param("si", $asin, $rank);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    }
}
```

5. **amazon_api.php**

```php
<?php
class AmazonAPI {
    private $isLive = false;

    public function setLive($value) {
        $this->isLive = $value;
    }

    public function getRank($asin) {
        Debug::log("Fetching rank for ASIN: $asin");
        
        if (!$this->isLive) {
            Debug::log("Debug mode: Returning mock rank", "DEBUG");
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
```

6. **debug.php**

```php
<?php
class Debug {
    private static $verbose = false;
    private static $logFile = 'debug.log';
    private static $emailTo;

    public static function init() {
        self::$emailTo = ERROR_EMAIL_TO;
    }

    public static function setVerbose($value) {
        self::$verbose = $value;
    }

    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message";
        
        // Log to file
        file_put_contents(self::$logFile, $logMessage . PHP_EOL, FILE_APPEND);
        
        // Log to syslog for ERROR and CRITICAL levels
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            $priority = ($level == 'CRITICAL') ? LOG_CRIT : LOG_ERR;
            openlog("amazon_rank_updater", LOG_PID | LOG_CONS, LOG_USER);
            syslog($priority, $logMessage);
            closelog();
        }
        
        if (self::$verbose) {
            echo $logMessage . PHP_EOL;
        }
    }

    public static function sendErrorEmail($subject, $message) {
        $headers = 'From: amazon-rank-updater@yourdomain.com' . "\r\n" .
            'Reply-To: noreply@yourdomain.com' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        mail(self::$emailTo, $subject, $message, $headers);
    }
}
```

7. **README.md**

```markdown
# Amazon Rank Updater

This project is designed to update Amazon product ranks for a list of ASINs stored in a database. It uses the Amazon Product Advertising API to fetch the latest sales rank for each product.

## Features

- Fetches ASINs from a local database
- Retrieves sales rank data from Amazon API
- Updates the database with the latest rank information
- Includes debug mode and verbose logging
- Runs as a continuous process, updating ranks daily
- Sends email notifications for critical errors

## Requirements

- PHP 7.0 or higher
- MySQL database
- Amazon Product Advertising API credentials

## Installation

1. Clone this repository:
   ```
   git clone https://github.com/yourusername/amazon-rank-updater.git
   ```

2. Install dependencies (if any):
   ```
   composer install
   ```

3. Set up your database and update the `config.php` file with your credentials.

4. Update `config.php` with your Amazon API credentials and email settings.

## Usage

Run the script using the provided wrapper:

```
./php-wrapper.sh [options]
```

Options:
- `-h, --help`: Display help information
- `-v, --verbose`: Increase verbosity
- `-o, --output`: Specify output file
- `--debug`: Run in debug mode (enabled by default)

## Configuration

Edit the `config.php` file to set:
- Database connection details
- Amazon API credentials
- API request intervals and daily update frequency
- Error notification email address

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
