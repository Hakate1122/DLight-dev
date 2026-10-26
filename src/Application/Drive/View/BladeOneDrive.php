<?php

namespace DLight\Application\Drive\View;

use DLight\Application\Interfaces\ViewEngine;
use eftec\bladeone\BladeOne;

use function defined;

/**
 * BladeOneDrive View Engine using BladeOne templating engine.
 */
class BladeOneDrive implements ViewEngine
{
    protected $blade;

    public function __construct($viewPath, $options = [])
    {
        $useCache = $options['cache'] ?? true;

        $cachePath = null;
        if ($useCache) {
            $cachePath = $options['cache_path'] ?? $options['compiled_path'] ?? (defined('INDEX_DIR') ? INDEX_DIR . 'cache/' : null);
        } else {
            $cachePath = null;
        }

        if ($cachePath) {
            if (!is_dir($cachePath) && false === @mkdir($cachePath, 0777, true)) {
                throw new \Exception("BladeOneDrive: Unable to create cache directory: $cachePath");
            }
            if (!is_writable($cachePath)) {
                @chmod($cachePath, 0777);
            }
        }

        $mode = $cachePath ? BladeOne::MODE_AUTO : BladeOne::MODE_SLOW;

        $this->blade = new BladeOne($viewPath, $cachePath, $mode);
    }

    public function render(string $template, array $data = []): string
    {
        return $this->blade->run($template, $data);
    }
}
