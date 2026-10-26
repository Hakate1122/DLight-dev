<?php
namespace DLight\Application;

use RuntimeException;

use function in_array;

/**
 * File Storage Handler - Provides a unified API for local filesystem and FTP storage.
 * 
 * Supported drivers:
 * - local
 * - ftp
 * 
 * Example:
 * 
 * Local:
 * 
 * $file = new File([
 *     'driver' => 'local',
 *     'root' => __DIR__ . '/storage',
 * ]);
 * 
 * FTP:
 * 
 * $file = new File([
 *     'driver' => 'ftp',
 *     'host' => '127.0.0.1',
 *     'port' => 21,
 *     'username' => 'user',
 *     'password' => 'password',
 *     'root' => '/public_html/storage',
 *     'passive' => true,
 *     'timeout' => 10,
 * ]);
 */
class File
{
    /**
     * Storage driver.
     * 
     * @var string
     */
    protected string $driver;

    /**
     * Storage root directory.
     * 
     * @var string
     */
    protected string $root;

    /**
     * FTP connection.
     * 
     * @var resource|\FTP\Connection|null
     */
    protected $ftp = null;

    /**
     * FTP configuration.
     * 
     * @var array
     */
    protected array $config = [];

    /**
     * Create a storage instance.
     * 
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->driver = strtolower((string) ($config['driver'] ?? 'local'));
        
        if (!in_array($this->driver, ['local', 'ftp'], true)) {
            throw new RuntimeException("Unsupported file storage driver: {$this->driver}");
        }

        $this->root = $this->normalizeRoot((string) ($config['root'] ?? ''));

        if ($this->driver === 'local') {
            if ($this->root === '') {
                $this->root = DIRECTORY_SEPARATOR;
            }
            return;
        }

        $this->connectFTP($config);
    }

    /**
     * Prevent unserialization issues with resource connections.
     */
    public function __wakeup()
    {
        if ($this->driver === 'ftp') {
            $this->connectFTP($this->config);
        }
    }

    /**
     * Get current storage driver.
     */
    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * Get configured storage root.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * Build a path relative to the storage root.
     */
    protected function fullPath(string $path): string
    {
        $path = $this->normalizePath($path);
        if ($path === '') {
            return $this->root;
        }
        if ($this->root === '' || $this->root === '/') {
            return '/' . $path;
        }
        return rtrim($this->root, '/') . '/' . $path;
    }

    /**
     * Normalize a storage path.
     */
    protected function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    /**
     * Normalize storage root.
     */
    protected function normalizeRoot(string $root): string
    {
        $root = str_replace('\\', '/', trim($root));
        if ($root === '') {
            return '';
        }
        /*
         * Preserve Windows drive paths such as:
         * C:/laragon/www/storage
         */
        if (preg_match('/^[A-Za-z]:\/$/', $root)) {
            return $root;
        }
        if ($root === '/') {
            return '/';
        }
        return rtrim($root, '/');
    }

    /**
     * Check whether a file exists.
     */
    public function exists(string $path): bool
    {
        if ($this->driver === 'local') {
            return file_exists($this->fullPath($path));
        }

        $remote = $this->fullPath($path);
        $size = @ftp_size($this->ftp, $remote);
        if ($size !== -1) {
            return true;
        }

        /*
         * Some FTP servers do not support SIZE correctly.
         * Fall back to directory listing.
         */
        $directory = dirname($remote);
        $name = basename($remote);
        $list = @ftp_nlist($this->ftp, $directory);
        if ($list === false) {
            return false;
        }

        foreach ($list as $item) {
            if (basename($item) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a path is a directory.
     */
    public function isDirectory(string $path): bool
    {
        if ($this->driver === 'local') {
            return is_dir($this->fullPath($path));
        }

        $remote = $this->fullPath($path);
        /*
         * ftp_chdir() is not used because it changes the
         * current working directory of the FTP connection.
         */
        $current = @ftp_pwd($this->ftp);
        if ($current === false) {
            return false;
        }

        if (@ftp_chdir($this->ftp, $remote)) {
            @ftp_chdir($this->ftp, $current);
            return true;
        }

        return false;
    }

    /**
     * Read a file.
     * 
     * @throws RuntimeException
     */
    public function read(string $path): string
    {
        if ($this->driver === 'local') {
            $file = $this->fullPath($path);
            $content = @file_get_contents($file);
            if ($content === false) {
                throw new RuntimeException("Unable to read file: {$file}");
            }
            return $content;
        }

        $temp = fopen('php://temp', 'w+b');
        if ($temp === false) {
            throw new RuntimeException('Unable to create temporary stream.');
        }

        try {
            if (!@ftp_fget($this->ftp, $temp, $this->fullPath($path), FTP_BINARY)) {
                throw new RuntimeException("Unable to read FTP file: {$path}");
            }
            rewind($temp);
            $content = stream_get_contents($temp);
            if ($content === false) {
                throw new RuntimeException("Unable to read FTP stream: {$path}");
            }
            return $content;
        } finally {
            fclose($temp);
        }
    }

    /**
     * Write a file.
     * 
     * @throws RuntimeException
     */
    public function write(string $path, string $content): bool
    {
        if ($this->driver === 'local') {
            $file = $this->fullPath($path);
            $this->ensureDir(dirname($file));
            $result = @file_put_contents($file, $content);
            if ($result === false) {
                throw new RuntimeException("Unable to write file: {$file}");
            }
            return true;
        }

        $remote = $this->fullPath($path);
        $this->ensureFTPDir(dirname($remote));
        return $this->ftpPutContent($remote, $content);
    }

    /**
     * Alias of write().
     */
    public function put(string $path, string $content): bool
    {
        return $this->write($path, $content);
    }

    /**
     * Append content to a file.
     * 
     * Uses direct append mode for local files.
     * FTP falls back to read/write because FTP has no
     * universally supported append-stream API.
     */
    public function append(string $path, string $content): bool
    {
        if ($this->driver === 'local') {
            $file = $this->fullPath($path);
            $this->ensureDir(dirname($file));
            $result = @file_put_contents($file, $content, FILE_APPEND);
            if ($result === false) {
                throw new RuntimeException("Unable to append file: {$file}");
            }
            return true;
        }

        $old = $this->exists($path) ? $this->read($path) : '';
        return $this->write($path, $old . $content);
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): bool
    {
        if (!$this->exists($path)) {
            return false;
        }

        if ($this->driver === 'local') {
            return @unlink($this->fullPath($path));
        }

        return @ftp_delete($this->ftp, $this->fullPath($path));
    }

    /**
     * Copy a file.
     */
    public function copy(string $from, string $to): bool
    {
        if (!$this->exists($from)) {
            throw new RuntimeException("Source file does not exist: {$from}");
        }

        if ($this->driver === 'local') {
            $destination = $this->fullPath($to);
            $this->ensureDir(dirname($destination));
            if (!@copy($this->fullPath($from), $destination)) {
                throw new RuntimeException("Unable to copy file: {$from} -> {$to}");
            }
            return true;
        }

        /*
         * FTP does not provide a portable server-side copy.
         */
        return $this->write($to, $this->read($from));
    }

    /**
     * Move/rename a file.
     */
    public function move(string $from, string $to): bool
    {
        if (!$this->exists($from)) {
            throw new RuntimeException("Source file does not exist: {$from}");
        }

        if ($this->driver === 'local') {
            $destination = $this->fullPath($to);
            $this->ensureDir(dirname($destination));
            if (!@rename($this->fullPath($from), $destination)) {
                throw new RuntimeException("Unable to move file: {$from} -> {$to}");
            }
            return true;
        }

        $destination = $this->fullPath($to);
        $this->ensureFTPDir(dirname($destination));
        if (!@ftp_rename($this->ftp, $this->fullPath($from), $destination)) {
            throw new RuntimeException("Unable to move FTP file: {$from} -> {$to}");
        }

        return true;
    }

    /**
     * Get file size.
     */
    public function size(string $path): int
    {
        if ($this->driver === 'local') {
            $size = @filesize($this->fullPath($path));
            if ($size === false) {
                throw new RuntimeException("Unable to determine file size: {$path}");
            }
            return $size;
        }

        $size = @ftp_size($this->ftp, $this->fullPath($path));
        if ($size === -1) {
            throw new RuntimeException("Unable to determine FTP file size: {$path}");
        }

        return $size;
    }

    /**
     * Get last modification timestamp.
     */
    public function lastModified(string $path): int
    {
        if ($this->driver === 'local') {
            $time = @filemtime($this->fullPath($path));
            if ($time === false) {
                throw new RuntimeException("Unable to determine modification time: {$path}");
            }
            return $time;
        }

        $time = @ftp_mdtm($this->ftp, $this->fullPath($path));
        if ($time === -1) {
            throw new RuntimeException("Unable to determine FTP modification time: {$path}");
        }

        return $time;
    }

    /**
     * Create a directory recursively.
     */
    public function makeDirectory(string $path): bool
    {
        if ($this->driver === 'local') {
            return $this->ensureDir($this->fullPath($path));
        }

        return $this->ensureFTPDir($this->fullPath($path));
    }

    /**
     * List files inside a directory.
     * 
     * @return array<int, string>
     */
    public function files(string $directory = ''): array
    {
        if ($this->driver === 'local') {
            $dir = $this->fullPath($directory);
            if (!is_dir($dir)) {
                return [];
            }
            $result = [];
            foreach (@scandir($dir) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $full = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_file($full)) {
                    $result[] = $this->normalizePath(trim(($directory !== '' ? $directory . '/' : '') . $item, '/'));
                }
            }
            return $result;
        }

        $dir = $this->fullPath($directory);
        $list = @ftp_nlist($this->ftp, $dir);
        if ($list === false) {
            return [];
        }

        $result = [];
        foreach ($list as $item) {
            $name = basename($item);
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (!$this->isDirectory($item)) {
                $result[] = $this->normalizePath(trim(($directory !== '' ? $directory . '/' : '') . $name, '/'));
            }
        }

        return $result;
    }

    /**
     * List directories.
     * 
     * @return array<int, string>
     */
    public function directories(string $directory = ''): array
    {
        if ($this->driver === 'local') {
            $dir = $this->fullPath($directory);
            if (!is_dir($dir)) {
                return [];
            }
            $result = [];
            foreach (@scandir($dir) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $full = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($full)) {
                    $result[] = $this->normalizePath(trim(($directory !== '' ? $directory . '/' : '') . $item, '/'));
                }
            }
            return $result;
        }

        $dir = $this->fullPath($directory);
        $list = @ftp_nlist($this->ftp, $dir);
        if ($list === false) {
            return [];
        }

        $result = [];
        foreach ($list as $item) {
            $name = basename($item);
            if ($name === '.' || $name === '..') {
                continue;
            }
            if ($this->isDirectory($item)) {
                $result[] = $this->normalizePath(trim(($directory !== '' ? $directory . '/' : '') . $name, '/'));
            }
        }

        return $result;
    }

    /**
     * Return a public URL/path for the file.
     * 
     * For local storage this returns the filesystem path unless
     * a public_url has been configured.
     */
    public function url(string $path): string
    {
        if (!empty($this->config['public_url'])) {
            return rtrim((string) $this->config['public_url'], '/') . '/' . $this->normalizePath($path);
        }
        return $this->fullPath($path);
    }

    /**
     * Ensure a local directory exists.
     */
    protected function ensureDir(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }
        if (@mkdir($dir, 0777, true)) {
            return true;
        }
        /*
         * Another process may have created it between
         * is_dir() and mkdir().
         */
        return is_dir($dir);
    }

    /**
     * Connect to FTP server.
     */
    protected function connectFTP(array $config): void
    {
        if (!function_exists('ftp_connect')) {
            throw new RuntimeException('FTP support is not available. Enable the PHP FTP extension.');
        }

        $host = $config['host'] ?? null;
        if (!$host) {
            throw new RuntimeException('FTP host is required.');
        }

        $port = (int) ($config['port'] ?? 21);
        $timeout = (int) ($config['timeout'] ?? 10);

        $this->ftp = @ftp_connect($host, $port, $timeout);
        if (!$this->ftp) {
            throw new RuntimeException("Unable to connect to FTP server: {$host}:{$port}");
        }

        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if (!@ftp_login($this->ftp, $username, $password)) {
            @ftp_close($this->ftp);
            $this->ftp = null;
            throw new RuntimeException('FTP authentication failed.');
        }

        if (($config['passive'] ?? true) === true) {
            @ftp_pasv($this->ftp, true);
        }

        if ($this->root !== '' && $this->root !== '/') {
            @ftp_chdir($this->ftp, $this->root);
        }
    }

    /**
     * Write content to an FTP file.
     */
    protected function ftpPutContent(string $remotePath, string $content): bool
    {
        $temp = fopen('php://temp', 'w+b');
        if ($temp === false) {
            throw new RuntimeException('Unable to create temporary FTP stream.');
        }

        try {
            if (fwrite($temp, $content) === false) {
                throw new RuntimeException('Unable to write content to temporary FTP stream.');
            }
            rewind($temp);
            if (!@ftp_fput($this->ftp, $remotePath, $temp, FTP_BINARY)) {
                throw new RuntimeException("Unable to write FTP file: {$remotePath}");
            }
            return true;
        } finally {
            fclose($temp);
        }
    }

    /**
     * Recursively create an FTP directory.
     */
    protected function ensureFTPDir(string $directory): bool
    {
        $directory = str_replace('\\', '/', $directory);
        if ($directory === '' || $directory === '/') {
            return true;
        }

        $absolute = str_starts_with($directory, '/');
        $parts = explode('/', trim($directory, '/'));
        $current = $absolute ? '/' : '';

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            $next = $current === '/' ? '/' . $part : ($current === '' ? $part : $current . '/' . $part);
            if (@ftp_chdir($this->ftp, $next)) {
                $current = $next;
                continue;
            }
            if (!@ftp_mkdir($this->ftp, $next)) {
                /*
                 * Another process may have created the directory.
                 */
                if (!@ftp_chdir($this->ftp, $next)) {
                    throw new RuntimeException("Unable to create FTP directory: {$next}");
                }
            }
            $current = $next;
        }

        return true;
    }

    /**
     * Close FTP connection.
     */
    public function close(): void
    {
        if ($this->ftp) {
            @ftp_close($this->ftp);
            $this->ftp = null;
        }
    }

    /**
     * Close FTP connection automatically.
     */
    public function __destruct()
    {
        $this->close();
    }
}