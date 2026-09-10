<?php

declare(strict_types=1);

use DLight\Application\Command;
use DLight\Command\Register;
use PHPUnit\Framework\TestCase;

class CommandRouterTest extends TestCase
{
    public function testDliRoutesReuseExistingCommandRegistry(): void
    {
        $cli = new Command();
        (new Register())->core($cli);

        require dirname(__DIR__, 2) . '/app/Router/command/command.php';

        $this->assertTrue($cli->hasCommand('help'));
        $this->assertTrue($cli->hasCommand('hello'));
    }

    public function testDuplicateCommandNameThrowsException(): void
    {
        $cli = new Command();
        $cli->register('hello', static fn() => null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate command name: hello');

        $cli->register('hello', static fn() => null);
    }

    public function testDuplicateCommandAliasThrowsException(): void
    {
        $cli = new Command();
        $cli->register('hello', static fn() => null);
        $cli->registerAlias('hi', 'hello');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate command name: hi');

        $cli->registerAlias('hi', 'hello');
    }
}
