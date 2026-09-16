<?php
namespace DLight\Database\Mapper;

class MysqlMapper extends BaseMapper {
    public function find($id) {
        $cols = $this->columns;
        $colSql = (count($cols) === 1 && $cols[0] === '*') ? '*' : '`' . implode('`,`', $cols) . '`';
        $sql = "SELECT {$colSql} FROM `{$this->table}` WHERE id = ?";
        $result = $this->adapter->query($sql, [$id]);
        return $this->adapter->fetch($result);
    }

    public function findOrFail($id) {
        $record = $this->find($id);
        if (!$record) {
            throw new \Exception("Record not found with ID: $id");
        }
        return $record;
    }

    public function all() {
        $cols = $this->columns;
        $colSql = (count($cols) === 1 && $cols[0] === '*') ? '*' : '`' . implode('`,`', $cols) . '`';
        $sql = "SELECT {$colSql} FROM `{$this->table}`";
        $result = $this->adapter->query($sql);
        return $this->adapter->fetchAll($result);
    }

    public function where($column, $value, $operator = "=") {
        $cols = $this->columns;
        $colSql = (count($cols) === 1 && $cols[0] === '*') ? '*' : '`' . implode('`,`', $cols) . '`';
        $sql = "SELECT {$colSql} FROM `{$this->table}` WHERE `{$column}` {$operator} ?";
        $result = $this->adapter->query($sql, [$value]);
        return $this->adapter->fetchAll($result);
    }

    public function create(array $data) {
        $fields = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)";
        $this->adapter->query($sql, array_values($data));
        return $this->adapter->lastInsertId();
    }

    public function update($id, array $data) {
        $fields = array_keys($data);
        $set = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
        $sql = "UPDATE `{$this->table}` SET $set WHERE id = ?";
        $params = array_values($data);
        $params[] = $id;
        return $this->adapter->query($sql, $params);
    }

    public function delete($id) {
        if ($this->useSoftDelete) {
            if (!$this->hasDeletedAt()) {
                throw new \RuntimeException("Table '{$this->table}' does not have 'deleted_at' column for soft delete.");
            }
            $sql = "UPDATE `{$this->table}` SET `deleted_at` = ? WHERE id = ?";
            $now = date('Y-m-d H:i:s');
            return $this->adapter->query($sql, [$now, $id]);
        }

        $sql = "DELETE FROM `{$this->table}` WHERE id = ?";
        return $this->adapter->query($sql, [$id]);
    }
    public function insertGetId(array $data) {
        $this->create($data);
        return $this->adapter->lastInsertId();
    }
    public function executeUpdate(array $data) {
        if (!isset($data['id'])) {
            throw new \InvalidArgumentException("ID is required for update.");
        }
        $id = $data['id'];
        unset($data['id']);
        return $this->update($id, $data);
    }
    public function executeDelete(array $data) {
        if (!isset($data['id'])) {
            throw new \InvalidArgumentException("ID is required for delete.");
        }
        $id = $data['id'];
        return $this->delete($id);
    }
    public function softDelete($id) {
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = ? WHERE id = ?";
        $now = date('Y-m-d H:i:s');
        return $this->adapter->query($sql, [$now, $id]);
    }

    public function restore($id) {
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = NULL WHERE id = ?";
        return $this->adapter->query($sql, [$id]);
    }
}