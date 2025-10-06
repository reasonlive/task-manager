<?php

namespace App\Core\Data\DQL\Relationship;

use App\Core\Data\DQL\Relationship\Relation;

class OneToMany extends Relation
{
    /**
     * @param string $relation Related table
     * @param string $alias
     * @param string $fk
     */
    public function __construct(public string $relation, public string $alias, public string $fk)
    {
        $this->type = RelationType::ONE_TO_MANY;
    }

    public function build(string $table, ?string $joinType = null): static
    {
        $this->sql = "$joinType $this->relation AS $this->alias ON $table.id = $this->alias.$this->fk";
        return $this;
    }
}