<?php
namespace DLight\Database\QueryBuilder;

use DLight\Database\Interfaces\BuilderInterface;

use function count;
use function func_get_args;
use function is_array;

abstract class BaseBuilder implements BuilderInterface {
    protected $table;
    protected $columns = ['*'];
    protected $wheres = []; // mỗi entry: [column, operator, value, boolean('AND'|'OR')]
    protected $bindings = [];
    protected $operation; // select|insert|update|delete|softDelete
    protected $pendingData = [];

    protected ?bool $tableHasDeletedAtCache = null;

    public function __construct(protected $adapter, string $table, protected bool $useSoftDelete = false)
    {
        $this->table = $table;
    }

    public function table(string $table): BuilderInterface {
        $this->table = $table;
        return $this;
    }

    public function select($columns = ['*']): BuilderInterface {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        $this->operation = 'select';
        return $this;
    }

    public function where($column, $value = null, $operator = "="): BuilderInterface {
        if (is_array($column)) {
            if (is_array($value)) {
                $cols = array_values($column);
                $vals = array_values($value);
                $count = min(count($cols), count($vals));
                for ($i = 0; $i < $count; $i++) {
                    $this->wheres[] = [$cols[$i], '=', $vals[$i], 'AND'];
                    $this->bindings[] = $vals[$i];
                }
                return $this;
            }

            foreach ($column as $col => $val) {
                $this->wheres[] = [$col, '=', $val, 'AND'];
                $this->bindings[] = $val;
            }
            return $this;
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }
        if ($operator === null && $value !== null) {
            $operator = '=';
        }

        $this->wheres[] = [$column, $operator, $value, 'AND'];
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere($column, $value = null): BuilderInterface {
        if (is_array($column)) {
            if (is_array($value)) {
                $cols = array_values($column);
                $vals = array_values($value);
                $count = min(count($cols), count($vals));
                for ($i = 0; $i < $count; $i++) {
                    $this->wheres[] = [$cols[$i], '=', $vals[$i], 'OR'];
                    $this->bindings[] = $vals[$i];
                }
                return $this;
            }

            foreach ($column as $col => $val) {
                $this->wheres[] = [$col, '=', $val, 'OR'];
                $this->bindings[] = $val;
            }
            return $this;
        }

        $this->wheres[] = [$column, '=', $value, 'OR'];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Check whether current table has deleted_at column.
     */
    protected function tableHasDeletedAt(): bool
    {
        if ($this->tableHasDeletedAtCache !== null) {
            return $this->tableHasDeletedAtCache;
        }
        try {
            $adapterClass = $this->adapter::class;
            if (stripos($adapterClass, 'sqlite') !== false) {
                $sql = "PRAGMA table_info(\"{$this->table}\")";
                $res = $this->adapter->query($sql);
                $rows = $this->adapter->fetchAll($res);
                foreach ($rows as $r) {
                    $name = $r['name'] ?? $r['Name'] ?? $r['field'] ?? $r['Field'] ?? null;
                    if ($name === 'deleted_at') {
                        $this->tableHasDeletedAtCache = true;
                        return true;
                    }
                }
            } else {
                $sql = "SHOW COLUMNS FROM `{$this->table}` LIKE 'deleted_at'";
                $res = $this->adapter->query($sql);
                $rows = $this->adapter->fetchAll($res);
                if (!empty($rows)) {
                    $this->tableHasDeletedAtCache = true;
                    return true;
                }
            }
        } catch (\Throwable) {
            $this->tableHasDeletedAtCache = false;
            return false;
        }

        $this->tableHasDeletedAtCache = false;
        return false;
    }

    /**
     * Helper to get current where bindings
     */
    public function getBindings(): array {
        return $this->bindings;
    }

    /**
     * Execute the current statement and return all records
     */
    public function fetchAll(): array
    {
        $sql = $this->toSql();
        $params = $this->getBindings();
        $result = $this->adapter->query($sql, $params);
        return $this->adapter->fetchAll($result);
    }

    /**
     * fetch() alias for convenience
     */
    public function fetch(string $type = 'assoc')
    {
        $sql = $this->toSql();
        $params = $this->getBindings();
        $result = $this->adapter->query($sql, $params);
        return $this->adapter->fetch($result, $type);
    }

    /**
     * fetch() alias for convenience getting the first record only
     */
    public function first(string $type = 'assoc')
    {
        $rows = $this->fetchAll();
        return $rows[0] ?? null;
    }

    /**
     * fetchAll alias
     */
    public function get(): array
    {
        return $this->fetchAll();
    }

    public function insert(array $data): BuilderInterface
    {
        $this->operation = 'insert';
        $this->pendingData = $data;
        return $this;
    }

    public function update(array $data): BuilderInterface
    {
        $this->operation = 'update';
        $this->pendingData = $data;
        return $this;
    }

    public function delete(): BuilderInterface
    {
        $this->operation = 'delete';
        return $this;
    }

    /**
     * Soft delete records (set deleted_at timestamp)
     * Can be called as:
     * - softDelete() - for chaining with where()->softDelete()->execute()
     * - softDelete($id) - for direct soft delete by ID
     * @param int|string|null $id Optional ID to soft delete directly
     * @return BuilderInterface|int
     */
    public function softDelete($id = null)
    {
        if ($id !== null) {
            return $this->softDeleteById($id);
        }

        $this->operation = 'softDelete';
        return $this;
    }
    
    /**
     * Soft delete by ID (internal method, implemented in concrete builders)
     * @param int|string $id
     * @return int
     */
    protected function softDeleteById($id)
    {
        throw new \BadMethodCallException('softDeleteById() must be implemented in the concrete builder.');
    }

    /**
     * Execute the built query
     * @throws \BadMethodCallException execution must be implemented in the concrete builder.
     * @return never
     */
    public function execute()
    {
        throw new \BadMethodCallException('execute() must be implemented in the concrete builder.');
    }
}