<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\Http\BaseController;
use App\Models\Task;

class AdminController extends BaseController
{
    public bool $admin = true;
    private Task $taskModel;

    public function __construct()
    {
        parent::__construct();
        $this->taskModel = new Task();
    }

    public function dashboard(): void
    {
        $tasks = $this->taskModel->findAllWithDetails();
        $stats = $this->getTaskStats($tasks);

        $this->render('admin/dashboard.html.twig', [
            'tasks' => $tasks,
            'stats' => $stats
        ]);
    }

    private function getTaskStats(array $tasks): array
    {
        $stats = [
            'total' => count($tasks),
            'todo' => 0,
            'in_progress' => 0,
            'ready' => 0,
            'for_review' => 0,
            'done' => 0
        ];

        foreach ($tasks as $task) {
            if (isset($stats[$task['status']])) {
                $stats[$task['status']]++;
            }
        }

        return $stats;
    }
}