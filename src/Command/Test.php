<?php

namespace DLight\Command;

use DLight\Application\App;

use function count;
use function defined;
use function is_string;
use function is_resource;
use function sprintf;
use function strlen;

/**
 * Run the project's test suite (PHPUnit) from the CLI.
 *
 * Usage:
 *   php dli test [<phpunit options>]
 */
class Test
{
    public static function handle()
    {
        return function ($argv = []) {
            if (App::isRunningFromPhar()) {
                echo cli_red("Can't run tests when running from a PHAR archive.\n\n");
                exit(1);
            }

            $args = [];
            $count = count($argv);
            for ($i = 2; $i < $count; $i++) {
                $args[] = $argv[$i];
            }

            $root = defined('ROOT_DIR') ? ROOT_DIR : __DIR__ . '/../../';

            $candidates = [
                $root . 'vendor/bin/phpunit',
                $root . 'vendor/bin/phpunit.bat',
                $root . 'vendor/phpunit/phpunit/phpunit',
            ];

            $phpunit = null;
            foreach ($candidates as $c) {
                if (!file_exists($c)) {
                    continue;
                }
                // On Windows, .bat may not have executable flag but is usable
                if (str_ends_with($c, '.bat')) {
                    $phpunit = $c;
                    break;
                }
                // Prefer executable stubs when available
                if (is_executable($c)) {
                    $phpunit = $c;
                    break;
                }
                // If the file exists but isn't marked executable (common when checked out on
                // Windows or transferred without exec bit), still accept it and run via PHP.
                $phpunit = $c;
                break;
            }

            if ($phpunit === null) {
                echo cli_yellow("PHPUnit not found. Install dev dependencies: composer install --dev\n");
                exit(1);
            }

            $php = PHP_BINARY;

            // Prepare JUnit report path (unless user already supplied one)
            $hasLogJUnit = false;
            foreach ($args as $a) {
                if (str_starts_with($a, '--log-junit') || str_starts_with($a, '--log-junit=')) {
                    $hasLogJUnit = true;
                    break;
                }
            }

            $reportFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dlight_phpunit_' . uniqid() . '.xml';
            if (!$hasLogJUnit) {
                $args[] = '--log-junit=' . $reportFile;
            } else {
                // if user provided --log-junit, attempt to extract path
                foreach ($args as $a) {
                    if (str_starts_with($a, '--log-junit=')) {
                        $reportFile = substr($a, strlen('--log-junit='));
                        break;
                    }
                }
            }

            // Build command
            // If user didn't provide any non-option arguments, run the default `tests` directory
            $hasNonOptionArg = false;
            foreach ($args as $a) {
                if ((string) $a !== '' && $a[0] !== '-') {
                    $hasNonOptionArg = true;
                    break;
                }
            }
            if (!$hasNonOptionArg) {
                $args[] = 'tests';
            }

            if (str_ends_with($phpunit, '.bat')) {
                $cmd = sprintf('"%s" %s', $phpunit, implode(' ', array_map('escapeshellarg', $args)));
            } else {
                $cmd = sprintf('%s %s %s', escapeshellarg($php), escapeshellarg($phpunit), implode(' ', array_map('escapeshellarg', $args)));
            }

            // Run PHPUnit and capture exit code and output (suppress direct stdout)
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open($cmd, $descriptors, $pipes);
            $phpunitOutput = '';
            $phpunitError = '';
            $exitCode = 1;
            if (is_resource($process)) {
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $phpunitOutput = stream_get_contents($pipes[1]);
                $phpunitError = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
            }

            // Try to get phpunit version for header
            $puVersion = null;
            if (str_ends_with($phpunit, '.bat')) {
                $verOutput = shell_exec('"' . $phpunit . '" --version 2>&1');
            } else {
                $verOutput = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($phpunit) . ' --version 2>&1');
            }
            if (is_string($verOutput)) {
                $lines = preg_split('/\r?\n/', trim($verOutput));
                $puVersion = $lines[0] ?? trim($verOutput);
            }
            // Fallback: try to extract from PHPUnit stdout captured earlier
            if (empty($puVersion) && is_string($phpunitOutput) && preg_match('/PHPUnit\s+([0-9\.]+)/i', $phpunitOutput, $pp)) {
                $puVersion = 'PHPUnit ' . $pp[1];
            }
                    

            // If report exists, parse and print formatted summary
            if (file_exists($reportFile)) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_file($reportFile);
                $totalTests = 0;
                $totalAssertions = 0;
                $failures = [];
                $cases = [];

                if ($xml !== false) {
                    // Find all testcase nodes anywhere in document
                    $testcases = $xml->xpath('//testcase');
                    if ($testcases === false) {
                        $testcases = [];
                    }

                    foreach ($testcases as $tc) {
                        $tcAttrs = $tc->attributes();
                        $name = (string)($tcAttrs['name'] ?? '');
                        $class = (string)($tcAttrs['classname'] ?? '');
                        $display = $name !== '' ? $name : ($class ?: 'test');
                        if ($class !== '' && $name !== '') {
                            $display = $class . '::' . $name;
                        }
                        $status = 'Pass';
                        $message = '';
                        if (property_exists($tc, 'failure') && $tc->failure !== null || property_exists($tc, 'error') && $tc->error !== null) {
                            $status = 'Fail';
                            $node = $tc->failure ?? $tc->error;
                            $message = trim((string)$node->message ?: (string)$node);
                            $failures[] = [
                                'test' => $display,
                                'message' => $message,
                            ];
                        }
                        $cases[] = ['name' => $display, 'status' => $status];
                    }

                    // Compute totals based on testcase nodes to avoid double-counting nested suites
                    $totalTests = count($testcases);
                    // Sum assertions from each testcase if available
                    $assertionsAttr = 0;
                    foreach ($testcases as $tc) {
                        $a = $tc->attributes();
                        $assertionsAttr += (int)($a['assertions'] ?? 0);
                    }
                    $totalAssertions = $assertionsAttr > 0 ? $assertionsAttr : $totalTests;
                }

                    // Print concise summary format requested by user
                    // Header
                    $puShort = $puVersion ?? '';
                    $puShort = preg_match('/PHPUnit\s+([0-9\.]+)/i', $puShort, $m) ? $m[1] : trim($puShort);

                    echo "DLight Framework - Test Suite" . PHP_EOL;
                    echo "PHPUnit {$puShort} | DLight " . App::version . " | PHP " . PHP_VERSION . PHP_EOL . PHP_EOL;

                    // Test lines (with colored status and failure messages)
                    $failMap = [];
                    foreach ($failures as $f) {
                        $failMap[$f['test']] = $f['message'];
                    }
                    foreach ($cases as $c) {
                        $mark = ($c['status'] === 'Pass') ? cli_green('✓') : cli_red('✗');
                        if ($c['status'] === 'Pass') {
                            echo "  {$mark} {$c['name']}" . PHP_EOL;
                            continue;
                        }

                        // failing case: parse failure message to extract reason and file:line
                        $raw = $failMap[$c['name']] ?? '';
                        $fileLine = null;
                        $reason = trim($raw);
                        if ($raw !== '') {
                            $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw)), fn($v) => $v !== ''));
                            // last line often contains file:line
                            $last = end($lines);
                            if ($last !== false && preg_match('/\.[pP][hH][pP]:\d+$/', $last)) {
                                $fileLine = $last;
                                // reason is previous non-test-name line
                                if (count($lines) >= 2) {
                                    $reason = $lines[count($lines) - 2];
                                }
                            } else {
                                // try to find line that looks like file:line anywhere
                                foreach (array_reverse($lines) as $ln) {
                                    if (preg_match('/\\.php:\d+$/', $ln) || preg_match('/:\d+$/', $ln)) {
                                        $fileLine = $ln;
                                        break;
                                    }
                                }
                                // pick a reasonable reason: first line that contains 'Failed' or the second line
                                $reason = '';
                                foreach ($lines as $ln) {
                                    if (stripos($ln, 'failed') !== false || stripos($ln, 'assert') !== false) {
                                        $reason = $ln;
                                        break;
                                    }
                                }
                                if ($reason === '' && isset($lines[1])) {
                                    $reason = $lines[1];
                                }
                                if ($reason === '' && isset($lines[0])) {
                                    $reason = $lines[0];
                                }
                            }
                        }

                        $filePart = $fileLine ? " On {$fileLine}" : '';
                        $reasonPart = $reason ? " - {$reason}" : '';
                        echo "  {$mark} {$c['name']} =>" . (cli_yellow($filePart . $reasonPart) !== '' && cli_yellow($filePart . $reasonPart) !== '0' ? cli_yellow("{$filePart}{$reasonPart}") : '') . PHP_EOL;
                    }

                    echo PHP_EOL;

                    // Time and Memory: try to parse PHPUnit stdout first, else sum testsuite times
                    $timeSeconds = 0.0;
                    $memoryStr = '0 MB';
                    if (is_string($phpunitOutput) && preg_match('/Time:\s*([0-9:\.]+),\s*Memory:\s*([0-9\.]+\s*MB)/i', $phpunitOutput, $m)) {
                        $timeRaw = $m[1];
                        $memoryStr = $m[2];
                        $parts = explode(':', $timeRaw);
                        $sec = array_pop($parts);
                        $mins = (int)(array_pop($parts) ?? 0);
                        $hours = (int)(array_pop($parts) ?? 0);
                        $timeSeconds = (float)$sec + $mins * 60 + $hours * 3600;
                    } elseif ($xml !== false) {
                        $suites = $xml->xpath('//testsuite');
                        if ($suites !== false) {
                            foreach ($suites as $s) {
                                $attrs = $s->attributes();
                                $timeSeconds += (float)($attrs['time'] ?? 0);
                            }
                        }
                    }

                    echo PHP_EOL;
                    echo '  Time: ' . cli_green(number_format($timeSeconds, 2)) . " s | Memory: " . cli_green($memoryStr) . PHP_EOL;

                    $overallText = count($failures) > 0 ? 'FAIL' : 'OK';
                    echo '  Results: ' . (count($failures) > 0 ? cli_red($overallText) : cli_green($overallText)) . " ({$totalTests} tests, {$totalAssertions} assertions)" . PHP_EOL;

                    // clean up report file if we created it
                    if (!$hasLogJUnit && file_exists($reportFile)) {
                        @unlink($reportFile);
                    }

                    exit($exitCode);
            }

            // No report, exit with PHPUnit's exit code
            exit($exitCode);
        };
    }
}
