<?php

namespace DLight\Application;

use DLight\Database\Adapter\MysqliAdapter;
use DLight\Database\Adapter\Sqlite3Adapter;
use DLight\Database\Adapter\PdoMysqlAdapter;
use DLight\Database\Adapter\PdoSqliteAdapter;
use DLight\Database\DatabaseManager;
use DLight\Database\Exception\CallWrongMethodOnDbDesign;

/**
 * **Database Handler**
 * 
 * Database proxy to Mapper/Builder.
 *
 * The actual implementation behind this model depends on env('DB_DESIGN'):
 * - 'builder': forwards to Query Builder with fluent methods
 * - 'mapper': forwards to Mapper with CRUD helpers
 *
 * @method \DLight\Database\Interfaces\BuilderInterface table(string $table) Set the table for the query.
 * @method \DLight\Database\Interfaces\BuilderInterface select($columns = ['*']) Select columns for the query.
 * @method \DLight\Database\Interfaces\BuilderInterface where($column, $value = null, $operator = null) Add a where condition to the query.
 * @method \DLight\Database\Interfaces\BuilderInterface insert(array $data) Insert data into the table.
 * @method \DLight\Database\Interfaces\BuilderInterface update(array $data) Update data in the table.
 * @method \DLight\Database\Interfaces\BuilderInterface delete() Delete records from the table.
 * @method \DLight\Database\Interfaces\BuilderInterface softDelete(int|string|null $id = null) Soft delete records from the table.
 * @method \DLight\Database\Interfaces\BuilderInterface execute() Execute the query.
 * @method \DLight\Database\Interfaces\BuilderInterface fetchAll() Fetch all results from the query.
 * @method \DLight\Database\Interfaces\BuilderInterface fetch(string $type = 'assoc') Fetch a single result from the query.
 * @method \DLight\Database\Interfaces\BuilderInterface first(string $type = 'assoc') Fetch the first result from the query.
 * @method \DLight\Database\Interfaces\BuilderInterface get() Get the results from the query.
 *
 * @method \DLight\Database\Interfaces\MapperInterface find($id) Find a record by its ID.
 * @method \DLight\Database\Interfaces\MapperInterface findOrFail($id) Find a record by its ID or throw an exception if not found.
 * @method \DLight\Database\Interfaces\MapperInterface all() Get all records.
 * @method \DLight\Database\Interfaces\MapperInterface where($column, $value, $operator) Add a where condition to the query.
 * @method \DLight\Database\Interfaces\MapperInterface create(array $data) Create a new record.
 * @method \DLight\Database\Interfaces\MapperInterface update($id, array $data) Update a record by its ID.
 * @method \DLight\Database\Interfaces\MapperInterface delete($id) Delete a record by its ID.
 * @method \DLight\Database\Interfaces\MapperInterface insertGetId(array $data) Insert a new record and get its ID.
 * @method \DLight\Database\Interfaces\MapperInterface executeUpdate(array $data) Execute an update operation.
 * @method \DLight\Database\Interfaces\MapperInterface executeDelete() Execute a delete operation.
 * 
 * @method mapper() Switch to Mapper design
 * @method builder() Switch to Builder design
 */
final class DB extends DatabaseManager
{
    /**
     * Summary of table
     * @var string
     */
    protected $table;

    /**
     * Optional columns to select (string|array|null)
     * @var mixed
     */
    protected $selectable;

    /**
     * Check if this class uses SoftDelete trait
     * Allows child models extending DB to automatically detect SoftDelete trait
     */
    protected function usesSoftDelete(): bool
    {
        $class = static::class;
        $softDeleteTrait = \DLight\Database\Traits\SoftDelete::class;
        
        // Check traits in this class and parent classes
        do {
            $traits = class_uses($class);
            if ($traits && in_array($softDeleteTrait, $traits)) {
                return true;
            }
        } while ($class = get_parent_class($class));
        
        return false;
    }

    /**
     * Initialize the DB instance.
     */
    public function __construct()
    {
        parent::__construct();
        if ($this->table) {
            // If child class has SoftDelete trait, use soft delete automatically
            $selectable = property_exists($this, 'selectable') ? $this->selectable : null;
            $this->mapper = $this->getMapper($this->table, $this->usesSoftDelete(), $selectable);
        }
    }

    /**
     * Set the table for the query.
     */
    public function table(string $table): self
    {
        $this->table = $table;
        $selectable = property_exists($this, 'selectable') ? $this->selectable : null;
        $this->mapper = $this->getMapper($table, $this->usesSoftDelete(), $selectable);
        return $this;
    }

    /**
     * Create a mapper-based DB instance regardless of env DB_DESIGN.
     */
    public static function mapper(): self
    {
        $instance = new static();
        return $instance->switchDesign('mapper');
    }

    /**
     * Switch current DB instance to builder design.
     */
    public function builder(): self
    {
        return $this->switchDesign('builder');
    }

    /**
     * Handle dynamic method calls into the model.
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (!is_object($this->mapper) || !method_exists($this->mapper, $method)) {
            $design = (string) env('DB_DESIGN');
            throw CallWrongMethodOnDbDesign::fromMethod((string) $method, $design);
        }

        return call_user_func_array([$this->mapper, $method], $args);
    }

    /**
     * Handle dynamic static method calls into the model.
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        if ($method === 'table') {
            $instance = new static();
            return $instance->table((string) ($args[0] ?? ''));
        }

        if ($method === 'mapper') {
            return (new static())->switchDesign('mapper');
        }

        if ($method === 'builder') {
            return (new static())->switchDesign('builder');
        }

        $instance = new static();
        if (!is_object($instance->mapper) || !method_exists($instance->mapper, $method)) {
            $design = (string) env('DB_DESIGN');
            throw CallWrongMethodOnDbDesign::fromMethod((string) $method, $design);
        }

        return call_user_func_array([$instance->mapper, $method], $args);
    }

    /**
     * Get the database adapter instance.
     * @return MysqliAdapter|PdoMysqlAdapter|PdoSqliteAdapter|Sqlite3Adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }
}
