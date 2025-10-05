<?php

namespace App\Core\Data\DQL\Relationship;

abstract class Relation
{
    public string $relation;
    public string $alias;
    public string $fk;

    public RelationType $type;

    protected string $sql = '';
    private array $fields = [];

    /**
     * @param string $table table name or alias
     * @param string $joinType
     * @return $this
     */
    public abstract function build(string $table, string $joinType): static;
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * TODO: implement logic without any fields
     * @param string $field
     * @param string $alias
     * @return $this
     */
    public function setField(string $field, string $alias): static
    {
        $this->fields[$field] = $alias;
        return $this;
    }
    public function sql(): string
    {
        return $this->sql;
    }
}