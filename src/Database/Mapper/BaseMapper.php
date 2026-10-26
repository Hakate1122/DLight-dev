<?php
namespace DLight\Database\Mapper;

use DLight\Database\Interfaces\MapperInterface;

use function count;
use function func_get_args;
use function is_array;

abstract class BaseMapper implements MapperInterface {
    protected ?bool $hasDeletedAt = null;

    /** Columns to select for queries (default: ['*']) */
    protected $columns = ['*'];

    public function __construct(protected $adapter, protected $table, protected bool $useSoftDelete = false)
    {
    }

    /**
     * Set selectable columns for mapper queries.
     * Accepts a string (single column or '*') or multiple string args or an array.
     */
    public function select($columns = ['*']) {
        if (is_array($columns)) {
            $this->columns = $columns;
        } else {
            $args = func_get_args();
            $this->columns = count($args) > 1 ? $args : [$columns];
        }
        return $this;
    }

    /**
     * Detect whether the table has a deleted_at column.
     */
    protected function hasDeletedAt(): bool
    {
        if ($this->hasDeletedAt !== null) {
            return $this->hasDeletedAt;
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
                        $this->hasDeletedAt = true;
                        return true;
                    }
                }
            } else {
                $sql = "SHOW COLUMNS FROM `{$this->table}` LIKE 'deleted_at'";
                $res = $this->adapter->query($sql);
                $rows = $this->adapter->fetchAll($res);
                if (!empty($rows)) {
                    $this->hasDeletedAt = true;
                    return true;
                }
            }
        } catch (\Throwable) {
            $this->hasDeletedAt = false;
            return false;
        }

        $this->hasDeletedAt = false;
        return false;
    }
}