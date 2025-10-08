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
     * @param string $alias table name or alias
     * @param string $joinType
     * @return $this
     */
    public abstract function build(string $alias, ?string $joinType): static;
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

    /**
     * One-to-many relation
     * @param string $relatedTable table name
     * @param string $targetFK foreign key from selected table
     * @return static
     */
    public static function oneToMany(string $relatedTable, string $targetFK): static
    {
        return new OneToMany($relatedTable, substr($relatedTable, 0, 1), $targetFK);
    }

    /**
     * Many-to-one relation
     * @param string $relatedTable table name
     * @param string $targetFK foreign key from selected table
     * @return static
     */
    public static function manyToOne(string $relatedTable, string $targetFK): static
    {
        return new ManyToOne($relatedTable, substr($relatedTable, 0, 1), $targetFK);
    }

    /**
     * Many-to-many relation
     * @param string $relatedTable related table name
     * @param string $mediatorTable mediator table name
     * @param string $targetFK foreign key as presents selected table in mediator table
     * @param string $relatedFK foreign key as presents related table in mediator table
     * @return static
     */
    public static function manyToMany(string $relatedTable, string $mediatorTable, string $targetFK, string $relatedFK): static
    {
        return new ManyToMany($relatedTable, $mediatorTable, $targetFK, $relatedFK);
    }
}