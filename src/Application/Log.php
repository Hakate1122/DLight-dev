<?php

namespace DLight\Application;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

use function sprintf;

/**
 * **Application Logger**
 * 
 * A simple logger implementation adhering to PSR-3 standards.
 * 
 * This logger writes log messages to a specified file with different log levels.
 */
class Log implements LoggerInterface
{
    private $logFilePath;

    /**
     * Constructor to initialize the logger with a log file path.
     * 
     * @param string|null $logFilePath Path to the log file. If null, defaults to 'logs/app.log' in the INDEX_DIR.
     */
    public function __construct(?string $logFilePath = null)
    {
        $this->logFilePath = $logFilePath ?? env('LOG_FILE', INDEX_DIR . 'logs/app.log');
    }

    /**
     * Log with a given level.
     * @param string $level Log level (e.g., emergency, alert, critical, error, warning, notice, info, debug)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (env('LOG_LEVEL') === null) {
            return;
        }
        $level = env('LOG_LEVEL', $level);
        $contextString = $context === [] ? '' : ' ' . json_encode($context);

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $logEntry = sprintf(
            '[%s] [%s]: %s%s' . PHP_EOL,
            $now,
            strtoupper($level),
            $message,
            $contextString
        );

        @file_put_contents($this->logFilePath, $logEntry, FILE_APPEND);
    }

    /* ----- Define PSR-3 methods ----- */

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /* ----- Fast Log Method ----- */
    /**
     * Fast log method for quick logging without instantiating the class. Not implementing context.
     *
     * @param string $logFilePath Path to the log file
     * @param string $message Log message
     */
    public static function fast(string $logFilePath, string $message): void
    {
        $logger = new self($logFilePath);
        $logger->log(env('LOG_LEVEL', LogLevel::INFO), $message);
    }
}