<?php

namespace DLight\Command;

use DLight\Application\App;

class Add
{
	private static function studly(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		$value = str_replace(['-', '_'], ' ', $value);
		$value = preg_replace('/\s+/', ' ', (string) $value);
		$value = ucwords(strtolower((string) $value));
		return str_replace(' ', '', $value);
	}

	/**
	 * PHP class name from --name: snake/kebab → Studly; already PascalCase/camelCase tokens stay readable.
	 */
	private static function modelClassNameFromArg(string $raw): string
	{
		$raw = preg_replace('/[^A-Za-z0-9_]/', '', $raw);
		if ($raw === '') {
			return '';
		}
		if (str_contains($raw, '_') || str_contains($raw, '-')) {
			return self::studly(str_replace('-', '_', $raw));
		}
		if (preg_match('/^[A-Z0-9_]+$/', $raw) && preg_match('/[A-Z]/', $raw) && !preg_match('/[a-z]/', $raw)) {
			return ucfirst(strtolower($raw));
		}

		return ucfirst($raw);
	}

	/**
	 * Generic add handler for `php dli add <type> --name=Name`
	 */
	public static function handle()
	{
		return function ($argv = []) {
			$type = $argv[2] ?? null;

			$opts = self::parseOptions($argv, 2);
			if ($type === null || str_starts_with((string) $type, '-') || isset($opts['help']) || isset($opts['h'])) {
				(Core::helpAdd())();
				return;
			}

			$type = strtolower($type);
			switch ($type) {
				case 'controller':
				case 'ctrl':
					(self::controller())($argv);
					break;
				case 'model':
				case 'mdl':
					(self::model())($argv);
					break;
				case 'view':
				case 'vw':
					(self::view())($argv);
					break;
				case 'command':
				case 'cmd':
					(self::command())($argv);
					break;
				case 'middleware':
				case 'mdw':
					(self::middleware())($argv);
					break;
				case 'mail':
					(self::mail())($argv);
					break;
				default:
					echo cli_red("Unknown add type: $type\n");
			}
		};
	}

	/**
	 * Handler for `add:controller` or `add controller`
	 */
	public static function controller()
	{
		return function ($argv = []) {
			$start = ($argv[1] === 'add') ? 3 : 2;
			$opts = self::parseOptions($argv, $start);

			$name = $opts['name'] ?? $opts['n'] ?? null;
			if (!$name) {
				echo cli_red("Please provide a name: --name=MyController\n");
				return;
			}

			$raw = trim((string) $name);
			$raw = str_replace('\\', '/', $raw);
			$raw = trim($raw, '/');
			$raw = preg_replace('/\.php$/i', '', $raw);

			$pathParts = array_values(array_filter(explode('/', (string) $raw), static fn($p) => $p !== ''));
			if (count($pathParts) === 0) {
				echo cli_red("Invalid controller name: --name=MyController\n");
				return;
			}

			$classPart = array_pop($pathParts);
			$dirParts = $pathParts;

			$dirParts = array_values(array_filter(array_map(
				static fn($p) => preg_replace('/[^A-Za-z0-9_]/', '', $p),
				$dirParts
			), static fn($p) => $p !== ''));

			$name = preg_replace('/[^A-Za-z0-9_]/', '', $classPart);
			$name = self::studly($name);
			if ($name === '') {
				echo cli_red("Invalid controller class name: --name=MyController\n");
				return;
			}
			if (!str_ends_with($name, 'Controller')) {
				$name .= 'Controller';
			}

			$dir = __DIR__ . '/../../app/Controller' . ($dirParts === [] ? ('') : '/' . implode('/', $dirParts));
			if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
				echo cli_red("Failed to create directory: $dir\n");
				return;
			}

			$file = $dir . '/' . $name . '.php';
			$rel = 'app/Controller' . ($dirParts === [] ? ('') : '/' . implode('/', $dirParts)) . "/$name.php";
			$existedBefore = file_exists($file);
			if ($existedBefore && !self::wantsForce($opts)) {
				echo cli_red("Controller already exists: $rel\n");
				return;
			}

			$namespace = 'App\\Controller' . ($dirParts === [] ? ('') : '\\' . implode('\\', $dirParts));
			$apiCrud = (bool) ($opts['api-crud'] ?? false);
			$crud = (bool) ($opts['crud'] ?? false);

			if ($apiCrud) {
				$template = "<?php\n\nnamespace $namespace;\n\nclass $name\n{\n    public function index()\n    {\n        header('Content-Type: application/json');\n        echo json_encode(['data' => []]);\n    }\n\n    public function store()\n    {\n        header('Content-Type: application/json');\n        // TODO: validate input and persist\n        echo json_encode(['message' => 'created'], JSON_UNESCAPED_UNICODE);\n    }\n\n    public function show(\$id)\n    {\n        header('Content-Type: application/json');\n        echo json_encode(['id' => \$id]);\n    }\n\n    public function update(\$id)\n    {\n        header('Content-Type: application/json');\n        // TODO: validate input and update\n        echo json_encode(['message' => 'updated', 'id' => \$id], JSON_UNESCAPED_UNICODE);\n    }\n\n    public function destroy(\$id)\n    {\n        header('Content-Type: application/json');\n        // TODO: delete resource\n        echo json_encode(['message' => 'deleted', 'id' => \$id], JSON_UNESCAPED_UNICODE);\n    }\n}\n";
			} elseif ($crud) {
				$template = "<?php\n\nnamespace $namespace;\n\nclass $name\n{\n    public function index()\n    {\n        // list\n        return null;\n    }\n\n    public function create()\n    {\n        // show create form\n        return null;\n    }\n\n    public function store()\n    {\n        // handle create\n        return null;\n    }\n\n    public function show(\$id)\n    {\n        // show one\n        return null;\n    }\n\n    public function edit(\$id)\n    {\n        // show edit form\n        return null;\n    }\n\n    public function update(\$id)\n    {\n        // handle update\n        return null;\n    }\n\n    public function destroy(\$id)\n    {\n        // handle delete\n        return null;\n    }\n}\n";
			} else {
				$template = "<?php\n\nnamespace $namespace;\n\nclass $name\n{\n    public function index()\n    {\n        echo \"This is $name\";\n    }\n}\n";
			}

			if (file_put_contents($file, $template) !== false) {
				echo ($existedBefore ? cli_yellow("Overwrote controller: $rel\n") : cli_green("Created controller: $rel\n"));
			} else {
				echo cli_red("Failed to create controller: $file\n");
			}
		};
	}

	public static function model()
	{
		return function ($argv = []) {
			$start = ($argv[1] === 'add') ? 3 : 2;
			$opts = self::parseOptions($argv, $start);

			$name = $opts['name'] ?? $opts['n'] ?? null;
			if (!$name) {
				echo cli_red("Please provide a name: --name=Posts [--table=posts] [--selectable=id,title]\n");
				return;
			}

			$className = self::modelClassNameFromArg((string) $name);
			if ($className === '') {
				echo cli_red("Invalid model class name: --name=Posts\n");
				return;
			}

			$table = self::resolveModelTable($opts, $className);
			$selectable = self::parseModelSelectable($opts);

			$dir = __DIR__ . '/../../app/Model';
			if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
				echo cli_red("Failed to create directory: $dir\n");
				return;
			}

			$file = $dir . '/' . $className . '.php';
			$existedBefore = file_exists($file);
			if ($existedBefore && !self::wantsForce($opts)) {
				echo cli_red("Model already exists: app/Model/$className.php\n");
				return;
			}

			$tableExport = var_export($table, true);
			$body = "    protected \$table = {$tableExport};\n";
			if ($selectable !== null) {
				$body .= '    protected $selectable = ' . self::phpQuotedArray($selectable) . ";\n";
			}

			$template = "<?php\n\nnamespace App\\Model;\n\nuse App\\Model\\Model;\n\nclass {$className} extends Model\n{\n{$body}}\n";

			if (file_put_contents($file, $template) !== false) {
				echo ($existedBefore ? cli_yellow("Overwrote model: app/Model/{$className}.php\n") : cli_green("Created model: app/Model/{$className}.php\n"));
			} else {
				echo cli_red("Failed to create model: $file\n");
			}
		};
	}

	public static function view()
	{
		return function ($argv = []) {
			$start = ($argv[1] === 'add') ? 3 : 2;
			$opts = self::parseOptions($argv, $start);

			$name = $opts['name'] ?? $opts['n'] ?? null;
			if (!$name) {
				echo cli_red("Please provide a name: --name=templateName\n");
				return;
			}

			$name = preg_replace('/[^A-Za-z0-9_\.\-]/', '', $name);
			$dir = __DIR__ . '/../../resource/view';
			if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
				echo cli_red("Failed to create directory: $dir\n");
				return;
			}

			$file = $dir . '/' . $name . '.php';
			$existedBefore = file_exists($file);
			if ($existedBefore && !self::wantsForce($opts)) {
				echo cli_red("View already exists: resource/view/$name.php\n");
				return;
			}

			$template = "<?php\n\n/** View: $name */\n?>\n<h1>$name</h1>\n";

			if (file_put_contents($file, $template) !== false) {
				echo ($existedBefore ? cli_yellow("Overwrote view: resource/view/$name.php\n") : cli_green("Created view: resource/view/$name.php\n"));
			} else {
				echo cli_red("Failed to create view: $file\n");
			}
		};
	}

	public static function command()
	{
		return function ($argv = []) {
			$start = ($argv[1] === 'add') ? 3 : 2;
			$opts = self::parseOptions($argv, $start);

			$name = $opts['name'] ?? $opts['n'] ?? null;
			if (!$name) {
				echo cli_red("Please provide a name: --name=MyCommand\n");
				return;
			}

			$name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
			if (!str_ends_with($name, 'Command')) {
				$name .= 'Command';
			}

			$dir = __DIR__ . '/..'; // src/Command
			if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
				echo cli_red("Failed to create directory: $dir\n");
				return;
			}

			$file = $dir . '/' . $name . '.php';
			$existedBefore = file_exists($file);
			if ($existedBefore && !self::wantsForce($opts)) {
				echo cli_red("Command already exists: src/Command/$name.php\n");
				return;
			}

			$template = "<?php\n\nnamespace DLight\\Command;\n\nclass $name\n{\n    public static function handle()\n    {\n        return function (\$argv = []) {\n            echo \"$name executed\";\n        };\n    }\n}\n";

			if (file_put_contents($file, $template) !== false) {
				echo ($existedBefore ? cli_yellow("Overwrote command: src/Command/$name.php\n") : cli_green("Created command: src/Command/$name.php\n"));
			} else {
				echo cli_red("Failed to create command: $file\n");
			}
		};
	}

	public static function middleware()
	{
		return function ($argv = []) {
			$start = ($argv[1] === 'add') ? 3 : 2;
			$opts = self::parseOptions($argv, $start);

			$name = $opts['name'] ?? $opts['n'] ?? null;
			if (!$name) {
				echo cli_red("Please provide a name: --name=MyMiddleware\n");
				return;
			}

			$name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
			if (!str_ends_with($name, 'Middleware')) {
				$name .= 'Middleware';
			}

			$dir = __DIR__ . '/../../app/Middleware';
			if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
				echo cli_red("Failed to create directory: $dir\n");
				return;
			}

			$file = $dir . '/' . $name . '.php';
			$existedBefore = file_exists($file);
			if ($existedBefore && !self::wantsForce($opts)) {
				echo cli_red("Middleware already exists: app/Middleware/$name.php\n");
				return;
			}

			$template = "<?php\n\nnamespace App\\Middleware;\n\nuse DLight\\Application\\Middleware;\n\nclass $name extends Middleware\n{\n    public static function sign(): void\n    {\n        Middleware::register('{$name}', function () {\n            // TODO: implement middleware logic\n            return null;\n        });\n    }\n}\n";

			if (file_put_contents($file, $template) !== false) {
				echo ($existedBefore ? cli_yellow("Overwrote middleware: app/Middleware/$name.php\n") : cli_green("Created middleware: app/Middleware/$name.php\n"));
			} else {
				echo cli_red("Failed to create middleware: $file\n");
			}
		};
	}

	public static function mail()
	{
		return function ($argv = []) {
			$start = ($argv[1] === 'add') ? 3 : 2;
			$opts = self::parseOptions($argv, $start);

			$name = $opts['name'] ?? $opts['n'] ?? null;
			if (!$name) {
				echo cli_red("Please provide a name: --name=MyMailer\n");
				return;
			}

			$name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
			if (!str_ends_with($name, 'Mail') && !str_ends_with($name, 'Mailer')) {
				$name .= 'Mail';
			}

			$dir = __DIR__ . '/../../app/Mail';
			if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
				echo cli_red("Failed to create directory: $dir\n");
				return;
			}

			$file = $dir . '/' . $name . '.php';
			$existedBefore = file_exists($file);
			if ($existedBefore && !self::wantsForce($opts)) {
				echo cli_red("Mail class already exists: app/Mail/$name.php\n");
				return;
			}

			$template = "<?php\n\nnamespace App\\Mail;\n\nclass $name\n{\n    public function send(\$to, \$subject, \$body)\n    {\n        // integrate with Mailer\n        return true;\n    }\n}\n";

			if (file_put_contents($file, $template) !== false) {
				echo ($existedBefore ? cli_yellow("Overwrote mail class: app/Mail/$name.php\n") : cli_green("Created mail class: app/Mail/$name.php\n"));
			} else {
				echo cli_red("Failed to create mail class: $file\n");
			}
		};
	}

	/**
	 * Table name: --table=… or snake_case guess from class name (e.g. Posts → posts).
	 */
	private static function resolveModelTable(array $opts, string $className): string
	{
		if (array_key_exists('table', $opts)) {
			$t = $opts['table'];
			if (!is_string($t)) {
				return self::guessTableNameFromClass($className);
			}
			$t = trim($t);
			if ($t === '') {
				return '';
			}
			$t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
			return is_string($t) ? $t : '';
		}

		return self::guessTableNameFromClass($className);
	}

	private static function guessTableNameFromClass(string $className): string
	{
		$s = preg_replace('/(?<!^)[A-Z]/', '_$0', $className);
		return strtolower((string) $s);
	}

	/**
	 * Parse --selectable=[id,title] or --selectable=id,title into a list of column names.
	 */
	private static function parseModelSelectable(array $opts): ?array
	{
		if (!array_key_exists('selectable', $opts)) {
			return null;
		}

		$v = $opts['selectable'];
		if (in_array($v, [true, false, null], true)) {
			return null;
		}
		if (!is_string($v)) {
			return null;
		}

		$v = trim($v);
		if ($v === '' || $v === '*') {
			return null;
		}

		if (str_starts_with($v, '[') && str_ends_with($v, ']')) {
			$v = trim(substr($v, 1, -1));
		}

		$parts = preg_split('/\s*,\s*/', $v);
		if (!is_array($parts)) {
			return null;
		}

		$cols = [];
		foreach ($parts as $p) {
			$p = trim((string) $p);
			if ($p === '') {
				continue;
			}
			if (!preg_match('/^\w+$/', $p)) {
				continue;
			}
			$cols[] = $p;
		}

		return count($cols) > 0 ? $cols : null;
	}

	/** @param list<string> $columns */
	private static function phpQuotedArray(array $columns): string
	{
		$parts = [];
		foreach ($columns as $c) {
			$parts[] = "'" . addcslashes($c, "'\\") . "'";
		}
		return '[' . implode(', ', $parts) . ']';
	}

	/** True when --force is present (overwrite existing scaffold files). */
	private static function wantsForce(array $opts): bool
	{
		return (bool) ($opts['force'] ?? false);
	}

	private static function parseOptions(array $argv, int $start = 2): array
	{
		$opts = [];
		$counter = count($argv);
		for ($i = $start; $i < $counter; $i++) {
			$arg = $argv[$i];
			if (str_starts_with($arg, '--')) {
				$parts = explode('=', substr($arg, 2), 2);
				$opts[$parts[0]] = $parts[1] ?? true;
			} elseif (str_starts_with($arg, '-')) {
				$key = ltrim($arg, '-');
				$val = $argv[$i + 1] ?? true;
				if (!str_starts_with((string) $val, '-')) {
					$opts[$key] = $val;
					$i++;
				} else {
					$opts[$key] = true;
				}
			}
		}
		return $opts;
	}

}