<?php

/** Simple PHAR builder CLI */
class BuildPhar
{
    public const VERSION = '2026.5.1-dev';
}

if (PHP_SAPI !== 'cli') {
    exit('This script only run on CLI.');
}

const VERSION = BuildPhar::VERSION;

$checkPhar = extension_loaded('phar') && !ini_get('phar.readonly');

function show_helper()
{
    global $checkPhar;
    echo "PHAR Builder v" . VERSION . "\n\n";
    echo $checkPhar ? "phar extension is loaded and writable.\n" : "WARNING: phar extension is not loaded or is read-only. PHAR building will not work.\n";
    echo "\n";
    echo "Usage:\n";
    echo "  php build-phar.php help              Show this help and features\n";
    echo "  php build-phar.php build [options]   Build a PHAR\n";
    echo "  php build-phar.php extract [options] Extract or list a PHAR's contents\n";
    echo "\n";
    echo "Features:\n";
    echo "  - Core helper: show version and functions\n";
    echo "  - Build phar: specify output name, includes, and stub (file or text)\n";
    echo "\n";
    echo "Build options:\n";
    echo "  --output=NAME             Output PHAR file name (default: app.phar)\n";
    echo "  --include=PATHS           Comma-separated files/dirs (relative to script) to include\n";
    echo "  --stub-file=FILE          Path to stub file to use\n";
    echo "  --stub-string=STRING      Provide stub contents directly\n";
    echo "  --root-extras=FILES       Comma-separated root files to include (default: .env,cacert.pem,composer.json,composer.lock)\n";
    echo "  --ignore=PATHS            Comma-separated file/dir patterns to exclude from build\n";
    echo "  --force                   Overwrite existing output file without prompt\n";
    echo "\n";
    echo "Extract options:\n";
    echo "  --phar=FILE               PHAR file path (relative or absolute)\n";
    echo "  --output=DIR              Destination directory (default: <pharname>-extracted)\n";
    echo "  --force                   Overwrite existing files\n";
    echo "  --list                    Only list files\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php build-phar.php build --output=my.phar --include=app,config,public --stub-file=stubs/boot.php\n";
}

/**
 * Extract or list a PHAR's contents.
 * Options:
 *  --phar=FILE   PHAR file path (relative or absolute)
 *  --output=DIR  Destination dir (default: <pharname>-extracted)
 *  --force       Overwrite existing files
 *  --list        Only list files
 */
function extract_phar(array $opts)
{
    $baseDir = __DIR__;
    $pharArg = $opts['phar'] ?? '';
    if (trim($pharArg) === '') {
        $pharArg = prompt("PHAR file path (relative to project root): ");
        if (trim($pharArg) === '') {
            echo "Aborted.\n";
            return;
        }
    }

    // Accept either absolute/relative path or project-relative
    $pharPath = $pharArg;
    if (!is_file($pharPath)) {
        $candidate = $baseDir . DIRECTORY_SEPARATOR . $pharArg;
        if (is_file($candidate)) {
            $pharPath = $candidate;
        }
    }

    if (!is_file($pharPath)) {
        echo "ERROR: PHAR file not found: {$pharArg}\n";
        return;
    }

    $baseName = pathinfo($pharPath, PATHINFO_FILENAME);
    $dest = $opts['output'] ?? ($baseName . '-extracted');
    if (!is_dir($dest) && !mkdir($dest, 0777, true)) {
        echo "ERROR: Failed to create destination directory: {$dest}\n";
        return;
    }

    try {
        $phar = new Phar($pharPath);

        if (!empty($opts['list'])) {
            echo "Listing contents of {$pharPath}:\n";
            foreach ($phar as $file) {
                /** @var PharFileInfo $file */
                echo $file->getPathName() . PHP_EOL;
            }
            return;
        }

        $overwrite = !empty($opts['force']);
        echo "Extracting {$pharPath} to {$dest} (overwrite=" . ($overwrite ? 'yes' : 'no') . ")...\n";
        $phar->extractTo($dest, null, $overwrite);
        echo "Extraction complete.\n";
    } catch (Exception $e) {
        echo "Extraction failed: " . $e->getMessage() . "\n";
    }
}

function prompt($text)
{
    echo $text;
    return trim(fgets(STDIN));
}

function normalize_rel($path)
{
    return str_replace('\\', '/', ltrim($path, '/\\'));
}

function collect_files(array $includes, $baseDir, array $ignores = [])
{
    $collected = [];
    $normalizedIgnores = array_map('normalize_rel', $ignores);
    foreach ($includes as $inc) {
        $inc = trim($inc);
        if ($inc === '') {
            continue;
        }
        $full = $baseDir . DIRECTORY_SEPARATOR . $inc;
        if (is_file($full)) {
            $rel = normalize_rel(substr($full, strlen($baseDir) + 1));
            $skip = false;
            foreach ($normalizedIgnores as $ig) {
                if ($ig === '') {
                    continue;
                }
                if (str_starts_with($rel, rtrim($ig, '/'))) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            $collected[$full] = $rel;
            continue;
        }
        if (is_dir($full)) {
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full));
            foreach ($rii as $file) {
                if ($file->isFile()) {
                    $path = $file->getPathname();
                    $rel = normalize_rel(substr($path, strlen($baseDir) + 1));
                    $skip = false;
                    foreach ($normalizedIgnores as $ig) {
                        if ($ig === '') {
                            continue;
                        }
                        if (str_starts_with($rel, rtrim($ig, '/'))) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) {
                        continue;
                    }
                    $collected[$path] = $rel;
                }
            }
            continue;
        }
        // Allow patterns relative to base (e.g., "app" may be matched case-insensitively)
        // if not found, skip with a message
        fwrite(STDOUT, "Warning: include path not found: {$inc}\n");
    }
    return $collected;
}

/**
 * Build the PHAR file.
 * @param array $opts Options for building the PHAR
 * @return void
 */
function build_phar(array $opts)
{
    if (ini_get('phar.readonly')) {
        exit("ERROR: phar.readonly is enabled in php.ini — disable it to build PHAR files.\n");
    }

    $baseDir = __DIR__;
    $output = $opts['output'] ?? '';

    // Require the user to provide an output filename if not supplied.
    while (true) {
        if (trim($output) === '') {
            $outInput = prompt("Output filename (required, type 'q' to cancel): ");
            if (strtolower(trim($outInput)) === 'q') {
                echo "Aborted.\n";
                return;
            }
            $outInput = trim($outInput);
            if ($outInput === '') {
                echo "Filename cannot be empty.\n";
                continue;
            }
            if (str_contains($outInput, '/') || str_contains($outInput, '\\') || str_contains($outInput, DIRECTORY_SEPARATOR)) {
                echo "Do not include directory separators. Enter only a filename.\n";
                continue;
            }
            if (pathinfo($outInput, PATHINFO_EXTENSION) !== 'phar') {
                $outInput .= '.phar';
            }
            $output = $outInput;
        }

        $outputPath = $baseDir . DIRECTORY_SEPARATOR . $output;

        // If file doesn't exist or user forced overwrite, proceed.
        if (!file_exists($outputPath) || !empty($opts['force'])) {
            if (file_exists($outputPath) && !empty($opts['force'])) {
                @unlink($outputPath);
            }
            break;
        }

        // File exists and not forced: ask to overwrite.
        $ans = prompt("Output {$output} exists. Overwrite? (y/N): ");
        if (strtolower($ans) === 'y') {
            @unlink($outputPath);
            break;
        }

        // User declined overwrite — ask for a new filename (loop will repeat)
        echo "Provide an alternative output filename (or type 'q' to cancel).\n";
        $output = '';
    }

    $includes = $opts['include'] ?? [];
    if (empty($includes)) {
        echo "No includes specified. Please enter comma-separated folders/files to include (e.g. app,public,src): ";
        $line = trim(fgets(STDIN));
        if ($line === '') {
            echo "Aborted. No includes provided.\n";
            return;
        }
        $includes = array_map('trim', explode(',', $line));
    }

    $files = collect_files($includes, $baseDir, $opts['ignore'] ?? []);
    $rootExtras = $opts['root-extras'] ?? [];
    if (empty($rootExtras)) {
        echo "No root files specified. Please enter comma-separated root files to include (e.g. .env,cacert.pem,composer.json): ";
        $line = trim(fgets(STDIN));
        if ($line === '') {
            echo "No root files will be included.\n";
            $rootExtras = [];
        } else {
            $rootExtras = array_filter(array_map('trim', explode(',', $line)));
        }
    }
    $rootAdd = [];
    foreach ($rootExtras as $fname) {
        $fpath = $baseDir . DIRECTORY_SEPARATOR . $fname;
        if (file_exists($fpath) && is_file($fpath)) {
            $rootAdd[$fpath] = $fname;
        }
    }

    try {
        $phar = new Phar($outputPath);
        $phar->startBuffering();

        // Merge files and root extras, avoiding duplicate internal paths.
        $addMap = [];
        foreach ($files as $full => $rel) {
            $addMap[$rel] = $full;
        }
        foreach ($rootAdd as $full => $rel) {
            if (isset($addMap[$rel])) {
                // same internal path already scheduled, skip and warn
                echo "Warning: skipping duplicate entry for {$rel}\n";
                continue;
            }
            $addMap[$rel] = $full;
        }

        echo "Adding files (" . count($addMap) . ")...\n";
        foreach ($addMap as $rel => $full) {
            echo "[ADD] " . $rel . "\n";
            $phar->addFile($full, $rel);
        }

        // Stub handling
        $stub = null;
        $stubFileRel = null;
        if (!empty($opts['stub-file']) && is_file($baseDir . DIRECTORY_SEPARATOR . $opts['stub-file'])) {
            $stubFileRel = normalize_rel($opts['stub-file']);
            $stub = file_get_contents($baseDir . DIRECTORY_SEPARATOR . $opts['stub-file']);
            echo "Using stub file: " . $opts['stub-file'] . "\n";
        } elseif (!empty($opts['stub-string'])) {
            $stub = $opts['stub-string'];
            echo "Using provided stub string.\n";
        }

        if ($stub === null) {
            // No default stub any more — require the user to pick a stub file
            // or enter stub contents interactively.
            echo "No stub specified. You must choose a stub file or enter stub text.\n";
            while (true) {
                echo "Choose stub source: (1) stub file, (2) enter stub text, (q) cancel: ";
                $choice = trim(fgets(STDIN));
                if (strtolower($choice) === 'q') {
                    echo "Aborted.\n";
                    return;
                }
                if ($choice === '1') {
                    $stubPath = prompt("Stub file path (relative to project root): ");
                    if ($stubPath === '') {
                        echo "Empty path provided.\n";
                        continue;
                    }
                    $fullStub = $baseDir . DIRECTORY_SEPARATOR . $stubPath;
                    if (!is_file($fullStub)) {
                        echo "Stub file not found: {$stubPath}\n";
                        continue;
                    }
                    $stub = file_get_contents($fullStub);
                    echo "Using stub file: {$stubPath}\n";
                    break;
                } elseif ($choice === '2') {
                    echo "Enter stub contents. End with a line containing only END\n";
                    $lines = [];
                    while (($line = fgets(STDIN)) !== false) {
                        $line = rtrim($line, "\r\n");
                        if ($line === 'END') {
                            break;
                        }
                        $lines[] = $line;
                    }
                    $stub = implode("\n", $lines);
                    echo "Using provided stub text.\n";
                    break;
                } else {
                    echo "Invalid choice. Enter 1, 2, or q.\n";
                }
            }
        }

        // If selected stub came from an external file, store its relative path for wrapping
        if (isset($stubPath) && !empty($stubPath)) {
            $stubFileRel = normalize_rel($stubPath);
        }

        // If the stub does not contain __HALT_COMPILER(), we can wrap it (when we have a stub file inside the PHAR)
        if (!str_contains($stub, '__HALT_COMPILER')) {
            if ($stubFileRel !== null) {
                $wrapper = "#!/usr/bin/env php\r\n";
                $wrapper .= "<?php Phar::mapPhar('{$output}'); require 'phar://{$output}/{$stubFileRel}'; __HALT_COMPILER();";
                $stub = $wrapper;
                echo "Wrapped stub to require phar://{$output}/{$stubFileRel} and appended __HALT_COMPILER().\n";
            } else {
                echo "ERROR: provided stub text does not contain __HALT_COMPILER(); and no stub file available to wrap.\n";
                return;
            }
        }

        $phar->setStub($stub);
        $phar->stopBuffering();

        echo "PHAR built successfully: {$outputPath}\n";
    } catch (Exception $e) {
        echo "PHAR build failed: " . $e->getMessage() . "\n";
    }
}

// Simple argv parsing for our small CLI
$argv = $_SERVER['argv'];
$argc = $_SERVER['argc'];

$cmd = $argv[1] ?? 'help';
if (in_array($cmd, ['-h', '--help', 'help'])) {
    show_helper();
    exit(0);
}

if ($cmd === 'build') {
    if (!extension_loaded('phar')) {
        exit("ERROR: phar extension is not loaded — cannot build PHAR files.\n");
    }
    if (ini_get('phar.readonly')) {
        exit("ERROR: phar.readonly is enabled in php.ini — disable it to build PHAR files.\n");
    }
    $opts = [];
    $baseDir = __DIR__;
    $jsonPath = $baseDir . DIRECTORY_SEPARATOR . 'config-build.json';
    $jsonConfig = null;
    $configDetected = false;
    if (file_exists($jsonPath)) {
        $jsonRaw = file_get_contents($jsonPath);
        $jsonConfig = json_decode($jsonRaw, true);
        if (is_array($jsonConfig)) {
            $configDetected = true;
            // Parse config
            // Output name
            if (!empty($jsonConfig['name'][0])) {
                $opts['output'] = $jsonConfig['name'][0] . (str_ends_with($jsonConfig['name'][0], '.phar') ? '' : '.phar');
            }
            // Overwrite
            if (isset($jsonConfig['override'])) {
                $opts['force'] = (bool)$jsonConfig['override'];
            }
            // Includes
            if (!empty($jsonConfig['path']) && is_array($jsonConfig['path'])) {
                $opts['include'] = $jsonConfig['path'];
            }
            if (!empty($jsonConfig['ignore']) && is_array($jsonConfig['ignore'])) {
                $opts['ignore'] = $jsonConfig['ignore'];
            }
            // Root files
            if (!empty($jsonConfig['root']) && is_array($jsonConfig['root'])) {
                $opts['root-extras'] = $jsonConfig['root'];
            }
            // Stub
            if (!empty($jsonConfig['stub'][0])) {
                $stubVal = $jsonConfig['stub'][0];
                if (str_starts_with($stubVal, '<?php')) {
                    $opts['stub-string'] = $stubVal;
                } else {
                    $opts['stub-file'] = $stubVal;
                }
            }
        } else {
            echo "config-build.json exists but is not valid JSON.\n";
        }
    }
    // parse remaining CLI args (override json if present)
    for ($i = 2; $i < $argc; $i++) {
        $arg = $argv[$i];
        if (str_starts_with($arg, '--')) {
            $pair = substr($arg, 2);
            $parts = explode('=', $pair, 2);
            $key = $parts[0];
            $val = $parts[1] ?? '';
            switch ($key) {
                case 'output':
                    $opts['output'] = $val;
                    break;
                case 'include':
                    $opts['include'] = array_map('trim', explode(',', $val));
                    break;
                case 'stub-file':
                    $opts['stub-file'] = $val;
                    break;
                case 'stub-string':
                    $opts['stub-string'] = $val;
                    break;
                case 'root-extras':
                    $opts['root-extras'] = array_filter(array_map('trim', explode(',', $val)));
                    break;
                case 'force':
                    $opts['force'] = true;
                    break;
                default:
                    echo "Unknown option: --{$key}\n";
            }
        }
    }
    // Nếu phát hiện config, hỏi người dùng muốn build tự động hay thủ công
    if ($configDetected) {
        echo "\nPhát hiện file cấu hình config-build.json.\n";
        echo "Bạn muốn build PHAR tự động theo cấu hình này không?\n";
        echo "Chọn (a) để build tự động, (m) để build thủ công, (q) để thoát: ";
        $choice = strtolower(trim(fgets(STDIN)));
        if ($choice === 'q') {
            echo "Đã hủy.\n";
            exit(0);
        }
        if ($choice === 'm') {
            // Xóa các tùy chọn lấy từ config để buộc build thủ công
            $opts = [];
        }
        // Nếu chọn 'a' hoặc bất kỳ phím nào khác thì giữ nguyên opts
    }
    build_phar($opts);
    exit(0);
}

if ($cmd === 'extract') {
    $opts = [];
    for ($i = 2; $i < $argc; $i++) {
        $arg = $argv[$i];
        if (str_starts_with($arg, '--')) {
            $pair = substr($arg, 2);
            $parts = explode('=', $pair, 2);
            $key = $parts[0];
            $val = $parts[1] ?? '';
            switch ($key) {
                case 'phar':
                    $opts['phar'] = $val;
                    break;
                case 'output':
                    $opts['output'] = $val;
                    break;
                case 'force':
                    $opts['force'] = true;
                    break;
                case 'list':
                    $opts['list'] = true;
                    break;
                default:
                    echo "Unknown option: --{$key}\n";
            }
        }
    }

    extract_phar($opts);
    exit(0);
}

// fallback
show_helper();
