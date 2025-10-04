<?php

namespace App\Core\Data\DQL\Relationship;

class ManyToOne extends Relation
{
    /**
     * @param string $relation Related table
     * @param string $alias
     * @param string $fk
     */
    public function __construct(public string $relation, public string $alias, public string $fk)
    {
        $this->type = RelationType::MANY_TO_ONE;
    }
    public function build(string $table, string $joinType): static
    {
        $this->sql = "$joinType $this->relation AS $this->alias ON $table.$this->fk = $this->alias.id";
        return $this;
    }
}