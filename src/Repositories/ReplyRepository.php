<?php

namespace App\Repositories;

use App\Core\Data\DQL\Query;
use App\Core\Data\Repository;
use App\Models\Reply;

class ReplyRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Reply::class);
    }

    /**
     * @param int $taskId
     * @return array
     */
    public function findByTaskId(int $taskId): array
    {
        $query = Query::select($this->table)->from()->equals('task_id', $taskId);
        return $this->query($query, false);
    }
}