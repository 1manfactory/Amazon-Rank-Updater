<?php

namespace MyProject;

class Debug
{
    private static string $logFile = 'debug.log';
    private static string $emailTo;

    public static function init(): void
    {
        self::$emailTo = ERROR_EMAIL_TO;
    }

    public static function getLogFilePath(): string
    {
        return self::$logFile;
    }

    public static function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = str_replace(["\r\n", "\r", "\n"], ' ', $message); // Replace line breaks with spaces
        $logMessage = "[$timestamp] [$level] $message";

        // Log to file
        if (file_put_contents(self::getLogFilePath(), $logMessage . PHP_EOL, FILE_APPEND) === false) {
            error_log("Failed to write to log file: " . self::$logFile);
        }

        // Log to syslog for ERROR and CRITICAL levels
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            $priority = ($level == 'CRITICAL') ? LOG_CRIT : LOG_ERR;
            openlog("amazon_rank_updater", LOG_PID | LOG_CONS, LOG_USER);
            syslog($priority, $logMessage);
            closelog();
        }

        if ($_SERVER['PHP_VERBOSE'] == 1) {
            echo $logMessage . PHP_EOL;
        }
    }

    public static function sendErrorEmail(string $subject, string $message): void
    {
        if (empty(self::$emailTo)) {
            self::log("Email notification disabled. Error: $subject", "WARNING");
            return;
        }

        $headers = 'From: ' . ERROR_EMAIL_FROM . "\r\n" .
            'Reply-To: ' . ERROR_EMAIL_REPLY_TO . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        if (mail(self::$emailTo, $subject, $message, $headers)) {
            self::log("Error email sent to " . self::$emailTo, "INFO");
        } else {
            self::log("Failed to send error email", "ERROR");
        }
    }
}
