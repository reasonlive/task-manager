<?php
declare(strict_types=1);
namespace App\Core\Data;

use App\Core\Data\DQL\Operation;
use App\Core\Env;
use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    private function __construct()
    {
        $config = [
            'driver' => Env::get('DB_DRIVER'),
            'host' => Env::get('DB_HOST', 'localhost'),
            //'port' => Env::get('DB_PORT', '3306'),
            'database' => Env::get('DB_NAME', 'test'),
            'username' => Env::get('DB_USER', 'root'),
            'password' => Env::get('DB_PASS', ''),
            'charset' => Env::get('DB_CHARSET', 'utf8'),
        ];

        $dsn = "{$config['driver']}:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, ?array $params = null): array|int
    {
        if (str_starts_with($sql, Operation::SELECT->value)) {
            $sql = $this->analyzeAndPrepare($sql);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        if (
            str_starts_with($sql, Operation::INSERT->value)
            || str_starts_with($sql, Operation::DELETE->value)
            || str_starts_with($sql, Operation::UPDATE->value)
        ) {
            return $stmt->rowCount();
        }

        return $stmt->fetchAll();
    }

    public function lastInsertId(): int
    {
        $id = $this->connection->lastInsertId();
        return $id ? (int)$id : 0;
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    public function getTableFields(string $tableName): array
    {
        $database = Env::get('DB_NAME');
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$database' AND TABLE_NAME='$tableName' ORDER BY ORDINAL_POSITION";
        return $this->connection->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getIntersectingFieldAliases(array $tables): array
    {
        $database = Env::get('DB_NAME');

        $sql = '';
        $i = 1;
        foreach ($tables as $table => $alias) {
            $sql .= "SELECT CONCAT('{$alias}.', COLUMN_NAME, ' AS {$table}_', COLUMN_NAME) as column_alias
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = '{$database}' AND TABLE_NAME = '{$table}'";

            if ($i < count($tables)) {
                $sql .= " UNION ALL ";
            }

            $i++;
        }

        return $this->connection->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Analyze SELECT queries for matching * signs
     * @param string $sql
     * @return string
     */
    private function analyzeAndPrepare(string $sql): string
    {
        $tables = [];
        $aliases = [];

        preg_match_all('/(\w+)\.\*/', $sql, $matches, PREG_SET_ORDER);
        if (count($matches) > 1) { // if not only main table is present
            foreach ($matches as $match) {
                $aliases[] = $match[1];
            }
            preg_match('/FROM\s(\w+)/', $sql, $matches);
            $tables[] = $matches[1];

            preg_match_all('/JOIN\s+(\w+)/i', $sql, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $tables[] = $match[1];
            }

            if (count($tables) !== count($aliases)) {
                $tables = array_filter($tables, fn($item) =>
                !(str_contains($item, '_')
                    && (!in_array(explode('_', $item)[0], $tables)
                    || !in_array(explode('_', $item)[1], $tables))
                ));
            }

            $tables = array_combine($tables, $aliases);
            $fieldsString = implode(',', $this->getIntersectingFieldAliases($tables));

            return "SELECT " . $fieldsString . " " . substr($sql, strpos($sql, 'FROM'));
        }

        return $sql;
    }
}