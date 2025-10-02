<?php
declare(strict_types=1);
namespace App\Models;

class Task extends Model
{
    protected string $table = 'tasks';
    protected array $fillable = ['title', 'description', 'status', 'user_id', 'created_at', 'updated_at'];

    public function findAllWithDetails(): array
    {
        $sql = "SELECT t.*, u.name as user_name, GROUP_CONCAT(tg.name) as tags
                FROM tasks t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN task_tags tt ON t.id = tt.task_id
                LEFT JOIN tags tg ON tt.tag_id = tg.id
                GROUP BY t.id
                ORDER BY t.created_at DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function findByIdWithDetails(int $id): ?array
    {
        $sql = "SELECT t.*, u.name as user_name, u.email as user_email, 
                       GROUP_CONCAT(tg.name) as tags, GROUP_CONCAT(tg.id) as tag_ids
                FROM tasks t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN task_tags tt ON t.id = tt.task_id
                LEFT JOIN tags tg ON tt.tag_id = tg.id
                WHERE t.id = ?
                GROUP BY t.id";

        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByStatus(string $status): array
    {
        return $this->where('status', $status);
    }

    public function findByUserId(int $userId): array
    {
        return $this->where('user_id', $userId);
    }

    public function addTag(int $taskId, int $tagId): bool
    {
        $sql = "INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES (?, ?)";
        return $this->db->query($sql, [$taskId, $tagId])->rowCount() > 0;
    }

    public function removeTag(int $taskId, int $tagId): bool
    {
        $sql = "DELETE FROM task_tags WHERE task_id = ? AND tag_id = ?";
        return $this->db->query($sql, [$taskId, $tagId])->rowCount() > 0;
    }

    public function getTaskTags(int $taskId): array
    {
        $sql = "SELECT tg.* FROM tags tg
                JOIN task_tags tt ON tg.id = tt.tag_id
                WHERE tt.task_id = ?";

        $stmt = $this->db->query($sql, [$taskId]);
        return $stmt->fetchAll();
    }

    public function getFilteredTasks(
        int $userId,
        ?int $id,
        ?string $title,
        string $status,
        string $sortBy,
        string $sortOrder,
        int $limit,
        int $offset
    ): array
    {
        $sql = "SELECT t.*, u.name as user_name FROM tasks t LEFT JOIN users u ON t.user_id = u.id";

        //return $this->db->query($sql)->fetchAll();
        $whereConditions = [];
        $params = [];

        if ($userId) {
            $whereConditions[] = "t.user_id = ?";
            $params[] = $userId;
        }

        if ($id) {
            $whereConditions[] = "t.id = ?";
            $params[] = $id;
        }

        if ($title) {
            $whereConditions[] = "t.title LIKE ?";
            $params[] = '%' . $title . '%';
        }

        if ($status && $status !== 'ALL') {
            $whereConditions[] = "t.status = ?";
            $params[] = $status;
        }

        if (!empty($whereConditions)) {
            $sql .= " WHERE ";

            foreach ($whereConditions as $i => $cond) {
                if ($i === 0) {
                    $sql .= "$cond";
                }
                else if ($i < count($whereConditions)) {
                    $sql .= " AND $cond";
                } else {
                    $sql .= "$cond";
                }
            }
        }

        // Валидация и экранирование параметров сортировки
        $allowedSortColumns = ['id', 'title', 'status', 'created_at', 'updated_at', 'user_id'];
        $allowedSortOrders = ['ASC', 'DESC'];

        $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'id';
        $sortOrder = in_array(strtoupper($sortOrder), $allowedSortOrders) ? strtoupper($sortOrder) : 'DESC';

        $sql .= " ORDER BY t.{$sortBy} {$sortOrder}";
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->query($sql, $params);
        //print($stmt->queryString);exit;
        $tasks = $stmt->fetchAll();

        return $tasks ?: [];
    }
}