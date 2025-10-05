<?php

namespace App\Core\Data\DQL\Relationship;

use App\Core\Data\DQL\Query;

class ManyToMany extends Relation
{
    private array $fks = [];

    public function __construct(
        public string $relation,
        private readonly string $mediator,
        string $baseTableForeignKey,
        string $relatedTableForeignKey,
    )
    {
        $this->type = RelationType::MANY_TO_MANY;
        $this->fks[] = $baseTableForeignKey;
        $this->fks[] = $relatedTableForeignKey;
    }

    private function makeAlias(string $value): string
    {
        $alias = '';
        if (str_contains($value, '_')) {
            $value = explode('_', $value);
            $alias .= substr($value[0], 0, 2);
            $alias .= substr($value[1], 2);
        }
        else {
            $alias = substr($value, 0, 2)
                .
                '_'
                . substr(str_shuffle(MD5(microtime())), 0, 5);
        }

        return $alias;
    }

    /**
     * @param string $table1fk key related to first main table
     * @param string $table2fk key to related table
     * @return $this
     */
    public function setForeignKeys(string $table1fk, string $table2fk): self
    {
        $this->fks[] = $table1fk;
        $this->fks[] = $table2fk;
        return $this;
    }

    public function build(string $table, ?string $joinType = Query::LEFT_JOIN): static
    {
        if (!count($this->fks)) {
            throw new \Exception("Many-to-many foreign keys are required");
        }

        [$fk1, $fk2] = $this->fks;

        $alias2 = $this->makeAlias($this->relation);
        $alias3 = $this->makeAlias($this->mediator);

        $this->alias = $alias2;

        $this->sql .= "$joinType $this->mediator AS $alias3 ON $table.id = $alias3.$fk1 ";
        $this->sql .= "$joinType $this->relation AS $alias2 ON $alias3.$fk2 = $alias2.id";

        return $this;
    }

    public function sql(): string
    {
        return $this->sql;
    }
}