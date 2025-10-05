<?php
declare(strict_types=1);
namespace App\Core\Data\DQL;

use App\Core\Data\DQL\Relationship\ManyToMany;
use App\Core\Data\DQL\Relationship\ManyToOne;
use App\Core\Data\DQL\Relationship\Relation;

class Query
{
    private Stage $stage;
    private Operation $operation;

    private const string FROM = 'FROM';
    public const string JOIN = 'JOIN';
    public const string LEFT_JOIN = 'LEFT JOIN';
    private const string WHERE = 'WHERE';
    private const string GROUP = 'GROUP BY';
    private const string HAVING = 'HAVING';
    private const string LIMIT = 'LIMIT';
    private const string OFFSET = 'OFFSET';
    private const string ORDER = 'ORDER BY';

    private const string ORDER_ASC = 'ASC';
    private const string ORDER_DESC = 'DESC';

    /** @var string|null main table name */
    private ?string $table = null;
    /** @var string|null alias of main table */
    private ?string $alias = null;
    /** @var array fields as table columns with its aliases */
    private array $fields = [];
    /** @var array params for PDO preparation */
    private array $params = [];
    /** @var Relation[] join tables */
    private array $relations = [];
    /** @var array main query parts */
    private array $buffer = [];
    private static bool $ENABLE_PDO_PREPARATION = false;
    private static bool $WHERE_CONDITION_STARTED = false;

    public function __construct(Operation $operation = Operation::SELECT)
    {
        $this->operation = $operation;
        $this->shiftStage(Stage::FIELD_INITIALIZATION);
    }

    public function shiftStage(Stage $stage): self
    {
        switch ($stage) {
            case Stage::FIELD_INITIALIZATION:
                $this->stage = Stage::FIELD_INITIALIZATION;
                $this->buffer[] = $this->operation->value;
                break;
            case Stage::TABLE_DEFINITION:
                $this->stage = Stage::TABLE_DEFINITION;
                if ($this->operation == Operation::SELECT) {
                    $this->buffer[] = self::FROM;
                    $this->buffer[] = $this->table . ($this->alias ? " AS $this->alias" : '');
                }
                break;
            case Stage::WHERE_CONDITION_PHASE:
                if ($this->operation == Operation::DELETE) {
                    $this->buffer[] = $this->table;
                }
                else if ($this->operation == Operation::UPDATE) {
                    $this->drainFields();
                }

                $this->buffer[] = self::WHERE;
                $this->stage = Stage::WHERE_CONDITION_PHASE;
                break;
            case Stage::GROUP_BY_PHASE:
            case Stage::HAVING_PHASE:
                $this->stage = Stage::GROUP_BY_PHASE;
                $this->buffer[] = self::GROUP;
                break;
            case Stage::ORDER_RECORDS_PHASE:
                $this->stage = Stage::ORDER_RECORDS_PHASE;
                $this->buffer[] = self::ORDER;
                break;
            case Stage::LIMIT_RECORDS_PHASE:
                $this->stage = Stage::LIMIT_RECORDS_PHASE;
                $this->buffer[] = self::LIMIT;
                break;
            default:
                $this->stage = Stage::FINAL;
        }

        return $this;
    }

    /**
     * Select factory
     * @param string|null $table
     * @param bool $prepare
     * @return self
     */
    public static function select(?string $table = null, bool $prepare = true): self
    {
        $q = new self(Operation::SELECT);

        if ($prepare) {
            $q->enableParamsPreparation();
        }

        if ($table) {
            $q->table = $table;
            $q->alias = substr($table, 0, 1);
        }

        return $q;
    }

    /**
     * Insert factory
     * @param string|null $table
     * @param bool $prepare
     * @return self
     */
    public static function insert(?string $table = null, bool $prepare = true): self
    {
        $query = new self(Operation::INSERT);

        if ($table !== null) {
            $query->table = $table;
        }

        if ($prepare) {
            $query->enableParamsPreparation();
        }

        return $query;
    }

    /**
     * Update factory
     * @param string|null $table
     * @param string|null $alias
     * @return self
     */
    public static function update(?string $table = null, bool $prepare = true): self
    {
        $query = new self(Operation::UPDATE);

        if ($table !== null) {
            $query->table = $table;
            $query->setAlias(
                substr($query->table, 0, 1)
                . substr($query->table, strlen($query->table) - 2)
            );

            $query->buffer[] = "$query->table AS $query->alias";
        }

        if ($prepare) {
            $query->enableParamsPreparation();
        }

        return $query;
    }

    /**
     * Delete factory
     * @param string|null $table
     * @param bool $prepare
     * @return self
     */
    public static function delete(?string $table = null, bool $prepare = true): self
    {
        $query = new self(Operation::DELETE);

        if ($table !== null) {
            $query->table = $table;
            $query->shiftStage(Stage::WHERE_CONDITION_PHASE);
        }

        if ($prepare) {
            $query->enableParamsPreparation();
        }

        return $query;
    }

    public function enableParamsPreparation(): self
    {
        self::$ENABLE_PDO_PREPARATION = true;
        return $this;
    }

    public function setTable(string $tableName, ?string $alias = null): self
    {
        if ($this->stage === Stage::FIELD_INITIALIZATION) {
            $this->table = $tableName;
        }

        if ($alias !== null) {
            $this->alias = $alias;
        }

        if ($this->operation === Operation::DELETE) {
            $this->shiftStage(Stage::WHERE_CONDITION_PHASE);
        }

        return $this;
    }

    public function setAlias(string $alias): self
    {
        if ($this->stage === Stage::FIELD_INITIALIZATION) {
            $this->alias = $alias;
        }

        return $this;
    }

    public function getAlias(string $table): ?string
    {
        return $this->table === $table ? $this->alias : $this->getRelationAlias($table);
    }

    /**
     * Set fields for select, insert, update operations <br>
     * Warning For select operation, this method turns value into alias name
     * @param string $fieldName
     * @param mixed|null $fieldValue for insert/update
     * @return $this
     * @throws \Exception
     */
    public function setField(string $fieldName, mixed $fieldValue = null): self
    {
        if ($this->stage === Stage::FIELD_INITIALIZATION) {
            $this->fields[$fieldName] = $fieldValue;
        }
        else {
            throw new \Exception("Forbidden method: " . __METHOD__);
        }

        return $this;
    }

    private function drainFields(): void
    {
        if ($this->stage !== Stage::FIELD_INITIALIZATION) {
            throw new \Exception("Forbidden method: " . __METHOD__);
        }

        if ($this->operation === Operation::SELECT) {
            $i = 1;
            foreach ($this->fields as $fieldName => $fieldValue) {
                $item = "$fieldName";

                if (!empty($fieldValue)) {
                    $item .= " AS $fieldValue";
                }

                if ($this->alias) {
                    $item = "$this->alias.$item";
                }

                if ($i < count($this->fields)) {
                    $item .= ', ';
                }

                $this->buffer[] = $item;
                $i++;
            }
        }
        else if ($this->operation === Operation::INSERT) {
            $i = 1;
            $values = [];
            $item = '';
            foreach ($this->fields as $fieldName => $fieldValue) {
                if ($i === 1) {
                    $item .= '(';
                }

                if ($i > 1) {
                    $item .= ", $fieldName";
                }
                else {
                    $item .= $fieldName;
                }

                $values[] = $fieldValue;
                $i++;
            }

            $this->buffer[] = $this->table;
            $this->buffer[] = $item . ')';
            $this->buffer[] = "VALUES";

            $str = '(';
            if (self::$ENABLE_PDO_PREPARATION) {
                $str .= implode(', ', str_split(str_repeat('?', count($values))));
                $this->params = $values;
            }
            else {
                $str .= implode(", ", $values);
            }
            $str .= ')';

            $this->buffer[] = $str;
        }
        else if ($this->operation === Operation::UPDATE) {
            $this->buffer[] = 'SET';
            $i = 1;
            foreach ($this->fields as $fieldName => $fieldValue) {
                $value = self::$ENABLE_PDO_PREPARATION ? "?" : $fieldValue;
                $this->params[] = $fieldValue;
                $item = "$this->alias.$fieldName = $value";

                if ($i < count($this->fields)) {
                    $item .= ',';
                }

                $this->buffer[] = $item;
                $i++;
            }
        }

        $this->fields = [];
    }

    /**
     * Select operation launcher
     * @param string|null $tableName
     * @param string|null $alias
     * @return $this
     */
    public function from(?string $tableName = null, ?string $alias = null): self
    {
        if ($this->operation !== Operation::SELECT) {
            throw new \Exception("Forbidden method: " . __METHOD__);
        }

        if ($tableName) {
            $this->setTable($tableName);
        }

        if ($alias) {
            $this->setAlias($alias);
        }
        else {
            $this->setAlias(substr($this->table, 0, 1));
        }

        if (count($this->fields)) {
            $this->drainFields();
        }

        if ($this->operation === Operation::SELECT && count($this->buffer) < 2) {
            $this->buffer[] = "$this->alias.*";
        }

        $this->shiftStage(Stage::TABLE_DEFINITION);

        return $this;
    }

    private function join(
        string $tableName,
        string $alias,
        string $fk,
        string $type,
    ): self
    {
        if ($this->stage === Stage::TABLE_DEFINITION) {
            $relation = new ManyToOne($tableName, $alias, $fk);
            $this->buffer[] = $relation->build($this->alias, $type)->sql();
            $this->relations[$relation->relation] = $relation;
        }

        return $this;
    }

    /**
     * For many-to-one relations only
     * @param string $tableName
     * @param string $alias
     * @param string $fk
     * @return self
     */
    public function innerJoin(string $tableName, string $alias, string $fk): self
    {
        return $this->join($tableName, $alias, $fk, self::JOIN);
    }

    /**
     * For many-to-one relations only
     * @param string $tableName
     * @param string $alias
     * @param string $fk
     * @return self
     */
    public function leftJoin(string $tableName, string $alias, string $fk): self
    {
        return $this->join($tableName, $alias, $fk, self::LEFT_JOIN);
    }

    /**
     * For many-to-many relations
     * @param string $tableName related table name
     * @param string $mediatorTableName
     * @param string $joinType LEFT JOIN by default
     * @return $this
     */
    public function manyToManyJoin(
        string $tableName,
        string $mediatorTableName,
        string $fk1,
        string $fk2,
        string $joinType = self::LEFT_JOIN
    ): self
    {
        $relation = new ManyToMany($tableName, $mediatorTableName, $fk1, $fk2);
        $this->buffer[] = $relation->build($this->alias, $joinType)->sql();
        $this->relations[$relation->relation] = $relation;

        return $this;
    }

    public function setRelation(Relation $relation): static
    {
        $relation->build($this->table);
        $this->relations[$relation->relation] = $relation;

        return $this;
    }

    /**
     * For relational table's fields
     * @param string $relation
     * @param string $fieldName
     * @param string $alias
     * @param Aggregation|null $aggregation
     * @return $this
     * @throws \Exception
     */
    public function setSelectedField(
        string $relation,
        string $fieldName,
        string $alias,
        ?Aggregation $aggregation = null
    ): self
    {
        if ($aggregation && $this->stage !== Stage::GROUP_BY_PHASE) {
            throw new \Exception("Aggregation is not supported in this stage");
        }

        if ($this->stage === Stage::TABLE_DEFINITION) {
            if (isset($this->relations[$relation])) {
                $this->relations[$relation]->setField($fieldName, $alias);
            }
        }
        else if ($this->stage === Stage::GROUP_BY_PHASE && $aggregation) {
            if (isset($this->relations[$relation])) {
                $t = $this->getRelationAlias($relation) ?? $relation;
                $field = $aggregation->value . "($t.$fieldName)";

                $this->relations[$relation]->setField($field, $alias);
            }
        }

        return $this;
    }

    public function withoutConditions(): self
    {
        $this->stage = Stage::WHERE_CONDITION_PHASE;
        return $this;
    }

    public function withoutGrouping(): self
    {
        $this->stage = Stage::ORDER_RECORDS_PHASE;
        return $this;
    }

    private function getRelationAlias(string $relation): ?string
    {
        if (isset($this->relations[$relation])) {
            return $this->relations[$relation]->alias;
        }

        return null;
    }

    private function where(mixed $field, Comparison $operator, mixed $value, ?string $relatedTable = null): self
    {
        if ($this->stage == Stage::FIELD_INITIALIZATION || $this->stage == Stage::TABLE_DEFINITION) {
            $this->shiftStage(Stage::WHERE_CONDITION_PHASE);
        }

        if ($relatedTable && $alias = $this->getRelationAlias($relatedTable)) {
            $field = $alias . ".$field";
        }

        if (self::$ENABLE_PDO_PREPARATION) {
            $this->buffer[] = "$this->alias.$field " . $operator->value . " ?";
            $this->params[] = $value;
        }
        else {
            $this->buffer[] = "$this->alias.$field " . $operator->value . " $value";
        }

        self::$WHERE_CONDITION_STARTED = true;

        return $this;
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param string|null $table related table name for its field
     * @return self
     * @throws \Exception
     */
    public function equals(string $field, mixed $value, ?string $table = null): self
    {
        return $this->where($field, Comparison::EQUAL, $value, $table);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param string|null $table
     * @return self
     * @throws \Exception
     */
    public function lessThan(string $field, mixed $value, ?string $table = null): self
    {
        return $this->where($field, Comparison::LESS, $value, $table);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param string|null $table
     * @return self
     * @throws \Exception
     */
    public function greaterThan(string $field, mixed $value, ?string $table = null): self
    {
        return $this->where($field, Comparison::MORE, $value, $table);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param string|null $table
     * @return self
     * @throws \Exception
     */
    public function lessOrEqualsThan(string $field, mixed $value, ?string $table = null): self
    {
        return $this->where($field, Comparison::LESS_EQUAL, $value, $table);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param string|null $table
     * @return self
     * @throws \Exception
     */
    public function greaterOrEqualsThan(string $field, mixed $value, ?string $table = null): self
    {
        return $this->where($field, Comparison::MORE_EQUAL, $value, $table);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param string|null $table
     * @return self
     * @throws \Exception
     */
    public function isNull(string $field, mixed $value, ?string $table = null): self
    {
        return $this->where($field, Comparison::NULL, $value, $table);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param string|null $table related table name for field
     * @return self
     * @throws \Exception
     */
    public function isNotNull(string $field, mixed $value, ?string $table = null): self
    {
        return $this->where($field, Comparison::NOT_NULL, $value, $table);
    }

    public function isLike(
        string $field,
        mixed $value,
        ?string $table = null,
        Comparison $comparator = Comparison::LIKE_EXACTLY_MARK
    ): self
    {
        $target = match ($comparator) {
            Comparison::LIKE_START_MARK => "%".$value,
            Comparison::LIKE_END_MARK => $value."%",
            Comparison::LIKE_EXACTLY_MARK => "%".$value."%",
        };

        return $this->where($field, Comparison::LIKE, $target, $table);
    }

    public function and(mixed $field = null, mixed $value = null, ?string $table = null): self
    {
        if ($this->stage === Stage::WHERE_CONDITION_PHASE && self::$WHERE_CONDITION_STARTED) {
            $this->buffer[] = Condition::AND->value;
        }

        if ($field && $value) {
            $this->equals($field, $value, $table);
        }

        return $this;
    }

    public function or(mixed $field, mixed $value, ?string $table = null): self
    {
        if ($this->stage === Stage::WHERE_CONDITION_PHASE && self::$WHERE_CONDITION_STARTED) {
            $this->buffer[] = Condition::OR->value;
        }

        if ($field && $value) {
            $this->equals($field, $value, $table);
        }

        return $this;
    }

    public function group(string $table, string $field): self
    {
        if ($this->stage == Stage::TABLE_DEFINITION || $this->stage == Stage::WHERE_CONDITION_PHASE) {
            $this->shiftStage(Stage::GROUP_BY_PHASE);
        }

        $t = $this->getAlias($table);
        $this->buffer[] = "$t.$field";

        return $this;
    }

    public function order(string $field, string $direction = self::ORDER_DESC): self
    {
        if ($this->stage !== Stage::ORDER_RECORDS_PHASE
            && ($this->stage !== Stage::HAVING_PHASE && $this->stage !== Stage::GROUP_BY_PHASE)
        ) {
            throw new \Exception("Order clause is forbidden here");
        }

        if ($this->stage !== Stage::ORDER_RECORDS_PHASE) {
            $this->shiftStage(Stage::ORDER_RECORDS_PHASE);
        }

        $direction = strtoupper($direction);
        $this->buffer[] = "$this->alias.$field $direction";

        return $this;
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        if ($this->stage !== Stage::ORDER_RECORDS_PHASE || $this->stage === Stage::LIMIT_RECORDS_PHASE) {
            throw new \Exception("Limit clause is forbidden here");
        }

        $this->shiftStage(Stage::LIMIT_RECORDS_PHASE);

        if (self::$ENABLE_PDO_PREPARATION) {
            $this->params[] = $limit;
            $limit = '?';
            if ($offset) {
                $this->params[] = $offset;
                $offset = '?';
            }
        }

        $this->buffer[] = $limit . (is_null($offset) ? "" : " OFFSET $offset");

        if ($offset) {
            $this->shiftStage(Stage::FINAL);
        }

        return $this;
    }

    public function offset(int $offset): self
    {
        if ($this->stage !== Stage::LIMIT_RECORDS_PHASE || $this->stage === Stage::FINAL) {
            throw new \Exception("Offset clause is forbidden here");
        }

        if (self::$ENABLE_PDO_PREPARATION) {
            $this->params[] = $offset;
            $offset = '?';
        }

        $this->buffer[] = self::OFFSET . " " . $offset;
        $this->shiftStage(Stage::FINAL);

        return $this;
    }

    private function build(): string
    {
        if ($this->operation == Operation::INSERT) {
            $this->drainFields();
        }

        if (
            $this->operation == Operation::UPDATE
            && $this->stage !== Stage::WHERE_CONDITION_PHASE
        )
        {
            throw new \Exception("WHERE condition is missed");
        }

        $result = '';

        // check if relations has some fields to select
        if (count($this->relations) && $this->operation === Operation::SELECT) {
            $pos = array_search(self::FROM, $this->buffer);
            $leftPart = array_slice($this->buffer, 0, $pos, true);
            $rightPart = array_slice($this->buffer, $pos, null, true);

            $leftPart[] = array_pop($leftPart) . ",";

            foreach ($this->relations as $relation) {
                // take all fields from relation
                if (empty($relation->getFields())) {
                    $leftPart[] = $relation->alias . ".*,";
                }
                else {
                    foreach ($relation->getFields() as $name => $alias) {
                        if (str_contains($name, '(')) { // for aggregation functions
                            $leftPart[] = "$name AS $alias,";
                        }
                        else {
                            $leftPart[] = $relation->alias . ".$name AS $alias,";
                        }
                    }
                }
            }

            $last = array_pop($leftPart);
            $leftPart[] = substr($last, 0, -1); // deleting last comma

            $this->buffer = $leftPart; //rewrite buffer
            foreach ($rightPart as $chunk) {
                $this->buffer[] = $chunk;
            }
        }

        foreach ($this->buffer as $i => $item) {
            if ($i === 0) {
                $result .= $item;
            }
            else {
                $result .= " $item";
            }
        }

        return $result . ';';
    }

    public function sql(): string
    {
        return $this->build();
    }

    public function dump(): string
    {
        $sql = $this->build();
        error_log($sql);
        var_dump($sql);
        exit;
    }

    /**
     * Params for PDO preparing string if self:
     * @return array
     */
    public function params(): array
    {
        return $this->params;
    }
}