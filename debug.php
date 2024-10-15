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

    public static function getLogFilePath() {
        return self::$logFile;
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
        if (empty(self::$emailTo)) {
            self::log("Email notification disabled. Error: $subject", "WARNING");
            return;
        }

        $headers = 'From: amazon-rank-updater@yourdomain.com' . "\r\n" .
            'Reply-To: noreply@yourdomain.com' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        if (mail(self::$emailTo, $subject, $message, $headers)) {
            self::log("Error email sent to " . self::$emailTo, "INFO");
        } else {
            self::log("Failed to send error email", "ERROR");
        }
    }
}
