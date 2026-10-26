<?php

namespace DLight\Database;

use DLight\Database\Adapter\MysqliAdapter;
use DLight\Database\Adapter\PdoMysqlAdapter;
use DLight\Database\Adapter\Sqlite3Adapter;
use DLight\Database\Adapter\PdoSqliteAdapter;

use DLight\Database\Exception\UnsupportedDesignException;
use DLight\Database\Exception\UnsupportedDriverException;

use DLight\Database\Mapper\MysqlMapper;
use DLight\Database\Mapper\SqliteMapper;

use DLight\Database\QueryBuilder\MysqlBuilder;
use DLight\Database\QueryBuilder\SqliteBuilder;

use function in_array;
use function is_object;

/**
 * **Database Manager**
 * 
 * Manages database connections and mappers/builders based on configuration.
 * 
 * It supports different database drivers and design patterns:
 * - Drivers: `mysqli`, `pdo_mysql`, `sqlite3`, `pdo_sqlite`, and more if needed
 * - Designs: `mapper` (active record style) and `builder` (query builder style)
 */
class DatabaseManager
{
    // constants for supported drivers and designs.
    /** Supported database drivers */
    protected const SUPPORTED_DRIVERS = ['mysqli', 'pdo_mysql', 'sqlite3', 'pdo_sqlite'];
    /** Supported database designs */
    protected const SUPPORTED_DESIGNS = ['mapper', 'builder'];

    // properties for adapter, mapper/builder class.
    protected $driver;
    /** Adapter instance */
    protected $adapter;
    /** Mapper or Builder instance */
    protected $mapper;
    /** Mapper or Builder class name based on design */
    protected $mapperClass;

    // properties for database (optional).
    /**
     * Table name for the mapper/builder instance
     * @var string
     */
    protected $table;

    /**
     * Selectable columns for table queries (string|array|null)
     * @var string|array|null
     */
    protected $selectable;
    /**
     * Fillable fields for mass assignment
     * @var string|array|null
     */
    protected $fillable;

    public function __construct()
    {
        $driver = env('DB_DRIVER');
        $this->driver = $driver;
        if (!$driver) {
            throw new \InvalidArgumentException("DB_DRIVER is not set.");
        }
        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new UnsupportedDriverException("Invalid DB_DRIVER: $driver" . ". Accepts: " . implode(', ', self::SUPPORTED_DRIVERS) . ".");
        }

        $design = env('DB_DESIGN');
        if (!$design) {
            throw new \InvalidArgumentException("DB_DESIGN is not set.");
        }
        if (!in_array($design, self::SUPPORTED_DESIGNS, true)) {
            throw new UnsupportedDesignException("Invalid DB_DESIGN: $design" . ". Accepts: " . implode(', ', self::SUPPORTED_DESIGNS) . ".");
        }

        $config = $this->getConfig($driver);

        switch ($driver) {
            case 'mysqli':
                $this->adapter = new MysqliAdapter();
                break;
            case 'sqlite3':
                $this->adapter = new Sqlite3Adapter();
                break;
            case 'pdo_sqlite':
                $this->adapter = new PdoSqliteAdapter();
                break;
            case 'pdo_mysql':
            default:
                $this->adapter = new PdoMysqlAdapter();
        }
        $this->adapter->connect($config);

        $this->mapperClass = $this->resolveMapperClass($design, $driver);
    }

    // /**
    //  * Create a table.
    //  *
    //  * @param string $table
    //  * @param array $columns associative array of column => definition
    //  * @param array $options ['if_not_exists' => true]
    //  * @return bool
    //  */
    // public function createTable(string $table, array $columns, array $options = []): bool
    // {
    //     if (empty($columns)) {
    //         throw new \InvalidArgumentException('Columns definition cannot be empty.');
    //     }

    //     $ifNotExists = $options['if_not_exists'] ?? true;

    //     $driver = (string) ($this->driver ?? env('DB_DRIVER'));

    //     $cols = [];
    //     foreach ($columns as $name => $def) {
    //         // Allow numeric keys where full definition is provided as string
    //         if (is_int($name)) {
    //             $cols[] = $def;
    //             continue;
    //         }

    //         $definition = (string) $def;

    //         // Normalize AUTO_INCREMENT between MySQL and SQLite
    //         if (str_contains($driver, 'sqlite') && str_contains(strtoupper($definition), 'AUTO_INCREMENT')) {
    //             // SQLite requires INTEGER PRIMARY KEY AUTOINCREMENT
    //             $definition = 'INTEGER PRIMARY KEY AUTOINCREMENT';
    //         }

    //         $cols[] = "`$name` $definition";
    //     }

    //     $sql = 'CREATE TABLE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') . $table . ' (' . implode(', ', $cols) . ')';

    //     $this->adapter->query($sql);
    //     return true;
    // }

    // /**
    //  * Alter a table. Supported operations: add, drop. Modify is available for MySQL only.
    //  *
    //  * @param string $table
    //  * @param array $changes ['add' => ['col' => 'DEF', ...], 'drop' => ['col1','col2'], 'modify' => ['col' => 'DEF']]
    //  * @return bool
    //  */
    // public function alterTable(string $table, array $changes): bool
    // {
    //     $driver = (string) ($this->driver ?? env('DB_DRIVER'));

    //     $stmts = [];

    //     if (!empty($changes['add']) && is_array($changes['add'])) {
    //         foreach ($changes['add'] as $col => $def) {
    //             if (is_int($col)) {
    //                 $stmts[] = "ALTER TABLE $table ADD COLUMN $def";
    //             } else {
    //                 $stmts[] = "ALTER TABLE $table ADD COLUMN `$col` $def";
    //             }
    //         }
    //     }

    //     if (!empty($changes['drop']) && is_array($changes['drop'])) {
    //         foreach ($changes['drop'] as $col) {
    //             // SQLite supports DROP COLUMN only starting with 3.35.0; many versions do not.
    //             if (str_contains($driver, 'sqlite')) {
    //                 throw new \Exception('Dropping columns on SQLite is not supported by this helper.');
    //             }
    //             $stmts[] = "ALTER TABLE $table DROP COLUMN `$col`";
    //         }
    //     }

    //     if (!empty($changes['modify']) && is_array($changes['modify'])) {
    //         if (str_contains($driver, 'sqlite')) {
    //             throw new \Exception('Modifying columns on SQLite is not supported by this helper.');
    //         }
    //         foreach ($changes['modify'] as $col => $def) {
    //             $stmts[] = "ALTER TABLE $table MODIFY COLUMN `$col` $def";
    //         }
    //     }

    //     foreach ($stmts as $sql) {
    //         $this->adapter->query($sql);
    //     }

    //     return true;
    // }

    // /**
    //  * Drop a table.
    //  *
    //  * @param string $table
    //  * @param bool $ifExists
    //  * @return bool
    //  */
    // public function dropTable(string $table, bool $ifExists = true): bool
    // {
    //     $sql = 'DROP TABLE ' . ($ifExists ? 'IF EXISTS ' : '') . $table;
    //     $this->adapter->query($sql);
    //     return true;
    // }

    /**
     * Resolve mapper/builder class from design and driver.
     */
    protected function resolveMapperClass(string $design, string $driver): string
    {
        if (!in_array($design, self::SUPPORTED_DESIGNS, true)) {
            throw new UnsupportedDesignException("Invalid DB_DESIGN: $design" . ". Accepts: " . implode(', ', self::SUPPORTED_DESIGNS) . ".");
        }

        if ($design === 'mapper') {
            if (in_array($driver, ['mysqli', 'pdo_mysql'], true)) {
                return MysqlMapper::class;
            }
            return SqliteMapper::class;
        }

        if (in_array($driver, ['mysqli', 'pdo_mysql'], true)) {
            return MysqlBuilder::class;
        }

        return SqliteBuilder::class;
    }

    /**
     * Switch current instance database design dynamically.
     * @param string $design The design to switch to ('mapper' or 'builder')
     * @return self Returns the current instance for chaining
     */
    public function switchDesign(string $design): self
    {
        $driver = (string) env('DB_DRIVER');
        $this->mapperClass = $this->resolveMapperClass($design, $driver);

        if (!empty($this->table)) {
            $selectable = property_exists($this, 'selectable') ? $this->selectable : null;
            $useSoftDelete = method_exists($this, 'usesSoftDelete') && (bool) $this->usesSoftDelete();
            $this->mapper = $this->getMapper($this->table, $useSoftDelete, $selectable);
        }

        return $this;
    }

    /**
     * Get database configuration based on driver
     * @param mixed $driver
     * 
     */
    protected function getConfig($driver)
    {
        if (str_contains($driver, 'sqlite')) {
            $dbName = (string) env('DB_NAME', ':memory:');
            if ($dbName === ':memory:' || $dbName === '' || str_ends_with($dbName, '.db') || str_ends_with($dbName, '.sqlite') || str_ends_with($dbName, '.sqlite3')) {
                return ['database' => $dbName];
            }

            return ['database' => $dbName . '.db'];
        }
        if (str_contains($driver, 'mysql')) {
            return [
                'host'     => env('DB_HOST'),
                'port'     => env('DB_PORT'),
                'user'     => env('DB_USER'),
                'password' => env('DB_PASS') ?? null,
                'database' => env('DB_NAME'),
            ];
        }
        return null;
    }

    /**
     * Get the current database adapter
     * @return MysqliAdapter|PdoMysqlAdapter|PdoSqliteAdapter|Sqlite3Adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Get a mapper/builder instance for a specific table
     *
     * @param mixed $table The table name for the mapper/builder
     * @param bool $useSoftDelete Whether to use soft delete (default: false)
     * @param mixed $selectable Optional columns to select (string|array|null)
     * @return object
     */
    public function getMapper($table, $useSoftDelete = false, $selectable = null)
    {
        $mapper = new $this->mapperClass($this->adapter, $table, $useSoftDelete);

        if ($selectable !== null && is_object($mapper) && method_exists($mapper, 'select')) {
            $mapper->select($selectable);
        }

        return $mapper;
    }
}
