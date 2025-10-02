<?php
declare(strict_types=1);
namespace App\Models;

class Reply extends Model
{
    protected string $table = 'replies';
    protected array $fillable = ['text', 'task_id', 'user_id', 'created_at', 'updated_at'];

    public function __construct()
    {
        parent::__construct();
    }

    public function findByTaskId(int $taskId): array
    {
        $sql = "SELECT r.*, u.name as user_name, u.email as user_email
                FROM replies r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.task_id = ?
                ORDER BY r.created_at ASC";

        $stmt = $this->db->query($sql, [$taskId]);
        return $stmt->fetchAll();
    }

    public function findByUserId(int $userId): array
    {
        return $this->where('user_id', $userId);
    }

    public function getRepliesWithTaskInfo(int $userId = null): array
    {
        $sql = "SELECT r.*, u.name as user_name, t.title as task_title
                FROM replies r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN tasks t ON r.task_id = t.id";

        $params = [];

        if ($userId) {
            $sql .= " WHERE r.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
}