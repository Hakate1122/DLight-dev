<?php

namespace DLight\Application;

use Exception;

/**
 * **View Rendering**
 *
 *  View class for rendering views with data and redirecting to a given URL or route name.
 * 
 * **Note:** 
 * - Default view engine is basic PHP. To use other view engines, set `SUPPORT_VIEW_ENGINE` to `enable` in the `.env` file and configure the view engine in `config/view.php`.
 * - Default view path is `ROOT_DIR/resource/view/`. You can customize it via constructor or config file.
 */
class View
{
    /**
     * @var string The path to the view files.
     */
    private ?string $viewPath;
    private ?string $engine;
    private $engineInstance;

    /**
     * Constructor to set the view path.
     * @param string|null $viewPath The path to the view files. If null, uses config or `default`.
     *                    - Config file: ROOT_DIR/config/view.php
     *                    - Default: ROOT_DIR/resource/view/
     * @throws Exception If the specified view engine class does not exist.
     */
    public function __construct(?string $viewPath = null)
    {
        $configPath = ROOT_DIR . 'config/view.php';
        $config = file_exists($configPath) ? require $configPath : [];
        $viewPath ??= $config['view_path'] ?? ROOT_DIR . 'resource/view/';
        $this->viewPath = rtrim($viewPath, '/');
        $this->engine = $config['engine'] ?? 'php';

        if (
            function_exists('env') && env('SUPPORT_VIEW_ENGINE', 'disable') === 'enable'
            && $this->engine !== 'php'
            && isset($config['drives'][$this->engine]['class'])
        ) {
            $class = $config['drives'][$this->engine]['class'];
            $options = $config['drives'][$this->engine]['options'] ?? [];
            if (class_exists($class)) {
                $this->engineInstance = new $class($this->viewPath, $options);
            } else {
                throw new Exception("View engine class not found: $class");
            }
        }
    }

    /**
     * Render a view file with optional data.
     *
     * @param string $view The view file to render, relative to the view path.
     * @param array $data Data to be passed to the view.
     * @param string|null $viewPath The custom view path to use. If null, uses the instance's view path.
     * @return string
     */
    public static function render(string $view, ?array $data = [], ?string $viewPath = null)
    {
        $instance = new self($viewPath);
        $data['title'] ??= '<title>Default Title</title>';
        return $instance->view($view, $data);
    }

    /**
     * Render a view file with optional data.
     * 
     * @param string $view The view file to render, relative to the view path.
     * @param array $data Data to be passed to the view.
     * @return string
     */
    public function view(string $view, ?array $data = [])
    {
        if ($this->engine === 'php' || !$this->engineInstance) {
            $view = str_replace(['..', '\\'], '', $view);
            if (str_contains($view, '.')) {
                $view = str_replace('.', '/', $view);
            }
            $extensions = ['.php', '.blade.php', '.twig', '.tpl', '.html', '.htm'];
            $filePath = null;
            foreach ($extensions as $ext) {
                $tryPath = $this->viewPath . '/' . $view . $ext;
                if (file_exists($tryPath)) {
                    $filePath = $tryPath;
                    break;
                }
            }
            if (!$filePath) {
                throw new Exception("View file not found: " . $this->viewPath . '/' . $view . '.[php/html/htm]');
            }
            extract($data);
            ob_start();
            require $filePath;
            return ob_get_clean();
        }
        if (method_exists($this->engineInstance, 'render')) {
            return $this->engineInstance->render($view, $data);
        }
        throw new Exception("View engine does not support render method");
    }

    /**
     * Abort the request with a given status code and optional custom error template.
     * @param int $statusCode The HTTP status code to abort with.
     */
    public static function abort(int $statusCode): bool|int
    {
        return http_response_code($statusCode);
    }

    /**
     * Include on default view file path with optional data.
     * @param string $view The partial view name (without .php)
     * @param array $data Optional data to pass to the partial
     * @return string
     */
    public static function include(string $view, array $data = [])
    {
        $instance = new self();
        return $instance->view($view, $data);
    }
}
