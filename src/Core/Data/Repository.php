<?php

namespace App\Core\Data;

use App\Core\Data\DQL\Aggregation;
use App\Core\Data\DQL\Query;
use App\Core\Data\DQL\Relationship\Relation;
use App\Core\Data\DQL\Relationship\RelationType;

abstract class Repository
{
    protected string $table;
    protected Database $db;
    protected array $relations = [];

    public function __construct(protected string $modelClass)
    {
        $this->table = (new $this->modelClass())->getTable();
        $this->db = Database::getInstance();
    }

    /**
     * TODO: add filtration by params
     * @param array $params
     * @return Model[]
     */
    public function findAll(array $params = []): array
    {
        $query = $this->preprocessQuery(Query::select($this->table)->from());

        if ($query::$GROUPING_REQUIRED) {
            $query->group($this->table, $this->modelClass::$primaryKey);
        }

        $query->order($this->modelClass::$primaryKey);

        return $this->query($query, false);
    }

    public function getIds(?string $field = null, array $values = []): array
    {
        $query = $this->preprocessQuery(
            Query::select($this->table)
                ->setField($this->modelClass::$primaryKey)
                ->setField($field)
                ->from()
        );

        return [];
    }


    public function find(int $id): ?Model
    {
        if (!count($this->relations)) {
            return $this->modelClass::load($id);
        }

        $query = $this->preprocessQuery(Query::select($this->table)->from())->equals($this->modelClass::$primaryKey, $id);

        if ($query::$GROUPING_REQUIRED) {
            $query->group($this->table, $this->modelClass::$primaryKey);
        }

        return $this->query($query, true);
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
     */
    public function where(string $field, string $value): array
    {
        $query = $this->preprocessQuery(Query::select($this->table)->from()->equals($field, $value));

        if ($query::$GROUPING_REQUIRED) {
            $query->group($this->table, $this->modelClass::$primaryKey);
        }

        return $this->query($query, false);
    }

    /**
     * @param string $field
     * @param string $value
     * @return Model|null
     */
    public function firstWhere(string $field, string $value): ?array
    {
        $query = $this->preprocessQuery(Query::select($this->table)->from())->equals($field, $value);
        if ($query::$GROUPING_REQUIRED) {
            $query->group($this->table, $this->modelClass::$primaryKey);
        }

        $query
            ->order($this->modelClass::$primaryKey, Query::ORDER_ASC)
            ->limit(1);

        return $this->query($query, true);
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

    /**
     * @param Query $query
     * @param bool $single
     * @return Model|Model[]
     */
    protected function query(Query $query, bool $single): mixed
    {
        //$query->dump();
        $result = $this->db->query($query->sql(), $query->params());

        if (count($result)) {
            if ($single && isset($result[0])) {
                return $this->modelClass::transform($this->transformRawData($result[0]));
            }

            return array_map(fn($item) => $this->modelClass::transform($this->transformRawData($item)), $result);
        }

        return [];
    }

    protected function transformRawData(array $rawData): array
    {
        $preparedData = [];
        //print_r($rawData);exit;
        if (count($this->relations)) {
            foreach ($rawData as $field => $value) {
                // divide table's names and its fields
                // @see Database::getIntersectingFieldAliases()
                if (count($pair = explode('_', $field)) > 1) {
                    $field = implode('_', array_slice($pair, 1));

                    if (strcasecmp($pair[0], $this->table) === 0) {
                        $preparedData[$field] = $value;
                    }
                    else if (ModelFactory::findModel($pair[0])) {
                        $preparedData[$pair[0]][$field] = $value;
                    }
                    else {
                        $preparedData[implode('_', $pair)] = $value;
                    }
                }
                else {
                    $preparedData[$field] = $value;
                }
            }
        }
        else {
            $preparedData = $rawData;
        }
        //print_r($preparedData);exit;
        return $preparedData;
    }

    public function with(Relation $relation): static
    {
        $this->relations[] = $relation;
        return $this;
    }

    /**
     * Checks additional state
     * @param Query $query
     * @return void
     * @throws \Exception
     */
    private function preprocessQuery(Query $query): Query
    {
        /** @var Relation $relation */
        foreach ($this->relations as $relation) {
            $query->setRelation($relation);

            if ($relation->type == RelationType::MANY_TO_MANY) {
                $query->setSelectedField($relation->relation, [], $relation->relation, Aggregation::JSON_AGG);
            }
        }

        return $query;
    }
}