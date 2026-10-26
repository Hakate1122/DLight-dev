<?php

declare(strict_types=1);

use DLight\Application\Router;
use PHPUnit\Framework\TestCase;

class RouteMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION['dlight_user']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/h';
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    public function testHomeRouteWithNamedMiddlewareDoesNotCrash(): void
    {
        require_once __DIR__ . '/../../app/Router/web/web.php';

        $_SERVER['REQUEST_URI'] = '/h';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        Router::run();
        $output = ob_get_clean();

        $this->assertStringContainsString('Unauthorized Access', $output);
        $this->assertSame(401, http_response_code());
    }
}
