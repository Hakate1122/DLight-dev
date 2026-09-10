# DLight PHP v8

**DLight** is a minimalist PHP framework designed for small projects, learning, or as a foundation for personal framework development.

## Features

- **Routing**: Flexible routing system, supporting groups, middleware, named routes, RESTful and API routes.
- **View Engine**: Supports multiple view engines, default is PHP, easily extendable to Blade, Twig, etc.
- **Session & Flash**: Manage sessions, convenient flash messages for one-time notifications.
- **Database Layer**: 
  - Supports multiple database systems (MySQL, SQLite).
  - Supports multiple drivers (mysqli, sqlite3, PDO).
  - Adapter pattern for connections and queries.
  - Query Builder generates dynamic SQL, separated from the adapter.
  - Record Mapper CRUD for each table.
- **Error & Exception Handling**: Error reporting, logging, runtime, parsing, separate exceptions.
- **Helper**: Many utility functions for debugging, var_dump, helper function.
- **Environment Configuration**: Read environment variables from `.env`, supported by [TinyEnv](https://github.com/datahihi1/tiny-env.git).
- **Security**: Supports secure sessions, maintenance mode, security headers, tokens generation (easily extendable).
- **Mailing**: Simple SMTP mail sending with configuration options.
- **WebSocket**: Basic WebSocket server implementation for real-time communication.

## Basic Usage

**Routing:**
```php
$router = new DLight\Application\Router();
$router->sign('GET /', [App\Controller\HomeController::class, 'index']); // Single route
$router->signApi('GET /data', [App\Controller\Api\DataController::class, 'fetch']); // API route
$router->group('/api')->action(function($router) { // Grouped routes
    $router->sign('GET /users', [App\Controller\Api\UserController::class, 'list']);
    $router->sign('GET /users/{id}', [App\Controller\Api\UserController::class, 'show']);
});
$router->sign('GET|POST /demo', [App\Controller\DemoController::class, 'index']); // Multiple methods
$router->all('/contact', [App\Controller\ContactController::class, 'index']); // All methods
```

**Controller:**

```php
class HomeController {
    public function index() {
        return DLight\Application\View::render('home', ['message' => 'Hello!']);
    }
}
```

**Database:**
```php
$test = new User();
$allUsers = $test->all(); // Get all users with Mapper

DB::table('users')->where('id', 1)->first(); // Query Builder
```

**View:**
```php
echo DLight\Application\View::render('home', ['message' => 'Hello!']);
```

**Session & Flash:**
```php
DLight\Application\Session::flash('msg', 'Success!'); // Set flash message
echo DLight\Application\Session::getFlash('msg'); // Get and clear flash message
```

**Hash:**

```php
$hashedPassword = DLight\Application\Hash::default('password123'); // Hash password
$isValid = DLight\Application\Hash::verify('password123', $hashedPassword); // Verify password
```

**Mail:**
```php
$mail = new DLight\Application\Mail();
$mail->to('recipient@example.com')
    ->cc('')
    ->bcc('')
    ->subject('Test Email')
    ->body('This is a test email.')
    ->addAttachment('/path/to/file.txt')
    ->send();
```

## System Requirements
- PHP 8.0 to 8.6.0beta2 (Update at 12:22 2026-08-31 UTC+7) - This framework has been testing
- Composer
- Web Server (Apache, Nginx, etc.)

## Installation

```bash
git clone https://github.com/datahihi1/DLight.git my-project
cd my-project
composer install
php dli -s
```

Then open your browser and navigate to `http://localhost:8000`.