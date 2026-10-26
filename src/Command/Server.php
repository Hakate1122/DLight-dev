<?php

namespace DLight\Command;

use DLight\Application\App;

use function count;
use function defined;
use function sprintf;

/**
 * A command to start a local development server.
 *
 * Usage:
 *   php dli server[-s] --mode=lan --port=8000 --bind=0.0.0.0
 * 
 * Options:
 ** --mode (-m): 'local' or 'lan' (default: 'lan')
 ** --port (-p): Port number to listen on (default: 8000)
 ** --bind: IP address to bind to (default: '0.0.0.0')
 */
class Server
{
    public static function server()
    {
        return function ($argv = []) {
            if (App::isRunningFromPhar()) {
                echo cli_red("Can't start the development server when running from a PHAR archive.\n\n");
                exit(1);
            }
            $opts = [];
            $counter = count($argv);
            for ($i = 2; $i < $counter; $i++) {
                $arg = $argv[$i];
                if (str_starts_with($arg, '--')) {
                    $parts = explode('=', substr($arg, 2), 2);
                    $opts[$parts[0]] = $parts[1] ?? true;
                } elseif (str_starts_with($arg, '-')) {
                    $key = ltrim($arg, '-');
                    $val = $argv[$i + 1] ?? true;
                    if (!str_starts_with($val, '-')) {
                        $opts[$key] = $val;
                        $i++;
                    } else {
                        $opts[$key] = true;
                    }
                }
            }


            $appUrl = null;
            if (function_exists('env')) {
                $appUrl = env('APP_URL');
            } elseif (getenv('APP_URL')) {
                $appUrl = getenv('APP_URL');
            }

            $mode = strtolower($opts['mode'] ?? $opts['m'] ?? 'lan');
            $port = (int)($opts['port'] ?? $opts['p'] ?? 8000);
            $bind = $opts['bind'] ?? null;

            if ($appUrl) {
                $parsed = parse_url($appUrl);
                if (isset($parsed['host'])) {
                    $bind ??= $parsed['host'];
                }
                if (isset($parsed['port'])) {
                    $port = $port ?: $parsed['port'];
                } elseif (isset($parsed['scheme']) && $parsed['scheme'] === 'https') {
                    $port = $port ?: 443;
                }
            }

            $detectLanIp = function (): string {
                $ip = gethostbyname(gethostname());
                if (filter_var($ip, FILTER_VALIDATE_IP) && !str_starts_with($ip, '127.')) {
                    return $ip;
                }

                if (stripos(PHP_OS, 'WIN') === 0) {
                    @exec('ipconfig', $out);
                    foreach ($out as $line) {
                        if (preg_match('/IPv4[^\:]*:\s*([\d\.]+)/i', $line, $m)) {
                            return $m[1];
                        }
                    }
                } else {
                    @exec('hostname -I', $out);
                    if (isset($out[0]) && ($out[0] !== '' && $out[0] !== '0')) {
                        $ips = preg_split('/\s+/', trim($out[0]));
                        foreach ($ips as $i) if (!str_starts_with($i, '127.')) {
                            return $i;
                        }
                    }
                    @exec("ip -4 addr show scope global", $out);
                    foreach ($out as $line) {
                        if (preg_match('/inet\s+([\d\.]+)/', $line, $m)) {
                            return $m[1];
                        }
                    }
                }
                return '0.0.0.0';
            };

            $public = defined('INDEX_DIR') ? INDEX_DIR : __DIR__ . '/../../public';

            if ($mode === 'local' || $mode === 'localhost') {
                $bindHost = $bind ?? '127.0.0.1';
                $displayHost = $bindHost === '0.0.0.0' ? '127.0.0.1' : $bindHost;
            } else {
                $bindHost = $bind ?? '0.0.0.0';
                if ($appUrl && isset($parsed['host'])) {
                    $displayHost = $parsed['host'];
                } else {
                    $detected = $detectLanIp();
                    $displayHost = ($detected === '0.0.0.0') ? 'localhost' : $detected;
                }
            }

            echo "Starting development server (mode: $mode) | PHP: " . PHP_VERSION . "\n";
            echo "Document root: $public\n";
            echo "Listening on: $bindHost:$port\n";
            echo "Accessible at: http://" . $displayHost . ":" . $port . "\n";
            echo "Press Ctrl+C to stop\n\n";

            $phpBin = PHP_OS_FAMILY === 'Windows' ? '"' . PHP_BINARY . '"' : escapeshellarg(PHP_BINARY);
            $cmd = sprintf('%s -S %s:%d -t %s', $phpBin, $bindHost, $port, escapeshellarg($public));
            passthru($cmd);
        };
    }
}
