<?php

namespace DLight\Reports\Render;

use DLight\Reports\Interface\RenderInterface;

use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * CLI Renderer for DLight Reports
 */
class Cli implements RenderInterface
{
    private static $colors = [
        'error' => "\033[35m",
        'exception' => "\033[31m",
        'parse' => "\033[34m",
        'runtime' => "\033[33m",
        'reset' => "\033[0m",
        'bold' => "\033[1m",
    ];

    private static $highlights = [
        'error' => "\033[45m\033[30m",
        'exception' => "\033[41m\033[30m",
        'parse' => "\033[44m\033[30m",
        'runtime' => "\033[43m\033[30m",
    ];

    public function render(string $type, string $message, string $file, int $line, array $context = []): void
    {
        $dfversion = class_exists(\DLight\Application\App::class)
        ? \DLight\Application\App::VERSION
        : 'Non-DLight Env';
        
        $phpversion = PHP_VERSION;
        
        $color = self::$colors[$type] ?? self::$colors['error'];
        $reset = self::$colors['reset'];
        $bold = self::$colors['bold'];

        echo "$color{$bold}DLight Report$reset - Oops, DLight Report detected a bug!" . PHP_EOL;
        echo "$color DLight Version: $reset$dfversion |$color PHP Version: $reset$phpversion" . PHP_EOL;
        echo PHP_EOL;
        echo "Type: $color{$bold}$type$reset" . PHP_EOL;
        echo "$color{$bold}==============================$reset" . PHP_EOL;
        echo "$color Message: $reset$message" . PHP_EOL;
        echo "$color File:    $reset$file" . PHP_EOL;
        echo "$color Line:    $reset$line" . PHP_EOL;
        $dt = new \DateTime('now', new \DateTimeZone(env('APP_TIMEZONE', 'UTC')));
        echo "$color Time:    $reset" . $dt->format('Y-m-d H:i:s') . " - " . env('APP_TIMEZONE', 'UTC') . PHP_EOL;

        if (!empty($context['code'])) {
            echo "$color Code:    $reset" . $context['code'] . PHP_EOL;
        }

        if ($file && file_exists($file)) {
            $lines = file($file);
            $start = max($line - 3, 0);
            $end = min($line + 2, count($lines));
            echo "$color Nearby: $reset" . PHP_EOL;
            for ($i = $start; $i < $end; $i++) {
                $num = $i + 1;
                $lineContent = rtrim($lines[$i]);
                echo match ($num) {
                    $line => (function() use ($num, $lineContent, $type, $color, $bold, $reset) {
                        $hl = self::$highlights[$type] ?? (self::$colors['highlight'] ?? $color);
                        return "  " . ($bold . "> " . $reset) . "$num: " . $hl . $bold . $lineContent . $reset . PHP_EOL;
                    })(),
                    default => "    $num: " . $lineContent . PHP_EOL,
                };
            }
        }

        if (!empty($context['trace'])) {
            echo PHP_EOL;
            echo "$color{$bold}Stack trace (earliest call first):$reset" . PHP_EOL;

            $trace = $context['trace'];

            if (is_string($trace)) {
                echo $trace . PHP_EOL;
            } elseif (is_array($trace)) {
                $frames = array_values(array_reverse($trace));
                foreach ($frames as $index => $frame) {
                    $tFile = $frame['file'] ?? '[internal function]';
                    $tLine = $frame['line'] ?? '-';
                    $function = $frame['function'] ?? '';
                    $class = $frame['class'] ?? '';
                    $typeSep = $frame['type'] ?? '';

                    $call = $function;
                    if ($class !== '') {
                        $call = $class . $typeSep . $call;
                    }

                    echo sprintf(
                        "  #%d %s(%s): %s%s%s",
                        $index,
                        $tFile,
                        $tLine,
                        $bold,
                        $call,
                        $reset
                    ) . PHP_EOL;
                }
            }
        }

        echo "$color{$bold}==============================$reset" . PHP_EOL . PHP_EOL;
        exit(1);
    }
}
