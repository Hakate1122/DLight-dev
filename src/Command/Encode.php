<?php

namespace DLight\Command;

use function is_resource;

/**
 * DLight ionCube Encoder command.
 *
 * Cross-platform:
 * - Windows
 * - Linux
 * - macOS
 * - Termux / Android
 *
 * Usage:
 *   php dli encode
 *   php dli encode start
 *   php dli encode start <source> <output>
 *
 * Encoder can be configured with:
 *
 *   IONCUBE_ENCODER=/path/to/encoder
 */
class Encode
{
    private const DEFAULT_SOURCE = '.';
    private const DEFAULT_OUTPUT = 'build/encoded';

    public static function handle()
    {
        return function ($argv = []) {
            $action = $argv[2] ?? null;

            if ($action === null || $action === '') {
                self::showHelp();
                return;
            }

            if ($action !== 'start') {
                echo cli_red("Unknown action: {$action}\n\n");
                self::showHelp();
                return;
            }

            $source = $argv[3] ?? self::DEFAULT_SOURCE;
            $output = $argv[4] ?? self::DEFAULT_OUTPUT;

            self::start($source, $output);
        };
    }

    /**
     * Display command information.
     */
    private static function showHelp()
    {
        $encoder = self::findEncoder();

        echo "DLight Encode using ionCube Encoder\n";
        echo "PHP: " . PHP_VERSION . "\n";

        echo "OS: " . PHP_OS_FAMILY . "\n";

        echo "ionCube Loader: "
            . (extension_loaded('ioncube Loader')
                ? cli_green('Installed')
                : cli_red('Not installed'))
            . "\n";

        echo "ionCube Encoder: "
            . ($encoder !== null
                ? cli_green('Found')
                : cli_red('Not found'))
            . "\n";

        if ($encoder !== null) {
            echo "Encoder path: {$encoder}\n";
        }

        echo "\n";

        echo "Usage:\n";
        echo "  php dli encode start\n";
        echo "  php dli encode start <source> <output>\n\n";

        echo "Environment variable:\n";
        echo "  IONCUBE_ENCODER=/path/to/encoder\n";
    }

    /**
     * Start encoding.
     */
    private static function start($source, $output)
    {
        $encoder = self::findEncoder();

        if ($encoder === null) {
            echo cli_red(
                "Error: ionCube Encoder was not found.\n"
            );

            echo "\nSet the IONCUBE_ENCODER environment variable.\n";

            if (PHP_OS_FAMILY === 'Windows') {
                echo "Example:\n";
                echo '  $env:IONCUBE_ENCODER="C:\\ioncube\\encoder.exe"' . "\n";
            } else {
                echo "Example:\n";
                echo "  export IONCUBE_ENCODER=/opt/ioncube/encoder\n";
            }

            return;
        }

        $source = self::absolutePath($source);
        $output = self::absolutePath($output);

        if (!is_dir($source)) {
            echo cli_red(
                "Error: Source directory does not exist:\n"
                . "  {$source}\n"
            );

            return;
        }

        if (!is_dir($output)) {
            if (!mkdir($output, 0777, true)) {
                echo cli_red(
                    "Error: Cannot create output directory:\n"
                    . "  {$output}\n"
                );

                return;
            }
        }

        echo "DLight Encode\n";
        echo "=============\n";
        echo "Platform: " . PHP_OS_FAMILY . "\n";
        echo "Encoder:  {$encoder}\n";
        echo "Source:   {$source}\n";
        echo "Output:   {$output}\n\n";

        $command = self::buildCommand(
            $encoder,
            $source,
            $output
        );

        echo "[1/2] Starting ionCube Encoder...\n";

        $exitCode = self::runProcess($command);

        if ($exitCode !== 0) {
            echo cli_red(
                "\nEncoding failed.\n"
                . "Exit code: {$exitCode}\n"
            );

            return;
        }

        echo cli_green(
            "\nEncoding completed successfully.\n"
        );
    }

    /**
     * Find ionCube Encoder.
     *
     * Search order:
     *
     * 1. IONCUBE_ENCODER
     * 2. PATH
     * 3. Common locations
     */
    private static function findEncoder()
    {
        /*
         * 1. Explicit environment variable.
         */
        $env = getenv('IONCUBE_ENCODER');

        if ($env !== false && trim($env) !== '') {
            $env = trim($env, "\"'");

            if (self::isExecutable($env)) {
                return self::normalizePath($env);
            }

            /*
             * Allow PATH-style value.
             */
            $path = self::findInPath($env);

            if ($path !== null) {
                return $path;
            }
        }

        /*
         * 2. PATH.
         */
        $names = self::encoderNames();

        foreach ($names as $name) {
            $path = self::findInPath($name);

            if ($path !== null) {
                return $path;
            }
        }

        /*
         * 3. Common installation locations.
         */
        foreach (self::commonPaths() as $path) {
            if (self::isExecutable($path)) {
                return self::normalizePath($path);
            }
        }

        return null;
    }

    /**
     * Possible executable names.
     */
    private static function encoderNames()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'encoder.exe',
                'ioncube_encoder.exe',
                'ioncube-encoder.exe',
            ];
        }

        return [
            'encoder',
            'ioncube_encoder',
            'ioncube-encoder',
        ];
    }

    /**
     * Search executable in PATH without depending on
     * where/which.
     */
    private static function findInPath($name)
    {
        /*
         * Already an absolute/relative path.
         */
        if (self::isExecutable($name)) {
            return self::normalizePath($name);
        }

        $pathVariable = getenv('PATH');

        if ($pathVariable === false || $pathVariable === '') {
            return null;
        }

        $separator = PHP_OS_FAMILY === 'Windows'
            ? ';'
            : ':';

        $directories = explode(
            $separator,
            $pathVariable
        );

        foreach ($directories as $directory) {
            $directory = trim($directory);

            if ($directory === '') {
                continue;
            }

            $candidate = rtrim(
                $directory,
                "/\\"
            ) . DIRECTORY_SEPARATOR . $name;

            if (self::isExecutable($candidate)) {
                return self::normalizePath($candidate);
            }
        }

        return null;
    }

    /**
     * Common encoder locations.
     */
    private static function commonPaths()
    {
        $paths = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $programFiles = getenv('ProgramFiles');
            $programFilesX86 = getenv('ProgramFiles(x86)');

            if ($programFiles) {
                $paths[] = $programFiles
                    . '\\ionCube\\Encoder\\encoder.exe';

                $paths[] = $programFiles
                    . '\\ionCube\\ionCube Encoder\\encoder.exe';
            }

            if ($programFilesX86) {
                $paths[] = $programFilesX86
                    . '\\ionCube\\Encoder\\encoder.exe';

                $paths[] = $programFilesX86
                    . '\\ionCube\\ionCube Encoder\\encoder.exe';
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $paths = [
                '/usr/local/bin/encoder',
                '/usr/local/bin/ioncube_encoder',
                '/opt/ioncube/encoder',
                '/opt/ionCube/encoder',
            ];
        } else {
            /*
             * Linux / Android / Termux.
             */
            $paths = [
                '/usr/local/bin/encoder',
                '/usr/local/bin/ioncube_encoder',
                '/usr/bin/encoder',
                '/usr/bin/ioncube_encoder',
                '/opt/ioncube/encoder',
                '/opt/ionCube/encoder',
                '/data/data/com.termux/files/usr/bin/encoder',
                '/data/data/com.termux/files/usr/bin/ioncube_encoder',
            ];
        }

        return $paths;
    }

    /**
     * Check whether a path points to an executable.
     */
    private static function isExecutable($path)
    {
        if (!is_file($path)) {
            return false;
        }

        /*
         * Windows does not use Unix executable permission bits.
         */
        if (PHP_OS_FAMILY === 'Windows') {
            return true;
        }

        return is_executable($path);
    }

    /**
     * Normalize path.
     */
    private static function normalizePath($path)
    {
        $real = realpath($path);

        return $real !== false
            ? $real
            : $path;
    }

    /**
     * Convert relative path to absolute path.
     */
    private static function absolutePath($path)
    {
        $real = realpath($path);

        if ($real !== false) {
            return $real;
        }

        $base = getcwd();

        if ($path === '.') {
            return $base;
        }

        return rtrim(
            $base,
            "/\\"
        ) . DIRECTORY_SEPARATOR . ltrim(
            $path,
            "/\\"
        );
    }

    /**
     * Build encoder command.
     *
     * NOTE:
     * The exact ionCube Encoder CLI syntax depends on
     * the installed Encoder version.
     */
    private static function buildCommand(
        $encoder,
        $source,
        $output
    ) {
        return [
            $encoder,
            $source,
            $output,
        ];
    }

    /**
     * Execute process cross-platform.
     */
    private static function runProcess(array $command)
    {
        $commandLine = self::buildProcessCommand(
            $command
        );

        $descriptorSpec = [
            0 => [
                'pipe',
                'r',
            ],
            1 => [
                'pipe',
                'w',
            ],
            2 => [
                'pipe',
                'w',
            ],
        ];

        $process = proc_open(
            $commandLine,
            $descriptorSpec,
            $pipes
        );

        if (!is_resource($process)) {
            echo cli_red(
                "Error: Unable to start ionCube Encoder.\n"
            );

            return 1;
        }

        fclose($pipes[0]);

        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);

            if ($line !== false) {
                echo $line;
            }
        }

        while (!feof($pipes[2])) {
            $line = fgets($pipes[2]);

            if ($line !== false) {
                echo cli_red($line);
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    /**
     * Safely construct a process command.
     */
    private static function buildProcessCommand(array $arguments)
    {
        $escaped = [];

        foreach ($arguments as $argument) {
            $escaped[] = escapeshellarg($argument);
        }

        return implode(' ', $escaped);
    }
}