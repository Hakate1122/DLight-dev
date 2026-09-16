<?php

namespace DLight\Command;

use DLight\Application\Command;

/**
 * Registers core commands to the CLI application.
 */
class Register
{
    public function core(Command $cli): void
    {
        $cli->register('help', fn($argv) => Core::help()($argv))->info('Show help information');
        $cli->register('help:add', Core::helpAdd())->info('Show detailed help for add / add:<type>');
        $cli->registerAlias('-h', 'help')->infoAlias('help');
        $cli->register('version', Core::version())->info('Show application version');
        $cli->registerAlias('-v', 'version')->infoAlias('version');
        $cli->register('server', Server::server())->info('Start the development server');
        $cli->registerAlias('-s', 'server')->infoAlias('server');
        $cli->register('list', Core::list($cli->list()))->info('List all available commands');
        $cli->register('add', Add::handle())->info('Add a new component');
        $cli->registerAlias('make', 'add')->infoAlias('add');
        $cli->register('add:controller', Add::controller())->info('Create a new controller');
        $cli->register('add:model', Add::model())->info('Create a new model');
        $cli->register('add:view', Add::view())->info('Create a new view');
        $cli->register('add:command', Add::command())->info('Create a new command');
        $cli->register('add:middleware', Add::middleware())->info('Create a new middleware');
        $cli->register('add:mail', Add::mail())->info('Create a new mail class');
        $cli->register('encode', \DLight\Command\Encode::handle())->info('Encode PHP code with ionCube Loader');
        $cli->register('test', Test::handle())->info('Run test suite (phpunit)');
    }
}
