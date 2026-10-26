<?php

namespace DLight\Reports\Render;

use DLight\Reports\Interface\RenderInterface;

use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * HTML Renderer for error and exception reporting.
 */
class Html implements RenderInterface
{
    private static $configs = [
        'error' => ['color' => '#7c3aed', 'icon' => 'Oops, an error occurred!', 'title' => 'Error', 'gradient' => 'linear-gradient(135deg, #8b5cf6, #7c3aed)'],
        'exception' => ['color' => '#dc2626', 'icon' => 'Oops, an uncaught exception detected!', 'title' => 'Exception', 'gradient' => 'linear-gradient(135deg, #ef4444, #dc2626)'],
        'parse' => ['color' => '#2563eb', 'icon' => 'Oops, a parsing bug detected!', 'title' => 'Parse', 'gradient' => 'linear-gradient(135deg, #3b82f6, #2563eb)'],
        'runtime' => ['color' => '#d97706', 'icon' => 'Oops, application shutdown detected!', 'title' => 'Runtime', 'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)'],
    ];

    public function render(string $type, string $message, string $file, int $line, array $context = []): void
    {
        /** Check DLight version */
        $dfver = class_exists(\DLight\Application\App::class)
            ? \DLight\Application\App::version
            : 'Non-DLight Environment';

        $config = self::$configs[$type] ?? self::$configs['error'];

        while (ob_get_level())
            ob_end_clean();

        if(!headers_sent()){
            http_response_code(500);
        }

        $favColor = $config['color'] ?? '#7c3aed';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="12" fill="' . $favColor . '"/><text x="50%" y="55%" font-size="36" text-anchor="middle" fill="#ffffff" font-family="Arial,Helvetica,sans-serif" font-weight="700">!</text></svg>';
        $favicon = 'data:image/svg+xml;utf8,' . rawurlencode($svg);
        ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= $config['title'] ?>: <?= htmlspecialchars($message) ?></title>

            <link rel="icon" href="<?= $favicon ?>" type="image/svg+xml">
            <meta name="theme-color" content="<?= $favColor ?>">

            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box
                }

                :root {
                    --bg: #f8f9fa;
                    --text: #333;
                    --container-bg: #ffffff;
                    --code-bg: #ffffff;
                    --line-number-bg: #f8f9fa;
                    --muted: #6b7280;
                    --subtle-bg: #f3f4f6;
                    --border: #e5e7eb;
                    --shadow: 0 2px 10px rgba(0, 0, 0, .1);
                }

                .dark {
                    --bg: #1e1e1e;
                    --text: #d4d4d4;
                    --container-bg: #252526;
                    --code-bg: #1e1e1e;
                    --line-number-bg: #2d2d2d;
                    --muted: #9a9a9a;
                    --subtle-bg: #2a2a2a;
                    --border: #2f2f2f;
                    --shadow: none;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: var(--bg);
                    color: var(--text);
                    line-height: 1.5;
                    min-width: 320px
                }

                .container {
                    margin: 20px;
                    background: var(--container-bg);
                    border-radius: 8px;
                    box-shadow: var(--shadow);
                    overflow: hidden
                }

                @media(max-width:600px) {
                    .container {
                        margin: 0;
                        border-radius: 0;
                        width: 100vw;
                        min-width: 320px;
                        max-width: 100vw;
                        box-shadow: none
                    }
                }

                .header {
                    background: <?= $config['gradient'] ?>;
                    color: white;
                    padding: 15px 20px;
                    font-weight: 500;
                    display: flex;
                    justify-content: space-between;
                    align-items: center
                }

                .tabs {
                    display: flex;
                    gap: 8px
                }

                .tab {
                    background: rgba(255, 255, 255, .2);
                    padding: 4px 12px;
                    border-radius: 4px;
                    font-size: 12px;
                    cursor: pointer
                }

                .tab.active {
                    background: rgba(255, 255, 255, .3)
                }

                .theme-toggle {
                    background: rgba(255,255,255,0.12);
                    padding: 4px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    cursor: pointer;
                    margin-left: 8px;
                    border: none;
                    color: inherit;
                }

                .theme-transition .container,
                .theme-transition .header,
                .theme-transition .title,
                .theme-transition .code-viewer,
                .theme-transition .trace-viewer,
                .theme-transition .line-number,
                .theme-transition .line-content,
                .theme-transition body,
                .theme-transition html {
                    transition: background-color 300ms ease, color 300ms ease, border-color 300ms ease, box-shadow 300ms ease;
                }

                .theme-toggle { transition: transform 180ms ease, background-color 200ms ease; }
                .theme-toggle:active { transform: scale(0.98); }

                .title {
                    padding: 20px;
                    border-bottom: 1px solid var(--border)
                }

                .title h2 {
                    color:
                        <?= $config['color'] ?>
                    ;
                    font-size: 18px;
                    font-weight: 600;
                    display: flex;
                    gap: 8px;
                    align-items: center
                }

                .message {
                    color: var(--muted);
                    font-size: 14px
                }

                .code-container {
                    background: var(--container-bg);
                    border-top: 1px solid var(--border)
                }

                .code-header {
                    background: var(--subtle-bg);
                    padding: 12px 20px;
                    border-bottom: 1px solid var(--border);
                    display: flex;
                    justify-content: space-between;
                    font-size: 12px;
                    color: var(--muted)
                }

                .code-viewer {
                    font-family: Monaco, Consolas, monospace;
                    font-size: 13px;
                    line-height: 1.6;
                    background: var(--code-bg);
                    overflow-x: auto;
                    padding: 16px
                }

                .code-line {
                    display: flex
                }

                .line-number {
                    background: var(--line-number-bg);
                    color: #9ca3af;
                    padding: 0 12px;
                    text-align: right;
                    min-width: 60px;
                    border-right: 1px solid var(--border);
                    font-size: 12px
                }

                .line-content {
                    padding: 0 16px;
                    flex: 1;
                    white-space: pre
                }

                .highlight-line {
                    background-color: <?= $config['color'] ?>20 !important; /* 20 = ~12% opacity */
                    color: <?= $config['color'] ?>;
                    font-weight: 600;
                    border-left: 4px solid <?= $config['color'] ?>;
                    padding-left: 12px;
                }

                .code-viewer .code-line:not(.highlight-line) .line-content,
                .code-viewer .code-line:not(.highlight-line) .line-number {
                    opacity: 0.6;
                }

                .php-keyword {
                    color: #0ea5e9
                }

                .php-string {
                    color: #059669
                }

                .php-comment {
                    color: var(--muted);
                    font-style: italic
                }

                .php-variable {
                    color: #d97706
                }

                .php-function {
                    color: #7c3aed
                }

                .php-constant {
                    color: #dc2626
                }

                .php-number {
                    color: #ea580c
                }

                .php-operator {
                    color: #374151
                }

                .trace-header {
                    background: var(--subtle-bg);
                    padding: 12px 20px;
                    border-top: 1px solid var(--border);
                    border-bottom: 1px solid var(--border);
                    display: flex;
                    justify-content: space-between;
                    font-size: 12px;
                    color: var(--muted);
                }

                .trace-viewer {
                    font-family: Monaco, Consolas, monospace;
                    font-size: 12px;
                    line-height: 1.6;
                    background: var(--code-bg);
                    overflow-x: auto;
                    padding: 12px 16px;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <span><?= $config['icon'] ?></span>
                    <div class="tabs">
                        <span class="tab active" onclick="show('full', event)">Full</span>
                        <span class="tab" onclick="show('raw', event)">Raw</span>
                        <button id="themeToggle" class="theme-toggle" onclick="toggleTheme(event)">Theme</button>
                    </div>
                </div>

                <div id="full" class="content">
                    <div class="title">
                        <h2><?= ucfirst($type) ?></h2>
                        <div class="message"><?= nl2br(htmlspecialchars($message)) ?></div>
                        <div>DLight: <?= htmlspecialchars($dfver) ?> | PHP: <?= htmlspecialchars(PHP_VERSION) ?></div>
                    </div>
                    <?php if ($file && file_exists($file)): ?>
                        <div class="code-container">
                            <div class="code-header">
                                <span><?= htmlspecialchars($file) ?></span>
                                <span>Line <?= $line ?></span>
                            </div>
                            <div class="code-viewer">
                                <?php
                                $lines = file($file);
                                $start = max($line - 6, 0);
                                $end = min($line + 5, count($lines));
                                for ($i = $start; $i < $end; $i++):
                                    $num = $i + 1;
                                    $code = rtrim($lines[$i]);
                                    ?>
                                    <div class="code-line <?= $num == $line ? 'highlight-line' : '' ?>">
                                        <div class="line-number"><?= $num ?></div>
                                        <div class="line-content"><?= $this->highlight($code) ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($context['trace'])): ?>
                        <div class="code-container">
                            <div class="trace-header">
                                <span>Stack trace</span>
                                <span><?= count(is_array($context['trace']) ? $context['trace'] : []) ?> frames</span>
                            </div>
                            <div class="trace-viewer">
                                <?php
                                $frames = $this->normalizeTrace($context['trace']);
                                foreach ($frames as $index => $frameLine): ?>
                                    <div class="code-line">
                                        <span class="line-number">#<?= $index ?></span>
                                        <span class="line-content"><?= htmlspecialchars($frameLine) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                $raw = "Type: $type\nMessage: $message\nFile: $file\nLine: $line\nTime: " . date('c');
                if (!empty($context['trace'])) {
                    $raw .= "\nTrace:\n" . implode("\n", $this->normalizeTrace($context['trace']));
                }
                ?>

                <div id="raw" class="content"
                    style="display:none;padding:20px;font-family:monospace;background:var(--bg);white-space:pre-wrap;">
                    <?= htmlspecialchars($raw) ?>
                </div>

                <script>
                    (function(){
                        var favColor = '<?= $favColor ?>';

                        function show(id, evt) {
                            document.querySelectorAll('.content').forEach(el => el.style.display = 'none');
                            document.getElementById(id).style.display = 'block';
                            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                            if (evt && evt.target) evt.target.classList.add('active');
                        }

                        window.show = show;

                        function applyTheme(isDark){
                            if(isDark) document.documentElement.classList.add('dark');
                            else document.documentElement.classList.remove('dark');
                            var meta = document.querySelector('meta[name="theme-color"]');
                            if(meta) meta.setAttribute('content', favColor);
                            var btn = document.getElementById('themeToggle');
                            if(btn) btn.textContent = isDark ? 'Light' : 'Dark';
                        }

                        function toggleTheme(evt){
                            document.documentElement.classList.add('theme-transition');

                            var isDark = document.documentElement.classList.toggle('dark');
                            localStorage.setItem('dlight-theme', isDark ? 'dark' : 'light');
                            applyTheme(isDark);

                            setTimeout(function(){
                                document.documentElement.classList.remove('theme-transition');
                            }, 400);

                            if(evt && evt.stopPropagation) evt.stopPropagation();
                        }

                        window.toggleTheme = toggleTheme;

                        var stored = localStorage.getItem('dlight-theme');
                        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                        var useDark = stored ? (stored === 'dark') : prefersDark;
                        applyTheme(useDark);
                    })();
                </script>
            </div>
        </body>

        </html>
        <?php
        exit;
    }

    private function highlight(string $code): string
    {
        $leadingSpace = '';
        if (preg_match('/^(\s+)/', $code, $matches)) {
            $leadingSpace = $matches[1];
        }
        $code = trim($code);

        if ($code === '' || $code === '0') {
            return $leadingSpace;
        }

        $code = htmlspecialchars($code, ENT_NOQUOTES);

        // Handle comments first (to avoid highlighting inside comments)
        $code = preg_replace('/(\/\/.*$)/', '<span class="php-comment">$1</span>', $code);
        $code = preg_replace('/(\/\*.*?\*\/)/s', '<span class="php-comment">$1</span>', $code);

        // Handle strings (single and double quotes)
        $code = preg_replace('/"([^"\\\\]*(\\\\.[^"\\\\]*)*)"/', '<span class="php-string">"$1"</span>', $code);
        $code = preg_replace("/'([^'\\\\]*(\\\\.[^'\\\\]*)*)'/", '<span class="php-string">\'$1\'</span>', $code);

        // Keywords
        $keywords = [
            'abstract',
            'and',
            'array',
            'as',
            'break',
            'callable',
            'case',
            'catch',
            'class',
            'clone',
            'const',
            'continue',
            'declare',
            'default',
            'die',
            'do',
            'echo',
            'else',
            'elseif',
            'empty',
            'enddeclare',
            'endfor',
            'endforeach',
            'endif',
            'endswitch',
            'endwhile',
            'eval',
            'exit',
            'extends',
            'final',
            'finally',
            'for',
            'foreach',
            'function',
            'false',
            'global',
            'goto',
            'get',
            'if',
            'implements',
            'include',
            'include_once',
            'instanceof',
            'insteadof',
            'interface',
            'isset',
            'list',
            'namespace',
            'new',
            'null',
            'or',
            'print',
            'private',
            'protected',
            'public',
            'require',
            'require_once',
            'return',
            'static',
            'switch',
            'throw',
            'trait',
            'try',
            'true',
            'unset',
            'use',
            'var',
            'while',
            'xor',
            'yield',
        ];

        foreach ($keywords as $keyword) {
            $code = preg_replace('/\b(' . preg_quote($keyword) . ')\b(?![^<]*>)/', '<span class="php-keyword">$1</span>', $code);
        }

        # Variables (but not inside already highlighted content)
        $code = preg_replace('/(\$[a-zA-Z_]\w*)(?![^<]*>)/', '<span class="php-variable">$1</span>', $code);

        # Object operators and array access
        $code = preg_replace('/(-&gt;)/', '<span class="php-operator">-></span>', $code);
        $code = preg_replace('/(::)/', '<span class="php-operator">::</span>', $code);

        # Numbers
        $code = preg_replace('/\b(\d+\.?\d*)\b(?![^<]*>)/', '<span class="php-number">$1</span>', $code);

        # Function calls (word followed by opening parenthesis, but not keywords)
        $code = preg_replace('/\b([a-zA-Z_]\w*)\s*(?=\()(?![^<]*>)(?!.*<span class="php-keyword">)/', '<span class="php-function">$1</span>', $code);

        # Constants (all caps words)
        $code = preg_replace('/\b([A-Z_][A-Z0-9_]{2,})\b(?![^<]*>)/', '<span class="php-constant">$1</span>', $code);

        # Operators
        $operators = [
            '=',
            '+',
            '-',
            '*',
            '/',
            '%',
            '==',
            '!=',
            '&lt;',
            '&gt;',
            '&lt;=',
            '&gt;=',
            '&amp;&amp;',
            '||',
            '!',
            '&amp;',
            '|',
            '^',
            '&lt;&lt;',
            '&gt;&gt;'
        ];
        foreach ($operators as $op) {
            $pattern = '/(' . preg_quote($op, '/') . ')(?![^<]*>)/';
            $code = preg_replace($pattern, '<span class="php-operator">$1</span>', $code);
        }

        return $leadingSpace . $code;
    }

    /**
     * Normalize a trace (array or string) into an array of readable lines.
     *
     * @param mixed $trace
     * @return array<int,string>
     */
    private function normalizeTrace($trace): array
    {
        if (is_string($trace)) {
            return preg_split('/\r\n|\r|\n/', $trace) ?: [];
        }

        if (!is_array($trace)) {
            return [];
        }

        $frames = array_values(array_reverse($trace));
        $lines = [];

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = $frame['line'] ?? '-';
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            $typeSep = $frame['type'] ?? '';

            $call = $function;
            if ($class !== '') {
                $call = $class . $typeSep . $call;
            }

            $lines[] = sprintf('%s(%s): %s', $file, $line, $call);
        }

        return $lines;
    }
}
