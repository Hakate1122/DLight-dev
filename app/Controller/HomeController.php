<?php

declare(strict_types=1);

namespace App\Controller;

use DLight\Application\View;

class HomeController
{
    #[\DLight\Attribute\Route(path: '/', methods: ['GET'], name: 'home')]
    public function home()
    {
        return View::render('home', [
            'phpVersion' => phpversion(),
            'dlightVersion' => \DLight\Application\App::version,
            'os' => PHP_OS,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'memory' => memory_get_usage(true),
            'memoryLimit' => ini_get('memory_limit')
        ]);
    }
}
