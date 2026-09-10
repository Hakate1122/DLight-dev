<?php

use DLight\Application\Router;
use DLight\Application\View;
use DLight\Application\Session;

if (!function_exists('old')) {
    /**
     * Get old input value from the previous request.
     * @param mixed $default
     * @return mixed|null
     */
    function old(string $key, $default = null)
    {
        if (isset($GLOBALS['old'][$key])) {
            return $GLOBALS['old'][$key];
        }
        if (isset($GLOBALS['old']) && is_array($GLOBALS['old']) && array_key_exists($key, $GLOBALS['old'])) {
            return $GLOBALS['old'][$key];
        }
        return $default;
    }
}

if (!function_exists('jslog')) {
    /**
     * Log data to the browser console for debugging purposes.
     *
     * @param mixed $data The data to be logged. It will be converted to JSON format.
     */
    function jslog($data): void
    {
        echo '<script>';
        echo 'console.log(' . json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) . ');';
        echo '</script>';
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die. A faster coding alternative to var_dump().
     * 
     * Supports multiple variables.
     * @param mixed ...$vars
     * @return never
     */
    function dd(...$vars)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $file = $backtrace[0]['file'] ?? '';
        $line = $backtrace[0]['line'] ?? 0;
        $src = '';
        if ($file && $line) {
            $lines = @file($file);
            if ($lines && isset($lines[$line - 1])) {
                $src = $lines[$line - 1];
            }
        }
        $varNames = [];
        if (preg_match('/dd\((.*)\)/', $src, $m)) {
            $varNames = array_map('trim', explode(',', $m[1]));
        }
        $output = '';
        if ($vars === [] && $varNames === []) {
            $output = "Variable does not exist!\n";
        }
        foreach ($varNames as $i => $raw) {
            $raw = trim($raw);
            if (preg_match('/^[$][a-zA-Z_]\w*$/', $raw)) {
                if (array_key_exists($i, $vars)) {
                    $output .= $raw . ' = ' . craft_custom_var_dump($vars[$i]) . "\n";
                } else {
                    $output .= $raw . ' = *UNDEFINED*' . "\n";
                }
            } elseif (array_key_exists($i, $vars)) {
                $output .= $raw . ' = ' . craft_custom_var_dump($vars[$i]) . "\n";
            } else {
                $output .= '*UNDEFINED*' . "\n";
            }
        }

        if (count($vars) > count($varNames)) {
            $counter = count($vars);
            for ($j = count($varNames); $j < $counter; $j++) {
                $output .= craft_custom_var_dump($vars[$j]) . "\n";
            }
        }
        $isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
        if ($isCli) {
            echo $output . PHP_EOL;
        } else {
            echo '<pre style="background:#222;color:#eee;padding:5px 10px;border-radius:8px;overflow:auto;font-size:13px;line-height:1.2;box-shadow:0 2px 8px #0002;">' . $output . '</pre>';
        }
        die();
    }
}

if (!function_exists('dump')) {
    /**
     * Dump only. A faster coding alternative to var_dump().
     * 
     * Supports multiple variables.
     * @param mixed ...$vars
     * @return void
     */
    function dump(...$vars)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $file = $backtrace[0]['file'] ?? '';
        $line = $backtrace[0]['line'] ?? 0;
        $src = '';
        if ($file && $line) {
            $lines = @file($file);
            if ($lines && isset($lines[$line - 1])) {
                $src = $lines[$line - 1];
            }
        }
        $varNames = [];
        if (preg_match('/dump\((.*)\)/', $src, $m)) {
            $varNames = array_map('trim', explode(',', $m[1]));
        }
        $output = '';
        if ($vars === [] && $varNames === []) {
            $output = "Variable does not exist!\n";
        }
        foreach ($varNames as $i => $raw) {
            $raw = trim($raw);
            if (preg_match('/^[$][a-zA-Z_]\w*$/', $raw)) {
                if (array_key_exists($i, $vars)) {
                    $output .= $raw . ' = ' . craft_custom_var_dump($vars[$i]) . "\n";
                } else {
                    $output .= $raw . ' = *UNDEFINED*' . "\n";
                }
            } elseif (array_key_exists($i, $vars)) {
                $output .= $raw . ' = ' . craft_custom_var_dump($vars[$i]) . "\n";
            } else {
                $output .= '*UNDEFINED*' . "\n";
            }
        }

        if (count($vars) > count($varNames)) {
            $counter = count($vars);
            for ($j = count($varNames); $j < $counter; $j++) {
                $output .= craft_custom_var_dump($vars[$j]) . "\n";
            }
        }
        $isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
        if ($isCli) {
            echo $output . PHP_EOL;
        } else {
            echo '<pre style="background:#222;color:#eee;padding:5px 10px;border-radius:8px;overflow:auto;font-size:13px;line-height:1.2;box-shadow:0 2px 8px #0002;">' . $output . '</pre>';
        }
    }
}

if(!function_exists('dumpJson')) {
    /**
     * Dump variable as JSON for debugging purposes.
     * @param mixed $var The variable to dump.
     */
    function dumpJson($var): void
    {

    }
}

/**
 * Custom var_dump function that supports HTML output and recursion detection.
 *
 * @param mixed $var The variable to dump.
 * @param int $indent The current indentation level.
 * @param array $references Array to track references for recursion detection.
 */
function craft_custom_var_dump($var, $indent = 0, &$references = []): string
{
    $indentation = str_repeat("  ", $indent);
    $varKey = null;
    $isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

    $ansi = function(string $text, string $color) use ($isCli): string {
        if (!$isCli) {
            return $text;
        }
        $map = [
            'gray' => "\033[0;37m",
            'magenta' => "\033[0;35m",
            'cyan' => "\033[0;36m",
            'green' => "\033[0;32m",
            'yellow' => "\033[0;33m",
            'blue' => "\033[0;34m",
            'red' => "\033[0;31m",
            'reset' => "\033[0m",
        ];
        return ($map[$color] ?? '') . $text . ($map['reset'] ?? '');
    };

    $htmlWrap = function(string $text, string $color) use ($isCli): string {
        if ($isCli) {
            return $text;
        }
        $map = [
            'gray' => '#888888',
            'magenta' => '#b14c9c',
            'cyan' => '#00aabb',
            'green' => '#2aa198',
            'yellow' => '#b58900',
            'blue' => '#268bd2',
            'red' => '#dc322f',
        ];
        $esc = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<span style="color: ' . ($map[$color] ?? '#000') . '">' . $esc . '</span>';
    };

    $colorize = (fn(string $text, string $color): string => $isCli ? $ansi($text, $color) : $htmlWrap($text, $color));
    if (is_object($var)) {
        $varKey = spl_object_id($var);
    } elseif (is_array($var)) {
        $varKey = md5(json_encode($var, JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
    if ($varKey && in_array($varKey, $references)) {
        return "{$indentation}" . $colorize("*RECURSION*", 'red') . "\n";
    }
    if ($varKey) {
        $references[] = $varKey;
    }

    $out = '';
    if (is_null($var)) {
        $out = "{$indentation}" . $colorize('NULL', 'gray') . "\n";
    } elseif (is_bool($var)) {
        $out = "{$indentation}" . $colorize('bool(' . ($var ? 'true' : 'false') . ')', 'magenta') . "\n";
    } elseif (is_int($var)) {
        $out = "{$indentation}" . $colorize('int(' . $var . ')', 'cyan') . "\n";
    } elseif (is_float($var)) {
        $out = "{$indentation}" . $colorize('float(' . $var . ')', 'cyan') . "\n";
    } elseif (is_string($var)) {
        $str = '"' . $var . '"';
        $out = "{$indentation}" . $colorize('string(' . strlen($var) . ') ', 'green') . $colorize($str, 'green') . "\n";
    } elseif (is_array($var)) {
        $out = "{$indentation}" . $colorize('array(' . count($var) . ') {', 'yellow') . "\n";
        foreach ($var as $key => $value) {
            $keyText = is_string($key) ? '"' . $key . '"' : (string)$key;
            $line = "{$indentation}  [" . $colorize($keyText, 'blue') . "]=>\n";
            $out .= $line . craft_custom_var_dump($value, $indent + 1, $references);
        }
        $out .= "{$indentation}" . $colorize('}', 'yellow') . "\n";
    } elseif (is_object($var)) {
        $className = $var::class;
        $out = "{$indentation}" . $colorize('object(' . $className . ') {', 'blue') . "\n";
        $properties = (array) $var;
        foreach ($properties as $key => $value) {
            $line = "{$indentation}  [" . $colorize((string)$key, 'blue') . "]=>\n";
            $out .= $line . craft_custom_var_dump($value, $indent + 1, $references);
        }
        $out .= "{$indentation}" . $colorize('}', 'blue') . "\n";
    } elseif (is_resource($var)) {
        $out = "{$indentation}" . $colorize('resource(' . get_resource_type($var) . ')', 'red') . "\n";
    } else {
        $out = "{$indentation}" . $colorize('unknown type', 'red') . "\n";
    }
    return $out;
}

if (!function_exists('redirect')) {
    /**
     * Redirect helper:
     * - redirect()->route('name') : Redirect to a named route.
     * - redirect($url) : Redirect to a specific URL.
     * @param string|null $url URL to redirect to.
     * @return object|void
     */
    function redirect(?string $url = null)
    {
        if ($url !== null) {
            header('Location: ' . $url);
            exit;
        }
        return new class {
            /**
             * Redirect to a named route.
             */
            public function route($name, $params = [])
            {
                $url = route($name, $params);
                header('Location: ' . $url);
                exit;
            }
            /**
             * Redirect to a specific URL.
             */
            public function to($url)
            {
                header('Location: ' . $url);
                exit;
            }
        };
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route.
     *
     * @param string $name The route name.
     * @param array $params The route parameters.
     * @return string|null The generated URL or null if the route does not exist.
     */
    function route(string $name, array $params = []): ?string
    {
        return Router::route($name, $params);
    }
}

if (!function_exists('session')) {
    /**
     * Helper function for session get/set.
     * - session($key): get session value.
     * - session($key, $value): set session value.
     *
     * @param string $key The session key.
     * @param mixed|null $value The session value.
     * @return mixed
     */
    function session(string $key, $value = null)
    {
        if ($value === null) {
            return Session::get($key);
        }
        Session::set($key, $value);
    }
}

if (!function_exists("flash")) {
    /**
     * Flash data to session.
     * @param string $key The session flash key.
     * @param mixed $value The session flash value.
     */
    function flash(string $key, $value): void
    {
        Session::flash($key, $value);
    }
}

if (!function_exists('getFlash')) {
    /**
     * Get flash data from session.
     * @return mixed|null
     */
    function getFlash(string $key)
    {
        return Session::getFlash($key);
    }
}

if (!function_exists('getBaseUrl')) {
    /**
     * Get the dynamic base URL of the application.
     */
    function getBaseUrl(): string
    {
        $scheme =
            ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null)
            ?: ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http");

        $host = $_SERVER['HTTP_X_FORWARDED_HOST']
            ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        return $scheme . '://' . $host . ($basePath === '' ? '/' : $basePath . '/');
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a hidden input field with CSRF token.
     */
    function csrf_field(): string
    {
        $token = TokenGenerator::csrf_generate();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('view')) {
    /**
     * Render a view file with optional data.
     * @param string $view The view file name (without .php).
     * @param array $data The data to pass to the view.
     * @return string The rendered view content.
     * @throws Exception If the view file does not exist.
     */
    function view(string $view, array $data = []): string
    {
        return View::render($view, $data);
    }
}

if (!function_exists('df_config')) {
    /**
     * DLight configuration helper.
     *
     * - df_config()               → get all config
     * - df_config('app.name')     → get specific config value
     * @param string|null $key The config key (dot notation for nested).
     * @return mixed|null The config value or null if not found.
     */
    function df_config(?string $key = null)
    {
        static $configs = null;

        if ($configs === null) {
            if (!defined('ROOT_DIR')) {
                throw new \Exception("ROOT_DIR is not defined.");
            }

            $configPath = rtrim(ROOT_DIR, '/\\') . '/config/';
            $configs = [];

            foreach (glob($configPath . '*.php') as $file) {
                $name = basename($file, '.php');
                $configs[$name] = include $file;
            }
        }

        if ($key === null) {
            return $configs;
        }

        $segments = explode('.', $key);
        $value = $configs;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

/**
 * Set CORS headers for API responses.
 */
function setApiCorsHeaders(): void
{
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Vary: Origin');
    } else {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate a URL for an asset.
     * @param string $path The asset path relative to the public directory.
     * @return string The full URL to the asset.
     */
    function asset(string $path = ''): string
    {
        return getBaseUrl() . ltrim($path, '/');
    }
}
