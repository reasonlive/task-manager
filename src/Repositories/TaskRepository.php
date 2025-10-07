<?php

namespace App\Repositories;

use App\Core\Data\Repository;
use App\Models\Task;

class TaskRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Task::class);
    }
}