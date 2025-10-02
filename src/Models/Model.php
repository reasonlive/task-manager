<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;

abstract class Model
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(array $params = [], array $sort = []): array
    {
        $data = [];
        $sql = "SELECT * FROM {$this->table}";

        if (count($params) > 0) {
            $data = $this->filterData($params);
            $count = count($data);

            if ($count > 0) {
                $sql .= " WHERE ";

                $i = 1;
                foreach ($data as $key => $value) {
                    if ($i < $count) {
                        $sql .= "{$key} = ? AND ";
                    }
                    else {
                        $sql .= "{$key} = ?";
                    }

                    $i++;
                }

                $data = array_values($data);
            }
        }

        if (count($sort) === 2) {
            $sql .= " ORDER BY ";
            $sql .= $sort[0];
            $sql .= " {$sort[1]}";
        }

        $stmt = $this->db->query($sql, $data);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create new object in the database
     * @param array $data
     * @return int|null ID of new object
     */
    public function create(array $data = []): ?int
    {
        // Фильтруем данные по fillable
        $filteredData = $this->filterData($data);

        if (empty($filteredData)) {
            return null;
        }

        $columns = implode(', ', array_keys($filteredData));
        $placeholders = implode(', ', array_fill(0, count($filteredData), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        if ($this->db->query($sql, array_values($filteredData))->rowCount() > 0) {
            return $this->db->lastInsertId();
        }

        return null;
    }

    public function update(int $id, array $data): bool
    {
        $filteredData = $this->filterData($data);

        if (empty($filteredData)) {
            return false;
        }

        $setClause = implode(' = ?, ', array_keys($filteredData)) . ' = ?';
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?";

        $params = array_values($filteredData);
        $params[] = $id;

        return $this->db->query($sql, $params)->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->query($sql, [$id])->rowCount() > 0;
    }

    public function softDelete(int $id): bool
    {
        if (in_array('is_active', $this->getTableColumns())) {
            return $this->update($id, ['is_active' => 0]);
        }

        if (in_array('deleted_at', $this->getTableColumns())) {
            return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }

        return $this->delete($id);
    }

    public function where(string $column, $value, string $operator = '='): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ?";
        $stmt = $this->db->query($sql, [$value]);
        return $stmt->fetchAll();
    }

    public function firstWhere(string $column, $value, string $operator = '='): ?array
    {
        $results = $this->where($column, $value, $operator);
        return $results[0] ?? null;
    }

    /**
     * Count record amount in the database
     * @param array $params optional filters
     * @return int
     */
    public function count(array $params = []): int
    {
        $data = [];
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";

        if (count($params) > 0) {
            $data = $this->filterData($params);
            $count = count($data);


            if ($count > 0) {
                $sql .= " WHERE ";
            }

            $i = 1;
            foreach ($data as $key => $value) {
                if ($i < $count) {
                    $sql .= "{$key} = ? AND ";
                }
                else {
                    $sql .= "{$key} = ?";
                }

                $i++;
            }

            $data = array_values($data);
        }

        $stmt = $this->db->query($sql, $data);

        return $stmt->fetch()['count'] ?? 0;
    }

    /**
     * There is only existent fields with values can be passed forward
     * @param array $data
     * @return array matched fields from db or empty
     */
    protected function filterData(array $data): array
    {
        if (empty($this->fillable)) {
            return [];
        }

        $data = array_filter($data, fn($item) => !empty($item));

        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function getTableColumns(): array
    {
        $sql = "DESCRIBE {$this->table}";
        $stmt = $this->db->query($sql);
        $columns = $stmt->fetchAll();

        return array_column($columns, 'Field');
    }

    public function getColumns(): array
    {
        return $this->getTableColumns();
    }

    public function getLastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->db->commit();
    }

    public function rollBack(): bool
    {
        return $this->db->rollBack();
    }

    public static function getInstance(?string $modelClass = null): static
    {
        if (!$modelClass) {
            return new static();
        }

        return new $modelClass();
    }

    public static function createInstance(string $modelClass, array $data): static
    {
        return (new $modelClass())->create($data);
    }
}