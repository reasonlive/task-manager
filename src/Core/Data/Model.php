<?php
declare(strict_types=1);
namespace App\Core\Data;

use App\Core\Data\DQL\Query;

/**
 * @method id()
 */
 class Model
{
    private static string $schemaPath;
    public static string $primaryKey = 'id';
    private Database $db;

    protected string $table;
    protected array $fields;

    protected function __construct(private bool $new = true)
    {
        self::$schemaPath = realpath(__DIR__ . '/../../../db/schema/');
        $this->db = Database::getInstance();
        $this->table = 'tasks';

        if ($data = @file_get_contents(self::$schemaPath . '/' . $this->table . '.schema')) {
            $schema = unserialize($data);
            $this->fields = $schema->fields;
        }
        else {
            $fields = $this->db->getTableFields($this->table);
            $this->fields = array_fill_keys($fields, null);

            file_put_contents(
                self::$schemaPath . '/' . $this->table . '.schema',
                serialize($this),
            );
        }
    }

    public function __serialize(): array
    {
        return $this->fields;
    }

    public function __unserialize(array $data): void
    {
        $this->fields = $data;
    }

    public function __call($name, $arguments)
    {
        if (count($arguments) == 1 && in_array($name, array_keys($this->fields))) {
            if ($name === self::$primaryKey && get_called_class() !== self::class) {
                throw new \Exception('Model primary key cannot be set');
            }

            $this->fields[$name] = $arguments[0];
            return $this;
        }

        if (!count($arguments) && in_array($name, array_keys($this->fields))) {
            return $this->fields[$name];
        }
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Create or update object
     * @return int|bool int for creating
     */
    public function save(): int|bool
    {
        if ($this->new) {
            return $this->insert($this->fields);
        }
        else {
            return $this->update($this->id(), $this->fields);
        }
    }

    public static function load(int $id): ?Model
    {
        $instance = new static(false);
        $query = Query::select($instance->getTable())->from()->equals(self::$primaryKey, $id);

        return self::transform(Database::getInstance()->query($query->sql(), $query->params()));
    }

     /**
      * Transform data into object
      * @param array|null $data
      * @return Model|null
      */
    public static function transform(?array $data): ?Model
    {
        $instance = new static(false);

        if ($data && count($data) == 1) {
            $data = reset($data);

            foreach ($data as $key => $value) {
                $instance->{$key}($value);
            }

            return $instance;
        }

        return null;
    }

    protected function insert(array $data = []): int
    {
        if (count($filtered = $this->filterFields($data))) {
            $query = Query::insert($this->table);
            foreach ($filtered as $field => $value) {
                $query->setField($field, $value);
            }

            if ($success = $this->db->query($query->sql(), $query->params())) {
                return $this->db->lastInsertId();
            }
        }

        return 0;
    }

    protected function update(int $id, array $data = []): bool
    {
        if (count($filtered = $this->filterFields($data))) {
            $query = Query::update($this->table);

            foreach ($filtered as $field => $value) {
                $query->setField($field, $value);
            }

            $query->equals(self::$primaryKey, $id);

            return !!$this->db->query($query->sql(), $query->params());
        }

        return false;
    }

    public function delete(): int
    {
        $query = Query::delete($this->table)
            ->equals(self::$primaryKey, $this->fields[self::$primaryKey]);
        ;

        return $this->db->query($query->sql(), $query->params());
    }

    private function filterFields(array $data): array
    {
        $filtered = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $this->fields) && $field !== self::$primaryKey) {
                $filtered[$field] = $value;
            }
        }

        return $filtered;
    }
}
