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

    /**
     * Builds join string
     * @param string $alias table name or table alias
     * @param string|null $joinType
     * @return $this
     */
    public function build(string $alias, ?string $joinType = null): static
    {
        $this->sql = "$joinType $this->relation AS $this->alias ON $alias.$this->fk = $this->alias.id";
        return $this;
    }
}