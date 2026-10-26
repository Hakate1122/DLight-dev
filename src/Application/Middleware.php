<?php

namespace DLight\Application;

use function call_user_func;

/**
 * **Middleware Handler**
 *
 * This class handles middleware functionality for the application.
 * It provides a simple way to add middleware to routes and groups.
 */
class Middleware
{
    /**
     * @var array $middlewares Stores registered middlewares
     */
    private static $middlewares = [];

    /**
     * Allows middleware instances to be used as callable objects in routes.
     *
     * Example:
     *   $auth = new UserAuthencation();
     *   $auth->sign();
     *   $router->sign('GET /h', fn() => ..., [$auth]);
     */
    public function __invoke(array $context = []): mixed
    {
        $name = $this->resolveName();
        return $name !== null ? self::run($name, $context) : null;
    }

    /**
     * Register a middleware
     * @param string $name Middleware name
     * @param callable $callback Middleware callback
     */
    public static function register(?string $name = null, ?callable $callback = null): void
    {
        if ($name !== null && $callback !== null) {
            self::$middlewares[$name] = $callback;
        }
    }

    /**
     * Get a middleware by name
     * @param string $name Middleware name
     */
    public static function get(string $name): ?callable
    {
        return self::$middlewares[$name] ?? null;
    }

    /**
     * Check if middleware exists
     * @param string $name Middleware name
     */
    public static function exists(string $name): bool
    {
        return isset(self::$middlewares[$name]);
    }

    /**
     * Run a middleware
     * @param string $name Middleware name
     * @param array $context Context data
     * @return mixed
     */
    public static function run(string $name, array $context = [])
    {
        if (self::exists($name)) {
            return call_user_func(self::$middlewares[$name], $context);
        }
        return null;
    }

    protected function resolveName(): ?string
    {
        $class = static::class;
        $name = strtolower(class_basename($class));

        if (self::exists($name)) {
            return $name;
        }

        foreach (array_keys(self::$middlewares) as $key) {
            $target = strtolower(class_basename($class));
            if (strtolower($key) === $target || strtolower($key) === strtolower($class)) {
                return $key;
            }
        }

        return null;
    }
}

if (!function_exists('class_basename')) {
    function class_basename(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts) ?: $class;
    }
}
