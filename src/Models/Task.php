<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Data\DQL\Aggregation;
use App\Core\Data\DQL\Query;

class Task extends Model
{
    protected string $table = 'tasks';
    protected array $fillable = [
        'title',
        'description',
        'status',
        'user_id',
        'created_at',
        'updated_at'
    ];

    public function findAllWithRelations($userId, $status, $tag, $sort, $order): ?array
    {
        $query = Query::select()
            ->enableParamsPreparation()
            ->from('tasks', 't')
            ->innerJoin('users', 'u', 'user_id')
            ->setSelectedField('users', 'name', 'username')
            ->manyToManyJoin('tags', 'task_tags', 'task_id', 'tag_id');

        if ($userId) {
            $query->and('user_id', $userId);
        }

        if ($status && $status !== 'ALL') {
            $query->and('status', $status);
        }

        $query
            ->withoutConditions()
            ->group('tasks', 'id')
            ->setSelectedField('tags', 'name', 'tag', Aggregation::GROUP_CONCAT);

        if ($sort && $order) {
            $query->order($sort, $order);
        }

        //$query->dump();

        return $this->db->query($query->sql(), $query->params()) ?? null;
    }

    public function addTag(int $taskId, int $tagId): bool
    {
        $sql = Query::insert('task_tags')
            ->enableParamsPreparation()
            ->setField('task_id', $taskId)
            ->setField('tag_id', $tagId);

        return $this
                ->db
                ->query($sql->sql(), $sql->params()) > 0;
    }

    public function removeTag(int $taskId, int $tagId): bool
    {
        $query = Query::delete('task_tags')
            ->and('task_id', $taskId)
            ->and('tag_id', $tagId);

        return $this->db->query($query->sql(), $query->params()) > 0;
    }

    public function getTaskTags(int $taskId): array
    {
        $sql = "SELECT tg.* FROM tags tg
                JOIN task_tags tt ON tg.id = tt.tag_id
                WHERE tt.task_id = ?";

        return $this->db->query($sql, [$taskId]);
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

        return $this->db->query($sql, $params) ?: [];
    }
}