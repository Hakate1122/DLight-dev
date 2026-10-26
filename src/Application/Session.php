<?php

namespace DLight\Application;

use function array_key_exists;

/**
 * **Session Management**
 * 
 * This class provides methods to manage user sessions, including basic
 * session data, flash messages, and security features like session
 * regeneration and destruction.
 */
final class Session
{
    private const FLASH_KEY = '_flash';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::ageFlash();
    }

    /* ----- Basic Session Methods ----- */

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /* ----- Flash Message Methods ----- */

    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[self::FLASH_KEY]['new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[self::FLASH_KEY]['old'][$key] ?? $default;
    }

    private static function ageFlash(): void
    {
        
        if (!isset($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = ['old' => [], 'new' => []];
            return;
        }

        $_SESSION[self::FLASH_KEY]['old'] =
            $_SESSION[self::FLASH_KEY]['new'] ?? [];

        $_SESSION[self::FLASH_KEY]['new'] = [];
    }

    /* ----- Convenience Flash Methods ----- */

    public static function error(string $message): void
    {
        self::flash('error', $message);
    }

    public static function success(string $message): void
    {
        self::flash('success', $message);
    }

    public static function getError(): ?string
    {
        return self::getFlash('error');
    }

    public static function getSuccess(): ?string
    {
        return self::getFlash('success');
    }

    /* ----- Security Methods ----- */

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly']]
            );
        }

        session_destroy();
    }
}
