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

    /** @var string|null table name of relation */
    protected ?string $relationship = null;

    /** @var bool For checking pushing into database */
    private bool $recordOperation = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    public function setRelation(mixed $relation): static
    {
        if (str_contains($relation, 'Models')) {
            $this->relationship = Model::getInstance($relation)->getTableName();

        } else if (is_string($relation)) {
            $this->relationship = $relation;
        }

        return $this;
    }

    public function findAll(array $params = [], array $sort = [], array $limit = []): array
    {
        $data = [];
        $sql = "SELECT t.*";

        if ($this->relationship) {
            $fk = array_filter(
                $this->fillable,
                fn($field) => str_starts_with($field, substr($this->relationship, 0, 3))
                    && str_ends_with($field, '_id')
            );

            $fk_fields = array_map(fn($field) => "r." . $field, $this->getTableColumns($this->relationship));
            $fk_fields = implode(", ", $fk_fields);

            if (count($fk) === 1) {
                $fk = reset($fk);

                $sql .= ", $fk_fields FROM {$this->table} t";
                $sql .= " JOIN {$this->relationship} as r ON t.{$fk} = r.{$this->primaryKey}";
            }
        } else {
            $sql .= " FROM {$this->table} t";
        }

        if (count($params) > 0) {
            $data = $this->filterData($params);
            $count = count($data);

            if ($count > 0) {
                $sql .= " WHERE ";

                $i = 1;
                foreach ($data as $key => $value) {
                    if ($i < $count) {
                        $sql .= "t.{$key} = ? AND ";
                    } else {
                        $sql .= "t.{$key} = ?";
                    }

                    $i++;
                }

                $data = array_values($data);
            }
        }

        if (count($sort) === 2) {
            $sql .= " ORDER BY t.{$sort[0]} {$sort[1]}";
        }

        if (count($limit)) {
            $sql .= " LIMIT ? OFFSET ?";
            $data[] = $limit[0];
            $data[] = $limit[1] ?? 0;
        }

        $stmt = $this->db->query($sql, $data);

        return $stmt->fetchAll();
    }

    public function getIds(?string $field = null, array $values = []): array
    {
        $sql = "SELECT id FROM {$this->table}";

        if ($field && !empty($names)) {
            $sql .= " WHERE {$field} IN ('" . implode("','", $names) . "')";
        }

        return array_column($this->db->query($sql)->fetchAll(), 'id');
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
        $this->recordOperation = true;
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
        $this->recordOperation = true;
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
    protected function filterData(array $data): mixed
    {
        $result = [];
        if (empty($this->fillable)) {
            return $result;
        }

        $result = array_filter($data, fn($item) => !empty($item));
        $result = array_intersect_key($result, array_flip($this->fillable));

        if (in_array($this->primaryKey, array_keys($data))) {
            if ($this->recordOperation) {
                return $result;
            }

            return $result + [$this->primaryKey => $data[$this->primaryKey]];
        }

        return $result;
    }

    private function getTableColumns(?string $table = null): array
    {
        $t = $table ?: $this->table;
        $sql = "DESCRIBE {$t}";
        $stmt = $this->db->query($sql);
        $columns = $stmt->fetchAll();

        return array_diff(array_column($columns, 'Field'), [
            $this->primaryKey,
            'created_at',
            'updated_at'
        ]);
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
        if (!$modelClass || !class_exists($modelClass)) {
            return new static();
        }

        return new $modelClass();
    }
}