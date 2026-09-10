<?php

namespace DLight\Reports;

use DLight\Reports\Handler;
use DLight\Reports\Render\Html;
use DLight\Reports\Render\Cli;

/**
 * Report - Global error and exception handling class
 * 
 * This class provides a centralized way to handle errors and exceptions in a DLight application. It allows for logging, rendering, and reporting of errors in both CLI and web environments.
 */
final class Report
{
    private static ?Handler $handler = null;

    /**
     * Setup the global error and exception handler
     *
     * @param bool $saveLog Whether to save logs to a file
     * @param string $logFile The log file path
     * @param mixed $renderer Custom renderer instance
     */
    public static function setup(bool $saveLog = true, string $logFile = 'storage/logs/errors.log', $renderer = null): void
    {
        self::$handler = new Handler($saveLog, $logFile, $renderer);
    }

    /** Get HTML renderer instance */
    public static function html(): Html
    {
        return new Html();
    }
    /** Get CLI renderer instance */
    public static function cli(): Cli
    {
        return new Cli();
    }

    /**
     * Throw a report
     *
     * @param string $type The type of report (e.g., 'error', 'warning', 'info')
     * @param string $message The report message
     * @param string $file The file where the report is generated
     * @param int $line The line number where the report is generated
     */
    public static function throw(string $type, string $message, string $file = __FILE__, int $line = __LINE__): void
    {
        $renderer = php_sapi_name() === 'cli' ? self::cli() : self::html();
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $renderer->render($type, $message, $file, $line, ['trace' => $trace]);
    }
}
