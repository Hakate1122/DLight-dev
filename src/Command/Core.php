<?php

namespace DLight\Command;

use DLight\Application\App;
use DLight\Command\Helper\ConsoleInput;

/**
 * Core command implementations for the CLI application.
 * 
 * Provides help, version, and command listing functionalities.
 * 
 * Usage:
 **  php dli help[-h]       Show help information
 **  php dli version[-v]    Show application version
 */
class Core
{
    public App $app;

    private static function shellReadFirstLine(string $command): ?string
    {
        $out = @shell_exec($command);
        if (!is_string($out)) {
            return null;
        }
        $out = trim($out);
        if ($out === '') {
            return null;
        }
        $lines = preg_split('/\R/', $out);
        if (!is_array($lines) || count($lines) === 0) {
            return null;
        }
        $line = trim($lines[0]);
        return $line !== '' ? $line : null;
    }

    private static function isGitWorkTree(string $dir): bool
    {
        $gitDir = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . '.git';
        return is_dir($gitDir);
    }

    private static function normalizeGitRemote(?string $remote): ?string
    {
        if (!is_string($remote)) {
            return null;
        }
        $remote = trim($remote);
        if ($remote === '') {
            return null;
        }

        if (preg_match('/^git@github\.com:([^\\s]+)$/i', $remote, $m)) {
            $path = $m[1];
            $path = preg_replace('/\\.git$/i', '', $path);
            return 'https://github.com/' . $path;
        }

        if (preg_match('/^https?:\\/\\/(.+)$/i', $remote)) {
            return preg_replace('/\\.git$/i', '', $remote);
        }

        return preg_replace('/\\.git$/i', '', $remote);
    }

    private static function readGitOriginUrl(string $cwd): ?string
    {
        $cmd = 'git -C ' . escapeshellarg($cwd) . ' config --get remote.origin.url 2>NUL';
        return self::normalizeGitRemote(self::shellReadFirstLine($cmd));
    }

    private static function readGitBranch(string $cwd): ?string
    {
        $cmd = 'git -C ' . escapeshellarg($cwd) . ' rev-parse --abbrev-ref HEAD 2>NUL';
        return self::shellReadFirstLine($cmd);
    }

    private static function readGitCommitShort(string $cwd): ?string
    {
        $cmd = 'git -C ' . escapeshellarg($cwd) . ' rev-parse --short HEAD 2>NUL';
        return self::shellReadFirstLine($cmd);
    }

    private static function parseOsReleasePrettyName(string $path = '/etc/os-release'): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return null;
        }
        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'PRETTY_NAME=')) {
                $value = substr($line, strlen('PRETTY_NAME='));
                $value = trim($value);
                if ($value === '') {
                    return null;
                }
                if (($value[0] ?? '') === '"' && str_ends_with($value, '"')) {
                    $value = substr($value, 1, -1);
                }
                return $value !== '' ? $value : null;
            }
        }
        return null;
    }

    private static function readWindowsRegistryValue(string $valueName): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $key = 'HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion';
        $cmd = 'reg query "' . $key . '" /v ' . escapeshellarg($valueName) . ' 2>NUL';
        $out = @shell_exec($cmd);
        if (!is_string($out) || trim($out) === '') {
            return null;
        }

        foreach (preg_split('/\R/', $out) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (stripos($line, $valueName) === false) {
                continue;
            }
            
            $parts = preg_split('/\s{2,}/', $line);
            if (is_array($parts) && count($parts) >= 3) {
                $value = trim(end($parts));
                if (preg_match('/^0x[0-9a-f]+$/i', $value)) {
                    $value = (string) hexdec(substr($value, 2));
                }
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    private static function getOsDetails(): array
    {
        $details = [
            'family' => PHP_OS_FAMILY,
            'name' => null,
            'version' => null,
            'build' => null,
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $productName = self::readWindowsRegistryValue('ProductName');
            $displayVersion = self::readWindowsRegistryValue('DisplayVersion');
            $currentBuild = self::readWindowsRegistryValue('CurrentBuild');
            $currentBuildNumber = self::readWindowsRegistryValue('CurrentBuildNumber');
            $ubr = self::readWindowsRegistryValue('UBR');

            $details['name'] = $productName ?: 'Windows';
            $details['version'] = $displayVersion ?: php_uname('r');

            $buildBase = $currentBuild ?: $currentBuildNumber ?: null;
            $details['build'] = $buildBase
                ? ($ubr ? ($buildBase . '.' . $ubr) : $buildBase)
                : php_uname('r');

            return $details;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $productName = self::shellReadFirstLine('sw_vers -productName 2>/dev/null');
            $productVersion = self::shellReadFirstLine('sw_vers -productVersion 2>/dev/null');
            $buildVersion = self::shellReadFirstLine('sw_vers -buildVersion 2>/dev/null');

            $details['name'] = $productName ?: 'macOS';
            $details['version'] = $productVersion ?: php_uname('r');
            $details['build'] = $buildVersion ?: php_uname('v');
            return $details;
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $pretty = self::parseOsReleasePrettyName('/etc/os-release');
            if ($pretty === null && is_file('/system/build.prop')) {
                $pretty = 'Android';
            }

            $details['name'] = $pretty ?: 'Linux';
            $details['version'] = php_uname('r');
            $details['build'] = php_uname('v');

            if (is_file('/system/build.prop')) {
                $androidRelease = self::shellReadFirstLine('getprop ro.build.version.release 2>/dev/null');
                $androidSdk = self::shellReadFirstLine('getprop ro.build.version.sdk 2>/dev/null');
                if ($androidRelease || $androidSdk) {
                    $v = trim('Android ' . ($androidRelease ?: '') . ($androidSdk ? ' (SDK ' . $androidSdk . ')' : ''));
                    $details['version'] = $v !== '' ? $v : $details['version'];
                }
            }

            return $details;
        }

        $details['name'] = php_uname('s');
        $details['version'] = php_uname('r');
        $details['build'] = php_uname('v');
        return $details;
    }

    public static function help()
    {
        $dfver = App::VERSION ?? "unknown";

        $detectDeviceRuntime = static function (): string {
            if (PHP_OS_FAMILY === 'Linux') {
                if (is_file('/system/build.prop')) {
                    return 'android';
                }
                return 'linux';
            }

            return match (PHP_OS_FAMILY) {
                'Windows' => 'windows',
                'Darwin'  => 'macos',
                default   => 'unknown',
            };
        };

        return function ($argv = null) use ($detectDeviceRuntime, $dfver) {
            $scriptName = isset($argv[0]) ? basename($argv[0]) : '';
            $os = self::getOsDetails();
            echo "DLI - DLight CLI Core Helper\n";
            echo "Version: " . cli_cyan($dfver) . " | PHP: " . cli_blue(phpversion()) . " on " . cli_yellow($detectDeviceRuntime()) . "\n";
            if (!empty($os['name']) || !empty($os['version']) || !empty($os['build'])) {
                $osName = (string) ($os['name'] ?? $os['family'] ?? 'unknown');
                $osVersion = (string) ($os['version'] ?? '');
                $osBuild = (string) ($os['build'] ?? '');

                $parts = array_values(array_filter([$osName, $osVersion !== '' ? "($osVersion)" : null, $osBuild !== '' ? "build $osBuild" : null]));
                echo "OS: " . cli_yellow(implode(' ', $parts)) . "\n";
            }
            echo "Usage: php dli <command> [options]\n\n";

            if (!App::isRunningFromPhar() && ($scriptName !== 'dli' && $scriptName !== 'dli.php')) {
                echo cli_gray("Don't change name, dli is fast too!\n\n");
            }

            if (App::isRunningFromPhar()) {
                echo cli_yellow("Detected: dli is running from a PHAR archive. Some features may not work (e.g., starting the server, or file writing)\n\n");
            }

            if (App::isRunningFromDocker()) {
                echo cli_gray("dli is running inside a Docker container.\n\n");
            }
            
            echo "Available commands:\n";
            echo "  help, -h             Show this help message\n";
            echo "  help:add             Detailed help for add / add:<type>\n";
            echo "  version, -v          Show application version\n";
            if (!App::isRunningFromPhar()) {echo "  server, -s           Start the development server\n";}
            echo "  list                 List all available commands\n";
            echo "  test                 Run unit tests\n";
            echo "\n";
            echo "Add commands - create a new components:\n";
            echo "  add <type>           Create a new component[controller, model, view, middleware, command, mail]\n";
            echo "  add:controller       Create a new controller\n";
            echo "  add:model            Create a new model\n";
            echo "  add:view             Create a new view\n";
            echo "  add:middleware       Create a new middleware\n";
            echo "  add:command          Create a new command\n";
            echo "  add:mail             Create a new mail class\n";
            echo "  For details on each command, use ". cli_yellow("php dli help:add") . "\n";
            echo "\n";
        };
    }

    /**
     * Detailed help for `add` and `add:<type>` scaffolds.
     */
    public static function helpAdd()
    {
        return function () {
            echo cli_cyan("DLI — add commands\n\n");

            echo "You can scaffold components in two equivalent ways:\n\n";
            echo "  " . cli_yellow("php dli add <type> [options]") . "\n";
            echo "  " . cli_yellow("php dli add:<type> [options]") . "\n\n";

            echo "Replace <type> with one of:\n";
            echo "  " . cli_green("controller") . " (aliases: ctrl)\n";
            echo "  " . cli_green("model") . "       (aliases: mdl)\n";
            echo "  " . cli_green("view") . "        (aliases: vw)\n";
            echo "  " . cli_green("command") . "     (aliases: cmd)\n";
            echo "  " . cli_green("middleware") . "  (aliases: mdw)\n";
            echo "  " . cli_green("mail") . "\n\n";

            echo "Options (all generators):\n";
            echo "  " . cli_yellow("--name=Name") . "   Class / file base name (required)\n";
            echo "  " . cli_yellow("-n Name") . "       Short form for name\n";
            echo "  " . cli_yellow("--force") . "     Overwrite the target file if it already exists\n\n";

            echo cli_cyan("add:controller") . " / " . cli_cyan("add controller") . "\n";
            echo "  Output: app/Controller/…/{Name}Controller.php\n";
            echo "  Suffix " . cli_gray("Controller") . " is added if missing.\n";
            echo "  Use a path in the name for subfolders, e.g. " . cli_yellow("--name=Admin/Dashboard") . "\n";
            echo "  " . cli_yellow("--crud") . " — scaffold index/create/store/show/edit/update/destroy stubs.\n";
            echo "  " . cli_yellow("--api-crud") . " — REST-style JSON stubs: index/store/show/update/destroy (" . cli_gray("overrides --crud") . ").\n\n";

            echo cli_cyan("add:model") . " / " . cli_cyan("add model") . "\n";
            echo "  Output: app/Model/{Name}.php — class name is StudlyCase from " . cli_yellow("--name") . " (no forced Model suffix).\n";
            echo "  " . cli_yellow("--table=posts") . " — DB table; if omitted, derived as snake_case from the class (Posts → posts).\n";
            echo "  " . cli_yellow("--selectable=[id,title]") . " or " . cli_yellow("--selectable=id,title") . " — default columns for the mapper.\n\n";

            echo cli_cyan("add:view") . " / " . cli_cyan("add view") . "\n";
            echo "  Output: resource/view/{name}.php (PHP template stub)\n\n";

            echo cli_cyan("add:command") . " / " . cli_cyan("add command") . "\n";
            echo "  Output: src/Command/{Name}Command.php (namespace DLight\\Command)\n";
            echo "  Suffix " . cli_gray("Command") . " is added if missing.\n\n";

            echo cli_cyan("add:middleware") . " / " . cli_cyan("add middleware") . "\n";
            echo "  Output: app/Middleware/{Name}Middleware.php\n";
            echo "  Suffix " . cli_gray("Middleware") . " is added if missing.\n\n";

            echo cli_cyan("add:mail") . " / " . cli_cyan("add mail") . "\n";
            echo "  Output: app/Mail/{Name}.php — suffix " . cli_gray("Mail") . " if name does not end with Mail/Mailer.\n\n";

            echo "Examples:\n";
            echo "  " . cli_gray("php dli add controller --name=Posts --api-crud") . "\n";
            echo "  " . cli_gray("php dli add:model --name=Posts --force") . "\n";
            echo "  " . cli_gray('php dli add:model --name=Posts --table=posts --selectable=[id,title,content,created_at]') . "\n";
            echo "  " . cli_gray("php dli add view -n home") . "\n";
        };
    }

    public static function version()
    {
        return function () {
            $version = App::VERSION ?? "unknown";
            echo "Version: " . cli_green($version) . "\n";

            $cwd = getcwd();
            if (!is_string($cwd) || $cwd === '') {
                return;
            }

            if (!self::isGitWorkTree($cwd)) {
                return;
            }

            if (!ConsoleInput::askYesNo("Git repository detected. Do you want to show Git information?", false)) {
                return;
            }

            $origin = self::readGitOriginUrl($cwd);
            $branch = self::readGitBranch($cwd);
            $commit = self::readGitCommitShort($cwd);

            if ($origin) {
                $label = (stripos($origin, 'github.com/') !== false) ? 'GitHub' : 'Git remote';
                echo $label . ": " . cli_cyan($origin) . "\n";
            }
            if ($branch || $commit) {
                $ref = trim(($branch ?: '') . ($commit ? (' @ ' . $commit) : ''));
                if ($ref !== '') {
                    echo "Git: " . cli_yellow($ref) . "\n";
                }
            }
        };
    }

    public static function list(array $commands)
    {
        return function () use ($commands) {
            echo "Available commands:\n";
            foreach ($commands as $cmd) {
                echo "  - $cmd\n";
            }
        };
    }
}
