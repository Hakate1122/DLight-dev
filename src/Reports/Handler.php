<?php

namespace DLight\Reports;

use DLight\Application\App;
use DLight\Reports\Interface\HandlerInterface;
use DLight\Reports\Interface\RenderInterface;

/**
 * Handler - Error and exception handling class
 */
class Handler implements HandlerInterface
{
    private RenderInterface $renderer;

    /**
     * Register a new error and exception handler
     * 
     * @param bool $saveLog Whether to save logs to a file
     * @param string $logFile The log file path
     * @param mixed $renderer The renderer instance to use
     */
    public function __construct(private bool $saveLog = false, private string $logFile = 'errors.log', ?RenderInterface $renderer = null)
    {
        $this->renderer = $renderer ?? $this->detectRenderer();

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleRuntime']);
    }

    /**
     * Detect the appropriate renderer based on the environment (CLI or Web)
     *
     * @return RenderInterface The renderer instance
     */
    private function detectRenderer(): RenderInterface
    {
        return php_sapi_name() === 'cli'
            ? new \DLight\Reports\Render\Cli()
            : new \DLight\Reports\Render\Html();
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if ((error_reporting() & $errno) === 0) {
            return false;
        }

        $type = match ($errno) {
            E_PARSE => 'parse',
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'runtime',
            default => 'error',
        };

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $context = [
            'code'  => $errno,
            'trace' => $trace,
        ];

        $this->log($type, $errstr, $errfile, $errline, $context);
        $this->renderer->render($type, $errstr, $errfile, $errline, $context);
        return true;
    }

    public function handleException(\Throwable $exception): void
    {
        $trace = $exception->getTrace();
        $context = [
            'trace' => $trace,
        ];

        $this->log('exception', $exception->getMessage(), $exception->getFile(), $exception->getLine(), $context);
        $this->renderer->render('exception', $exception->getMessage(), $exception->getFile(), $exception->getLine(), $context);
    }

    /**
     * Handle parse errors on shutdown
     */
    public function handleParse(): void
    {
        // This method is intentionally left blank as parse errors are handled in handleRuntime
    }

    public function handleRuntime(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [
            E_PARSE             => 'parse',
            E_ERROR             => 'runtime',
            E_CORE_ERROR        => 'runtime',
            E_COMPILE_ERROR     => 'runtime',
            E_USER_ERROR        => 'runtime',
            E_RECOVERABLE_ERROR => 'runtime',
        ];

        if (isset($fatalTypes[$error['type']])) {
            $type = $fatalTypes[$error['type']];
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $context = [
                'code'  => $error['type'],
                'trace' => $trace,
            ];

            $this->log($type, $error['message'], $error['file'], $error['line'], $context);
            $this->renderer->render($type, $error['message'], $error['file'], $error['line'], $context);
        }
    }

    public function log(string $type, string $message, string $file, int $line, array $context = []): void
    {
        if (!$this->saveLog) {
            return;
        }

        if (str_starts_with($this->logFile, 'phar://')) {
            $rel = preg_replace('#^phar://[^/]+/#', '', $this->logFile);
            $this->logFile = getcwd() . DIRECTORY_SEPARATOR . $rel;
        }

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $log = sprintf(
            "[%s] %s | %s:%d | %s | %s | %s | %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($type),
            $file,
            $line,
            $message,
            json_encode($context),
            App::version ?? 'unknown',
            phpversion()
        );
        $isSupportLockEx = defined('FILE_APPEND') && defined('LOCK_EX');
        if ($isSupportLockEx) {
            file_put_contents($this->logFile, $log, FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($this->logFile, $log, FILE_APPEND);
        }
    }
}
