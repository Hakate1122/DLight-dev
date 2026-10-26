<?php

namespace DLight\Application;

use function in_array;

/**
 * **HTTP Request Handler**
 *
 * Handles method, URI, GET, POST, JSON, headers, cookies and server info.
 */
class Request
{
    protected string $method;
    protected string $uri;
    protected array $query;
    protected array $body;
    protected array $headers;
    protected array $cookies;
    protected array $server;

    public function __construct()
    {
        $this->server = $_SERVER;
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '';

        $this->query = $_GET ?? [];
        $this->cookies = $_COOKIE ?? [];

        $this->headers = $this->normalizeHeaders(
            function_exists('getallheaders') ? getallheaders() : []
        );

        $this->body = $this->parseBody();
    }

    /**
     * Normalize header keys to lowercase for consistent access.
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }
        return $normalized;
    }

    /**
     * Parse request body based on Content-Type.
     */
    protected function parseBody(): array
    {
        $contentType = strtolower($this->headers['content-type'] ?? '');

        if (str_contains($contentType, 'application/json')) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return $_POST ?? [];
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            return $_POST ?? [];
        }

        $raw = file_get_contents('php://input');
        if (!in_array($raw, ['', '0', false], true)) {
            return ['_raw' => $raw];
        }

        return [];
    }

    // -------------------------
    // Getters
    // -------------------------

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function getBodyParams(): array
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $key, $default = null)
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    public function getServer(): array
    {
        return $this->server;
    }

    // -------------------------
    // Helpers
    // -------------------------

    public function input(string $key, $default = null)
    {
        return $this->body[$key]
            ?? $this->query[$key]
            ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }
}
