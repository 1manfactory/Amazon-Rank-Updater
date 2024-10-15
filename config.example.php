<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');

// Source table configuration
define('SOURCE_TABLE', 'titlemeta');
define('SOURCE_ASIN_COLUMN', 'amazon_asin');

// Target table configuration
define('TARGET_TABLE', 'rank');

// Amazon API configuration
define('AWS_ACCESS_KEY', 'YOUR_AWS_ACCESS_KEY');
define('AWS_SECRET_KEY', 'YOUR_AWS_SECRET_KEY');
define('AWS_ASSOCIATE_TAG', 'YOUR_ASSOCIATE_TAG');

// API limits and time intervals
define('API_REQUEST_INTERVAL', 10); // Seconds between requests
define('DAILY_WAIT_TIME', 86400); // 24 hours in seconds

// E-mail configuration (leave empty to disable email notifications)
define('ERROR_EMAIL_TO', '');