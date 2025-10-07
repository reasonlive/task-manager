<?php

namespace App\Core\Data;

use App\Core\Data\DQL\Query;

abstract class Repository
{
    protected string $table;
    protected Database $db;

    public function __construct(protected string $modelClass)
    {
        $this->table = (new $this->modelClass())->getTable();
    }

    /**
     * TODO: add filtration by params
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function findAll(array $params = []): array
    {
        $query = Query::select($this->table)->from()
            ->order(Model::$primaryKey);

        if ($result = $this->db->query($query->sql(), $query->params())) {
            return array_map(fn($item) => Model::transform($item), $result);
        }

        return [];
    }

    public function getIds(?string $field = null, array $values = []): array
    {
        return [];
    }


    public function find(int $id, array $relations = []): ?Model
    {
        if (!count($relations)) {
            return $this->modelClass::load($id);
        }

        $query = Query::select(static::$table)->from();
        foreach ($relations as $relation) {
            $query->setRelation($relation);
        }

        $query->equals('id', $id);
        return Model::transform($this->db->query($query->sql(), $query->params()));
    }

    public function create(array $data = []): ?int
    {
        if (count($data) > 0) {
            try {
                $new = new $this->modelClass();

                foreach ($data as $key => $value) {
                    $new->$key($value);
                }

                return $new->save();
            }
            catch (\Exception $e) {
                return null;
            }
        }
    }

    public function update(int $id, array $data = []): bool
    {
        if ($model = $this->find($id)) {
            foreach ($data as $key => $value) {
                $model->$key($value);
            }
        }

        return $model->save();
    }

    public function delete($id): bool
    {
        if ($model = $this->find($id)) {
            return !!$model->delete();
        }

        return false;
    }

    /**
     * @param string $field
     * @param string $value
     * @return Model[]
     * @throws \Exception
     */
    public function where(string $field, string $value): array
    {
        $query = Query::select($this->table)->from()
            ->equals($field, $value);

        $result = $this->db->query($query->sql(), $query->params());
        if (count($result)) {
            return array_map(fn($item) => Model::transform($item), $result);
        }

        return [];
    }

    /**
     * @param string $field
     * @param string $value
     * @return Model|null
     * @throws \Exception
     */
    public function firstWhere(string $field, string $value): ?array
    {
        $query = Query::select($this->table)->from()
            ->equals($field, $value)
            ->order(Model::$primaryKey, Query::ORDER_ASC)
            ->limit(1);

        $result = $this->db->query($query->sql(), $query->params());
        return Model::transform($result[0]);
    }

    /**
     * Count record amount in the database
     * @param array $params optional filters
     * @return int
     */
    public function count(array $params = []): int
    {
        $query = Query::select($this->table)->from()->count();
        return $this->db->query($query->sql())['count'] ?? 0;
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
}