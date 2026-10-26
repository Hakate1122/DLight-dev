<?php
namespace DLight\Database\Adapter;

use DLight\Database\Interfaces\AdapterInterface;

/**
 * #### PDO SQLite Database Adapter using pdo_sqlite extension
 * **Require**: the `pdo_sqlite` PHP extension.
 */
class PdoSqliteAdapter implements AdapterInterface
{
	protected $pdo;

	/**
	 * Get connection - Connect to a SQLite database using PDO.
	 * 
	 * @param array $config Configuration array with keys:
	 * - `database`: (string) Database file name (default: `:memory:` for in-memory database)
	 */
	public function connect(array $config)
	{
		if (!extension_loaded('pdo_sqlite')) {
			throw new \Exception('PDO SQLite extension is not loaded.');
		}

		$db = $config['database'] ?? ':memory:';
		if ($db === ':memory:') {
			$dsn = 'sqlite::memory:';
		} elseif (str_starts_with($db, 'file:')) {
			$dsn = $db;
		} else {
			$dsn = 'sqlite:' . ROOT_DIR . 'app/database/' . $db;
		}
		$this->pdo = new \PDO($dsn);
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}

	public function disconnect()
	{
		$this->pdo = null;
	}

	public function query($sql, $params = [])
	{
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt;
	}

	public function fetch($result, $type = 'assoc')
	{
		return match ($type) {
            'num' => $result->fetch(\PDO::FETCH_NUM),
            'both' => $result->fetch(\PDO::FETCH_BOTH),
            'object' => $result->fetch(\PDO::FETCH_OBJ),
            default => $result->fetch(\PDO::FETCH_ASSOC),
        };
	}

	public function fetchAll($result, $type = 'assoc')
	{
		$data = [];
		while ($row = $this->fetch($result, $type)) {
			$data[] = $row;
		}
		return $data;
	}

	public function getError()
	{
		return $this->pdo?->errorInfo();
	}

	public function lastInsertId()
	{
		return $this->pdo->lastInsertId();
	}

	public function beginTransaction()
	{
		$this->pdo->beginTransaction();
	}

	public function commit()
	{
		$this->pdo->commit();
	}

	public function rollback()
	{
		$this->pdo->rollBack();
	}
}