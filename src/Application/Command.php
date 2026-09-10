<?php

namespace DLight\Application;

use DLight\Command\Helper\CommandEntry;

/**
 * **Command Manager**
 * 
 * Command class for registering and executing CLI commands.
 */
class Command
{
    public array $commands = [];
    private array $properties = [];

    /**
     * Register a new command.
     *
     * **Example**: register('greet', [\App\Command\Greet::class, 'handle'])
     *
     * @param string $name Command name
     * @param callable|array|string $handler Function, method array, or class name
     * @param bool $hiddenOnPhar Whether to hide this command in Phar builds
     */
    public function register(string $name, callable|array|string $handler, bool $hiddenOnPhar = false): CommandEntry
    {
        if (isset($this->commands[$name])) {
            throw new \InvalidArgumentException("Duplicate command name: $name");
        }

        $entry = new CommandEntry($this, $name, $handler, $hiddenOnPhar);
        $this->commands[$name] = $entry;
        return $entry;
    }

    /**
     * Register an alias for an existing command. An existing command must be registered before creating an alias for it.
     *
     * Example: registerAlias('say:greet', 'greet')
     *
     * @param string $target Name of the existing command to alias
     */
    public function registerAlias(string $alias, string $target): CommandEntry
    {
        if (isset($this->commands[$alias])) {
            throw new \InvalidArgumentException("Duplicate command name: $alias");
        }

        if (!isset($this->commands[$target])) {
            throw new \InvalidArgumentException("Target command not found: $target");
        }

        $this->commands[$alias] = $this->commands[$target];
        return $this->commands[$target];
    }

    /**
     * Get or set a property for the command manager.
     *
     * @param string $name Property name
     * @param mixed|null $default Default value if setting a new property
     * @return mixed The property value or null if not set
     */
    public function property(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        }

        if (func_num_args() > 1) {
            $this->properties[$name] = $default;
            return $default;
        }

        return null;
    }

    public function hasCommand(string $name): bool{
        return isset($this->commands[$name]);
    }

    public function run(array $argv): void
    {
        $cmd = $argv[1] ?? null;

        if ($cmd === null || $cmd === '') {
            $defaultCommand = $this->property('default_command');
            if ($defaultCommand === null || $defaultCommand === '') {
                $defaultCommand = $this->property('default');
            }
            if ($defaultCommand === null || $defaultCommand === '') {
                $defaultCommand = function_exists('env') ? env('DLI_DEFAULT_COMMAND') : null;
            }

            if (is_string($defaultCommand) && trim($defaultCommand) !== '') {
                $cmd = trim($defaultCommand);
            } else {
                $cmd = 'help';
            }
        }

        if (!isset($this->commands[$cmd])) {
            echo cli_red("Command not found: $cmd\n");
            exit(1);
        }

        $entry = $this->commands[$cmd];
        $handler = $entry instanceof CommandEntry ? $entry->getHandler() : $entry;

        if (is_string($handler) && class_exists($handler)) {
            $instance = new $handler();
            $instance($argv);
            return;
        }

        if (is_callable($handler)) {
            $handler($argv);
            return;
        }

        echo "Invalid command handler for: $cmd\n";
    }

    public function list(): array
    {
        $list = [];
        foreach ($this->commands as $name => $entry) {
            $info = '';
            if ($entry instanceof CommandEntry && $entry->info !== null) {
                $info = ' -> ' . $entry->info;
            }
            $list[] = $name . $info;
        }
        return $list;
    }
}

