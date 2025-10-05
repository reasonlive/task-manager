<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Data\Database;
use App\Core\Data\DQL\Query;
use App\Core\Data\DQL\Relationship\Relation;

abstract class Model
{
    public Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];

    /** @var string|null Classname of relation */
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
        if (str_contains($relation, 'Models') && class_exists($relation)) {
            $this->relationship = $relation;
        }

        return $this;
    }

    public function findAll(array $params = [], array $sort = [], array $limit = []): array
    {
        $data = [];
        $sql = "SELECT t.*";

        if ($this->relationship) {
            $fk_table = Model::getInstance($this->relationship)->getTableName();
            $fk = array_filter(
                $this->fillable,
                fn($field) => str_starts_with($field, substr($fk_table, 0, 3))
                    && str_ends_with($field, '_id')
            );

            $fk_fields = array_map(fn($field) => "r." . $field, $this->getDiffRelationColumns());
            $fk_fields = implode(", ", $fk_fields);

            if (count($fk) === 1) {
                $fk = reset($fk);

                $sql .= ", $fk_fields FROM {$this->table} t";
                $sql .= " JOIN {$fk_table} as r ON t.{$fk} = r.{$this->primaryKey}";
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

        return $this->db->query($sql, $data);
    }

    public function getIds(?string $field = null, array $values = []): array
    {
        $sql = "SELECT id FROM {$this->table}";

        if ($field && !empty($values)) {
            $sql .= " WHERE {$field} IN ('" . implode("','", $values) . "')";
        }

        return array_column($this->db->query($sql), 'id');
    }

    /**
     * Find by id whether relations or not
     * @param int $id
     * @param Relation[] $relations TODO: implement relation without fields
     * @return array|null
     */
    public function findById(int $id, array $relations = []): ?array
    {
        $query = Query::select($this->table)
            ->from();

        if (count($relations) > 0) {
            foreach ($relations as $relation) {
                $query->setRelation($relation);
            }
        }

        $query->equals($this->primaryKey, $id);

        //$query->dump();

        return $this->db->query($query->sql(), $query->params()) ?: null;
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

        $query = Query::insert($this->table);
        foreach ($filteredData as $field => $value) {
            $query->setField($field, $value);
        }

        if ($this->db->query($query->sql(), $query->params()) > 0) {
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

        $query = Query::update($this->table);
        foreach ($filteredData as $field => $value) {
            $query->setField($field, $value);
        }

        $query->equals($this->primaryKey, $id);

        //$query->dump();

        return $this->db->query($query->sql(), $query->params()) > 0;
    }

    public function delete(int $id): bool
    {
        $query = Query::delete($this->table)
            ->equals($this->primaryKey, $id);

        return $this->db->query($query->sql(), $query->params()) > 0;
    }

    public function where(string $column, $value, string $operator = '='): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ?";
        return $this->db->query($sql, [$value]);
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

        return $this->db->query($sql, $data)['count'] ?? 0;
    }

    /**
     * There is only existent fields with values can be passed forward
     * @param array $data
     * @return array matched fields from db or empty
     */
    protected function filterData(array $data): array
    {
        $result = [];
        if (empty($this->fillable)) {
            return $result;
        }

        $result = array_filter($data, fn($item) => !is_null($data));
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
        $columns = $this->db->query($sql);

        return array_column($columns, 'Field');
    }

    private function getDiffRelationColumns(): array
    {
        if ($this->relationship) {
            return array_diff(
                $this->getTableColumns(Model::getInstance($this->relationship)->getTableName()),
                $this->getTableColumns()
            );
        }

        return [];
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