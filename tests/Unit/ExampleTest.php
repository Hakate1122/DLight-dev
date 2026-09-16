<?php

declare(strict_types=1);

use Datahihi1\TinyEnv\TinyEnv;
use DLight\Application\DB;
use DLight\Database\Mapper\SqliteMapper;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function testAnotherExample()
    {
        $this->assertEquals(2, 1 + 1);
    }

    public function testDbMapperTableKeepsMapperDesign(): void
    {
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        $previousDriver = TinyEnv::env('DB_DRIVER');
        $previousDesign = TinyEnv::env('DB_DESIGN');
        $previousName = TinyEnv::env('DB_NAME');

        TinyEnv::setCache('DB_DRIVER', 'sqlite3');
        TinyEnv::setCache('DB_DESIGN', 'builder');
        TinyEnv::setCache('DB_NAME', 'demo');

        try {
            $db = DB::mapper()->table('users');
            $property = new ReflectionProperty(DB::class, 'mapper');
            $property->setAccessible(true);
            $this->assertInstanceOf(SqliteMapper::class, $property->getValue($db));
        } finally {
            if ($previousDriver === null) {
                TinyEnv::clearCache('DB_DRIVER');
            } else {
                TinyEnv::setCache('DB_DRIVER', $previousDriver);
            }

            if ($previousDesign === null) {
                TinyEnv::clearCache('DB_DESIGN');
            } else {
                TinyEnv::setCache('DB_DESIGN', $previousDesign);
            }

            if ($previousName === null) {
                TinyEnv::clearCache('DB_NAME');
            } else {
                TinyEnv::setCache('DB_NAME', $previousName);
            }
        }
    }
}
