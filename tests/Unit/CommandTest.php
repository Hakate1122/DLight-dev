<?php

declare(strict_types=1);

use DLight\Application\Command;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
    public function testRunUsesConfiguredDefaultCommandWhenNoCommandIsProvided(): void
    {
        $cli = new Command();
        $cli->register('help', fn($argv) => null);

        $executed = false;
        $cli->register('hello', function (array $argv) use (&$executed): void {
            $executed = true;
            $this->assertSame(['php'], $argv);
        });

        $cli->property('default_command', 'hello');
        $cli->run(['php']);

        $this->assertTrue($executed);
    }
}
