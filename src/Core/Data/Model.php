<?php
declare(strict_types=1);
namespace App\Core\Data;

use App\Core\Data\DQL\Query;

/**
 * @method id()
 */
abstract class Model
{
    protected static string $table = '';
    private static string $schemaPath;
    public static string $primaryKey = 'id';
    private Database $db;
    protected array $fields;

    public function __construct(private bool $new = true)
    {
        self::$schemaPath = realpath(__DIR__ . '/../../../db/schema/');
        $this->db = Database::getInstance();

        if ($data = @file_get_contents(self::$schemaPath . '/' . static::$table . '.schema')) {
            $schema = unserialize($data);
            $this->fields = $schema->fields;
        }
        else {
            $fields = $this->db->getTableFields(static::$table);
            // attaching related objects
            $related = [];
            foreach ($fields as $field) {
                if (str_ends_with($field, '_' . self::$primaryKey)) {
                    $related[] = substr($field, 0, strpos($field, '_' . self::$primaryKey, 0));
                }
            }

            $this->fields = array_fill_keys(array_merge($fields, $related), null);

            file_put_contents(
                self::$schemaPath . '/' . static::$table . '.schema',
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
        if (!static::$table) {
            throw new \Exception('Model table must be defined');
        }

        if (!array_key_exists($name, $this->fields)) {
            // this is related model class
            if (($className = ModelFactory::findModel($name)) && $className !== static::class) {

                $params = json_decode($arguments[0], true);
                if (is_array($params)) {
                    $objects = [];
                    foreach ($params as $item) {
                        $obj = new $className(false);
                        foreach ($item as $k => $v) {
                            $obj->$k($v);
                        }

                        $objects[] = $obj;
                    }

                    $this->fields[$name] = $objects;
                }

                return $this;
            }
            else {
                throw new \Exception("Method $name does not exist");
            }
        }

        if (count($arguments) == 1 && in_array($name, array_keys($this->fields))) {

            if ($name === static::$primaryKey && __CLASS__ !== self::class) {
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
        return static::$table;
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
        $query = Query::select($instance->getTable())->from()->equals(static::$primaryKey, $id);
        $data = Database::getInstance()->query($query->sql(), $query->params());

        if ($data && !isset($data['id'])) {
            $data = $data[0];
        }

        return self::transform($data);
    }

     /**
      * Transform data into object
      * @param array|null $data
      * @return Model|null
      */
    public static function transform(?array $data): ?Model
    {
        $instance = new static(false);

        if ($data) {
            foreach ($data as $key => $value) {
                // values might be for related model
                if (is_array($value)) {
                    // try to attach
                    $reflection = new \ReflectionClass(static::class);
                    $reflection->getProperty('fields')->setAccessible(true);

                    $relatedKey = array_filter(
                        array_keys($reflection->getProperty('fields')->getValue($instance)),
                        fn($item) => str_contains($key, $item) && !str_ends_with($item, '_' . static::$primaryKey)
                    );

                    if (count($relatedKey) === 1) {
                        $relatedKey = reset($relatedKey);

                        if ($relatedClass = ModelFactory::findModel($key)) {
                            $reflection = new \ReflectionClass($relatedClass);
                        }

                        $reflection->setStaticPropertyValue('table', $key);
                        $relation = $reflection->newInstance();

                        foreach ($value as $k => $v) {
                            $relation->{$k}($v);
                        }
                        // attach
                        $instance->{$relatedKey}($relation);
                    }

                    continue;
                }

                $instance->{$key}($value);
            }
            //print_r($instance);exit;
            return $instance;
        }

        return null;
    }

    protected function insert(array $data = []): int
    {
        if (count($filtered = $this->filterFields($data))) {
            $query = Query::insert(static::$table);
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
            $query = Query::update(static::$table);

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
        $query = Query::delete(static::$table)
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
