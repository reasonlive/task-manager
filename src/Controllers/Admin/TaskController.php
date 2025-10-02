<?php

namespace App\Controllers\Admin;

use App\Core\Http\BaseController;
use App\Enums\Task\Status as TaskStatus;
use App\Models\Model;
use App\Models\Reply;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;

class TaskController extends BaseController
{
    public bool $admin = true;
    /**
     * GET /admin/tasks
     * @return void
     */
    public function index(): void
    {
        $tasks = Task::getInstance()
            ->findAllWithDetails();

        $this->render('admin/tasks/index.html.twig', [
            'tasks' => $tasks,
            'statuses' => TaskStatus::names(),
            'available_users' => User::getInstance()->findByRole('MODERATOR'),
            'available_tags' => Tag::getInstance()->findAll(),
            'sort_field' => $this->request->get('sort', 'id'),
            'sort_order' => $this->request->get('order', 'asc'),
            'current_filters' => [
                'status' => $this->request->get('status'),
                'tag' => $this->request->get('tag'),
                'user_id' => $this->request->get('user_id'),
            ],
        ]);
    }

    /**
     * GET /admin/tasks/{$id}
     * @param int $id
     * @return void
     */
    public function show(int $id): void
    {
        $task = Task::getInstance()->findByIdWithDetails($id);

        if (!$task) {
            $this->response->setStatusCode(404);
            $this->render('errors/404.html.twig');
            return;
        }

        $replies = Model::getInstance(Reply::class)->findByTaskId($id);

        $this->render('admin/tasks/show.html.twig', [
            'task' => $task,
            'replies' => $replies,
            'statuses' => TaskStatus::names(),
            'tags' => Model::getInstance(Tag::class)->findAll()
        ]);
    }

    /**
     * POST /admin/tags
     * @return void
     */
    public function create(): void
    {
        try {
            $body = $this->request->getBody();
            $id = Model::getInstance(Tag::class)->create($body);
            if ($id > 0) {
                $this->json(['success' => true, 'Task created successfully']);
            }
        } catch (\Exception $e) {
            $this->response
                ->setStatusCode(500)
                ->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/tasks/update
     * @param int $id
     * @return void
     */
    public function update(): void
    {
        $id = $this->request->get('id');
        $status = $this->request->get('status');
        $allowedStatuses = TaskStatus::names();

        if (!in_array($status, $allowedStatuses)) {
            $this->json(['success' => false, 'message' => 'Invalid status'], 400);
        }

        $success = Model::getInstance(Task::class)
            ->update($id, ['status' => $status]);

        if ($success) {
            $this->json(['success' => true, 'message' => 'Status updated successfully']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update status'], 500);
        }
    }

    /**
     * POST /admin/tasks/tags
     * @return void
     */
    public function manageTaskTags(): void
    {
        $id = $this->request->get('task_id');
        $tag = $this->request->get('tag');
        $action = $this->request->get('action');


        if (!$tag = Tag::getInstance()->findByName($tag)) {
            $this->json(['success' => false, 'error' => 'Tag not found'], 404);
            return;
        }

        $task = Task::getInstance();

        if ($action === 'add') {
            $success = $task->addTag($id, $tag['id']);
        } elseif ($action === 'remove') {
            $success = $task->removeTag($id, $tag['id']);
        } else {
            $this->json(['success' => false, 'error' => 'Invalid action'], 400);
            return;
        }

        if ($success) {
            $this->json(['success' => true, 'message' => 'Tag updated successfully']);
        } else {
            $this->json(['success' => false, 'error' => 'Failed to update tag'], 500);
        }
    }

    /**
     * POST /admin/tasks/reply/add
     * @param int $taskId
     * @return void
     */
    public function addReply(): void
    {
        $taskId = $this->request->get('task_id');
        $text = $this->request->get('text');
        $userId = 1; // ID текущего админа (из сессии)

        if (empty($text)) {
            $this->redirect("/admin/tasks/{$taskId}?error=Reply text is required");
            return;
        }

        $success = Model::getInstance(Reply::class)->create([
            'text' => $text,
            'task_id' => $taskId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($success) {
            $this->redirect("/admin/tasks/{$taskId}?success=Reply added successfully");
        } else {
            $this->redirect("/admin/tasks/{$taskId}?error=Failed to add reply");
        }
    }
}