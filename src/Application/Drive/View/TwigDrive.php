<?php
// not available yet
namespace DLight\Application\Drive\View;

use DLight\Application\Interfaces\ViewEngine;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use \Twig\TwigFunction;

use function is_array;

/**
 * TwigDrive class for rendering Twig templates.
 */
class TwigDrive implements ViewEngine
{
    protected $twig;

    public function __construct($viewPath, $options = [])
    {
        $loader = new FilesystemLoader($viewPath);
        $this->twig = new Environment($loader, $options);

        // Đăng ký các function từ config
        if (!empty($options['functions']) && is_array($options['functions'])) {
            foreach ($options['functions'] as $name => $callable) {
                $this->twig->addFunction(new TwigFunction($name, $callable));
            }
        }
    }

    public function render(string $template, array $data = []): string
    {
        if (!str_ends_with($template, '.twig')) {
            $template .= '.twig';
        }
        return $this->twig->render($template, $data);
    }
}