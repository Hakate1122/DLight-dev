<?php

declare(strict_types=1);

use Datahihi1\TinyEnv\TinyEnv;
use DLight\Application\DB;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testCrudWithSqliteMemoryUsingDbMapper(): void
    {
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }

        $previousDriver = TinyEnv::env('DB_DRIVER');
        $previousDesign = TinyEnv::env('DB_DESIGN');
        $previousName = TinyEnv::env('DB_NAME');

        TinyEnv::setCache('DB_DRIVER', 'pdo_sqlite');
        TinyEnv::setCache('DB_DESIGN', 'mapper');
        TinyEnv::setCache('DB_NAME', ':memory:');

        try {
            $db = DB::mapper()->table('users');
            $db->getAdapter()->query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, status INTEGER DEFAULT 1)');

            $id = $db->create([
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'status' => 1,
            ]);

            $this->assertNotNull($id);

            $record = $db->find((int) $id);
            $this->assertIsArray($record);
            $this->assertSame('Alice', $record['name']);
            $this->assertSame('alice@example.com', $record['email']);

            $all = $db->all();
            $this->assertCount(1, $all);

            $updated = $db->update((int) $id, ['name' => 'Alice Updated', 'status' => 2]);
            $this->assertNotFalse($updated);

            $updatedRecord = $db->find((int) $id);
            $this->assertSame('Alice Updated', $updatedRecord['name']);
            $this->assertSame(2, (int) $updatedRecord['status']);

            $deleted = $db->delete((int) $id);
            $this->assertNotFalse($deleted);

            $this->assertFalse($db->find((int) $id));
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
