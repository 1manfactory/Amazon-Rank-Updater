<?php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');

// Source table configuration
define('SOURCE_TABLE', 'product_meta_data');
define('SOURCE_ASIN_COLUMN', 'amazon_asin');

// Target table configuration
define('TARGET_TABLE', 'rank');

// Amazon API configuration
define('AWS_ACCESS_KEY', 'YOUR_AWS_ACCESS_KEY');
define('AWS_SECRET_KEY', 'YOUR_AWS_SECRET_KEY');
define('AWS_ASSOCIATE_TAG', 'YOUR_ASSOCIATE_TAG');
define('AWS_HOST', 'webservices.amazon.com');
define('AWS_REGION', 'us-east-1');

// E-mail configuration (leave empty to disable email notifications)
define('ERROR_EMAIL_TO', '');
define('ERROR_EMAIL_FROM', '');
define('ERROR_EMAIL_REPLY_TO', '');

// Max. tries for API calls
define('MAX_API_ERRORS', 3);
