<?php
declare(strict_types=1);
namespace App\Models;

class Tag extends Model
{
    protected string $table = 'tags';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name'];

    public function __construct()
    {
        parent::__construct();
    }

    public function findByName(string $name): ?array
    {
        return $this->firstWhere('name', $name);
    }

    public function getNames(): array
    {
        $sql = "SELECT name FROM tags";
        return array_column($this->db->query($sql)->fetchAll(), 'name');
    }

    public function getPopularTags(int $limit = 10): array
    {
        $sql = "SELECT t.*, COUNT(tt.task_id) as usage_count
                FROM tags t
                LEFT JOIN task_tags tt ON t.id = tt.tag_id
                GROUP BY t.id
                ORDER BY usage_count DESC
                LIMIT ?";

        $stmt = $this->db->query($sql, [$limit]);
        return $stmt->fetchAll();
    }

    public function getTagsWithTaskCount(): array
    {
        $sql = "SELECT t.*, COUNT(tt.task_id) as task_count
                FROM tags t
                LEFT JOIN task_tags tt ON t.id = tt.tag_id
                GROUP BY t.id
                ORDER BY t.name";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function createMany(array $tagNames): bool
    {
        try {
            $this->beginTransaction();

            $sql = "INSERT IGNORE INTO tags (name) VALUES (?)";
            $stmt = $this->db->prepare($sql);

            foreach ($tagNames as $name) {
                $stmt->execute([trim($name)]);
            }

            $this->commit();
            return true;

        } catch (\Exception $e) {
            $this->rollBack();
            return false;
        }
    }
}