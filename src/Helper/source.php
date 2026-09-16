<?php

/**
 * **Source File Helper**
 *
 * Utility class for managing source files located in INDEX_DIR/source.
 */
class Source
{
    /** @var string Source folder name */
    private const SOURCE_DIR = 'source';

    /**
     * Shared finfo instance.
     */
    private static ?finfo $finfo = null;

    /**
     * Build URL for a source file (located in INDEX_DIR/source).
     *
     * @param string|array $file File path or config array
     * @param string|null $extension File extension
     */
    public static function url($file = '', ?string $extension = null): string
    {
        if (is_array($file)) {
            $filename = $file['file'] ?? '';
            $ext = $file['extension'] ?? '';
        } else {
            $filename = (string) $file;
            $ext = $extension ?? '';
        }

        if ($ext && str_contains($filename, '.') && !str_contains($filename, '/')) {
            $parts = explode('.', $filename, 2);
            if (count($parts) === 2) {
                $filename = $parts[0] . '/' . $parts[1];
            }
        }

        $info = pathinfo($filename);
        if ($ext && (!isset($info['extension']) || $info['extension'] !== $ext)) {
            $filename .= '.' . $ext;
        }

        $path = preg_replace('#[^a-zA-Z0-9/_\.-]#', '', $filename);
        $path = ltrim($path, '/');

        return '/' . self::SOURCE_DIR . '/' . $path;
    }

    /**
     * Shortcut for url()
     */
    public static function file($file = '', $extension = null): string
    {
        return self::url($file, $extension);
    }

    /**
     * Get the full path for a source file.
     *
     * @param string|array $file File name
     */
    public static function path($file = ''): string
    {
        return rtrim(INDEX_DIR, '/\\') . self::url($file);
    }

    /**
     * Return full filesystem path to meta file for a source file.
     */
    public static function metaPath(string $file): string
    {
        $path = rtrim(INDEX_DIR, '/\\') . '/' . self::SOURCE_DIR . '/' . ltrim($file, '/');
        return $path . '.meta.json';
    }

    /**
     * Write metadata array for a file (JSON) next to the file.
     */
    public static function writeMeta(string $file, array $meta): bool
    {
        $mp = self::metaPath($file);
        $dir = dirname($mp);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($mp, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    }

    /**
     * Read metadata JSON for a file if exists.
     */
    public static function getMeta(string $file): ?array
    {
        $mp = self::metaPath($file);
        if (!file_exists($mp)) {
            return null;
        }
        $s = file_get_contents($mp);
        if ($s === false) {
            return null;
        }
        $j = json_decode($s, true);
        return is_array($j) ? $j : null;
    }

    /**
     * Check if a source file exists in INDEX_DIR/source.
     *
     * @param string|array $file File name
     * @param bool $showInfo Return file info if true
     * @return bool|array
     */
    public static function check($file = '', bool $showInfo = false)
    {
        $baseDir = rtrim(INDEX_DIR, '/\\') . DIRECTORY_SEPARATOR . self::SOURCE_DIR;
        $relativePath = ltrim(self::url($file), '/');
        $filePath = rtrim(INDEX_DIR, '/\\') . DIRECTORY_SEPARATOR . $relativePath;

        $realBase = realpath($baseDir);
        $realPath = realpath($filePath);

        if ($realPath === false || !str_starts_with($realPath, $realBase)) {
            return false;
        }

        if ($showInfo) {
            $info = [
                'path' => $realPath,
                'size' => filesize($realPath),
                'modified' => filemtime($realPath),
                'is_readable' => is_readable($realPath),
                'is_writable' => is_writable($realPath),
                'is_executable' => is_executable($realPath)
            ];

            // name & extension
            $info['name'] = basename($realPath);
            $info['extension'] = pathinfo($realPath, PATHINFO_EXTENSION);

            // mime
            $m = self::detectMime($realPath);
            $info['mime'] = $m;

            // category & inspect media
            if (str_starts_with($m, 'image/')) {
                $info['category'] = 'image';
                $gs = @getimagesize($realPath);
                if ($gs && isset($gs[0], $gs[1])) {
                    $info['resolution'] = $gs[0] . 'x' . $gs[1];
                }
            } elseif (str_starts_with($m, 'video/')) {
                $info['category'] = 'video';
                $esc = escapeshellarg($realPath);
                $res = @shell_exec("ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 $esc 2>&1");
                if ($res) {
                    $res = trim($res);
                    if ($res !== '') {
                        $info['resolution'] = $res;
                    }
                }
                $dur = @shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $esc 2>&1");
                if ($dur) {
                    $info['duration'] = (float) trim($dur);
                }
            } elseif (str_starts_with($m, 'audio/')) {
                $info['category'] = 'audio';
                $esc = escapeshellarg($realPath);
                $dur = @shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $esc 2>&1");
                if ($dur) {
                    $info['duration'] = (float) trim($dur);
                }
            } elseif (in_array($m, ['application/zip', 'application/x-rar-compressed', 'application/x-tar'])) {
                $info['category'] = 'archive';
            } else {
                $info['category'] = 'document';
            }

            // include stored metadata if exists (meta has precedence for user-provided values)
            $meta = self::getMeta($file);
            if ($meta) {
                $info['meta'] = $meta;
                // merge specific keys if present
                foreach (['name', 'extension', 'mime', 'category', 'resolution', 'duration'] as $k) {
                    if (isset($meta[$k])) {
                        $info[$k] = $meta[$k];
                    }
                }
            }

            return $info;
        }
        return true;
    }

    /**
     * Upload a file to a specified location within INDEX_DIR/source.
     *
     * @param array $file File from $_FILES
     * @param string $location Target subdirectory inside /source
     * @return bool|string Path of uploaded file or false
     */
    public static function upload(array $file, string $location = '')
    {
        if (!isset($file['tmp_name'], $file['name'])) {
            return false;
        }

        $targetDir = rtrim(INDEX_DIR, '/\\') . '/' . self::SOURCE_DIR . '/' . trim($location, '/\\');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetFile = $targetDir . '/' . basename($file['name']);

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // attempt to write metadata for the file
            $relative = ltrim($location . '/' . basename($file['name']), '/');
            $meta = [];
            $meta['name'] = basename($file['name']);
            $meta['extension'] = pathinfo($file['name'], PATHINFO_EXTENSION);

            // detect mime
            $m = self::detectMime($targetFile);
            $meta['mime'] = $m;

            // categorize
            if (str_starts_with($m, 'image/')) {
                $meta['category'] = 'image';
                // resolution
                $gs = @getimagesize($targetFile);
                if ($gs && isset($gs[0], $gs[1])) {
                    $meta['resolution'] = $gs[0] . 'x' . $gs[1];
                }
            } elseif (str_starts_with($m, 'video/')) {
                $meta['category'] = 'video';
                // try ffprobe for resolution/duration
                $esc = escapeshellarg($targetFile);
                $res = @shell_exec("ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 $esc 2>&1");
                if ($res) {
                    $res = trim($res);
                    if ($res !== '') {
                        $meta['resolution'] = $res;
                    }
                }
                $dur = @shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $esc 2>&1");
                if ($dur) {
                    $meta['duration'] = (float) trim($dur);
                }
            } elseif (str_starts_with($m, 'audio/')) {
                $meta['category'] = 'audio';
                $esc = escapeshellarg($targetFile);
                $dur = @shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $esc 2>&1");
                if ($dur) {
                    $meta['duration'] = (float) trim($dur);
                }
            } elseif (in_array($m, ['application/zip', 'application/x-rar-compressed', 'application/x-tar'])) {
                $meta['category'] = 'archive';
            } else {
                $meta['category'] = 'document';
            }

            // size & modified
            $meta['size'] = filesize($targetFile);
            $meta['modified'] = filemtime($targetFile);

            // write meta
            self::writeMeta($relative, $meta);

            return $targetFile;
        }
        return false;
    }

    /**
     * Rename a file in INDEX_DIR/source.
     *
     * @param string $oldFile Old file name
     * @param string $newFile New file name
     */
    public static function rename(string $oldFile, string $newFile): bool
    {
        $oldPath = rtrim(INDEX_DIR, '/\\') . self::url($oldFile);
        $newPath = rtrim(INDEX_DIR, '/\\') . self::url($newFile);

        if (!file_exists($oldPath)) {
            return false;
        }

        $dir = dirname($newPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ok = rename($oldPath, $newPath);
        // move meta if exists
        $oldMeta = self::metaPath($oldFile);
        $newMeta = self::metaPath($newFile);
        if (file_exists($oldMeta)) {
            $mdir = dirname($newMeta);
            if (!is_dir($mdir)) {
                mkdir($mdir, 0755, true);
            }
            @rename($oldMeta, $newMeta);
        }

        return $ok;
    }

    /**
     * Remove a file from INDEX_DIR/source.
     *
     * @param string $file File name
     */
    public static function remove(string $file): bool
    {
        $filePath = rtrim(INDEX_DIR, '/\\') . self::url($file);
        $ok = file_exists($filePath) && unlink($filePath);
        $meta = self::metaPath($file);
        if (file_exists($meta)) {
            @unlink($meta);
        }
        return $ok;
    }

    /**
     * Replace an old file with a new file.
     *
     * @param string $oldFile Old file name
     * @param array $newFile New file (from $_FILES)
     * @return bool|string Path of new file or false
     */
    public static function change(string $oldFile, array $newFile)
    {
        if (!self::remove($oldFile)) {
            return false;
        }

        $location = dirname($oldFile);
        $res = self::upload($newFile, $location);
        // if uploaded, update meta name if needed
        if ($res !== false) {
            $newRel = ltrim($location . '/' . basename($newFile['name']), '/');
            $meta = self::getMeta($newRel);
            if ($meta) {
                $meta['name'] = basename($newFile['name']);
                self::writeMeta($newRel, $meta);
            }
        }
        return $res;
    }

    /**
     * Detect MIME type safely.
     */
    private static function detectMime(string $file): string
    {
        if (!extension_loaded('fileinfo')) {
            return mime_content_type($file) ?: 'application/octet-stream';
        }

        self::$finfo ??= new finfo(FILEINFO_MIME_TYPE);

        return self::$finfo->file($file)
            ?: 'application/octet-stream';
    }
}


if (!function_exists('source')) {
    /**
     * Get the URL for a source file (located in INDEX_DIR/source).
     */
    function source(string $path = ''): string
    {
        $baseUrl = getBaseUrl();

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $path = ltrim(str_replace(['..', '\\'], '', $path), '/');

        if (str_contains($baseUrl, '/public/')) {
            return $baseUrl . 'source/' . $path;
        }

        if (
            preg_match('/^([a-zA-Z0-9\-\.]+)(:\d+)?$/', $host) &&
            (!str_contains($scriptName, '/public/'))
        ) {
            return $baseUrl . 'source/' . $path;
        }

        return $baseUrl . 'source/' . $path;
    }
}