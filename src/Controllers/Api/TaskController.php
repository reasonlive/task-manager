<?php


namespace App\Controllers\Api;

use App\Core\Http\BaseController;
use App\Enums\Task\Status;
use App\Models\Model;
use App\Models\Task;
use Exception;

class TaskController extends BaseController
{
    public bool $api = true;
    private Task $taskRepository;

    public function __construct()
    {
        parent::__construct();
        $this->taskRepository = Task::getInstance();
    }

    /**
     * GET /api/tasks - Получить список задач с фильтрацией, сортировкой и пагинацией
     */
    public function index(): void
    {
        try {
            $user = $this->getUser();
            // Получаем параметры запроса
            $page = $this->request->get('page', 1);
            $perPage = $this->request->get('per_page', 5);
            $id = $this->request->get('id');
            $title = $this->request->get('title');
            $status = $this->request->get('status', 'ALL');
            $userId = $user['id'];
            $sortField = $this->request->get('sort_field', 'id');
            $sortOrder = strtoupper($this->request->get('sort_order', 'desc'));

            $filterFields = ['user_id' => $userId];
            if ($status !== 'ALL') {
                $filterFields['status'] = $status;
            }

            // Валидация параметров сортировки
            $allowedSortFields = ['id', 'created_at', 'updated_at', 'title'];
            $allowedSortOrders = ['ASC', 'DESC'];

            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'created_at';
            }
            if (!in_array(strtoupper($sortOrder), $allowedSortOrders)) {
                $sortOrder = 'DESC';
            }

            // Рассчитываем смещение для пагинации
            $offset = ($page - 1) * $perPage;

            $tasks = $this->taskRepository->getFilteredTasks(
                $user['id'],
                $id,
                $title,
                $status,
                $sortField,
                $sortOrder,
                $perPage,
                $offset
            );


            $totalCount = $this->taskRepository->count($filterFields);
            $totalPages = ceil($totalCount / $perPage);

            $this->json([
                'success' => true,
                'data' => $tasks,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_items' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'filters' => [
                    'status' => $status,
                    'user_id' => $userId,
                    'sort_field' => $sortField,
                    'sort_order' => $sortOrder
                ]
            ]);

        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/tasks/{id} - Получить задачу по ID
     */
    public function show($id): void
    {
        try {
            $user = $this->getUser();

            if (!isset($id)) {
                $this->json([
                    'success' => false,
                    'error' => 'ID задачи обязателен'
                ], 400);
                return;
            }

            $task = $this->taskRepository->findById((int)$id);

            if (!$task || $task['user_id'] !== $user['id']) {
                $this->json([
                    'success' => false,
                    'error' => 'Задача не найдена'
                ], 404);
                return;
            }

            $this->json([
                'success' => true,
                'data' => $task
            ]);

        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/tasks/create - Создать новую задачу
     */
    public function create(): void
    {
        try {
            $user = $this->getUser();
            $input = $this->request->getBody();

            // Валидация обязательных полей
            $errors = $this->validateTaskData($input);
            if (!empty($errors)) {
                $this->json([
                    'success' => false,
                    'error' => 'Ошибки валидации',
                    'errors' => $errors
                ], 400);
                return;
            }

            // Подготавливаем данные для создания
            $taskData = [
                'title' => $input['title'],
                'description' => $input['description'] ?? '',
                'status' => $input['status'] ?? 'TODO',
                'user_id' => $user['id'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Создаем задачу
            $taskId = $this->taskRepository->create($taskData);

            if (!$taskId) {
                $this->json([
                    'success' => false,
                    'error' => 'Ошибка при создании задачи'
                ], 500);
                return;
            }

            // Добавляем теги, если они указаны
            if (isset($input['tags']) && is_array($input['tags'])) {
                foreach ($input['tags'] as $tagId) {
                    $this->taskRepository->addTag($taskId, (int)$tagId);
                }
            }

            // Получаем созданную задачу с деталями
            $task = $this->taskRepository->findById($taskId);

            $this->json([
                'success' => true,
                'message' => 'Задача успешно создана',
                'data' => $task
            ], 201);

        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => 'Ошибка при создании задачи: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/tasks/update - Обновить задачу
     */
    public function update(): void
    {
        try {
            $input = $this->request->getBody();

            if (!isset($input['id'])) {
                $this->json([
                    'success' => false,
                    'error' => 'ID задачи обязателен'
                ], 400);
                return;
            }

            // Проверяем существование задачи
            $existingTask = $this->taskRepository->findById((int)$input['id']);
            if (!$existingTask) {
                $this->json([
                    'success' => false,
                    'error' => 'Задача не найдена'
                ], 404);
                return;
            }

            // Подготавливаем данные для обновления
            $updateData = [];
            $allowedFields = ['title', 'description', 'status', 'user_id'];

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }

            $updateData['updated_at'] = date('Y-m-d H:i:s');

            if (empty($updateData)) {
                $this->json([
                    'success' => false,
                    'error' => 'Нет данных для обновления'
                ], 400);
                return;
            }

            // Обновляем задачу
            $success = $this->taskRepository->update((int)$input['id'], $updateData);

            if (!$success) {
                $this->json([
                    'success' => false,
                    'error' => 'Ошибка при обновлении задачи'
                ], 500);
                return;
            }

            // Обновляем теги, если они указаны
            if (isset($input['tags'])) {
                // Сначала удаляем все текущие теги
                $currentTags = $this->taskRepository->getTaskTags((int)$input['id']);
                foreach ($currentTags as $tag) {
                    $this->taskRepository->removeTag((int)$input['id'], $tag['id']);
                }

                // Добавляем новые теги
                if (is_array($input['tags'])) {
                    foreach ($input['tags'] as $tagId) {
                        $this->taskRepository->addTag((int)$input['id'], (int)$tagId);
                    }
                }
            }

            // Получаем обновленную задачу
            $task = $this->taskRepository->findById((int)$input['id']);

            $this->json([
                'success' => true,
                'message' => 'Задача успешно обновлена',
                'data' => $task
            ]);

        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => 'Ошибка при обновлении задачи: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /tasks/destroy/{$id} - Удалить задачу
     */
    public function destroy($id): void
    {
        try {
            $user = $this->getUser();

            if (!isset($id)) {
                $this->json([
                    'success' => false,
                    'error' => 'ID задачи обязателен'
                ], 400);
                return;
            }

            // Проверяем существование задачи
            $existingTask = $this->taskRepository->findById((int)$id);
            if (!$existingTask) {
                $this->json([
                    'success' => false,
                    'error' => 'Задача не найдена'
                ], 404);
                return;
            }

            if ($existingTask['user_id'] !== $user['id']) {
                $this->json(['success' => false, 'error' => 'Forbidden'], 403);
            }

            // Удаляем задачу
            $success = $this->taskRepository->delete((int)$id);

            if (!$success) {
                $this->json([
                    'success' => false,
                    'error' => 'Ошибка при удалении задачи'
                ], 500);
                return;
            }

            $this->json([
                'success' => true,
                'message' => 'Задача успешно удалена'
            ]);

        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => 'Ошибка при удалении задачи: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Валидация данных задачи
     */
    private function validateTaskData(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors['title'] = 'Название задачи обязательно';
        } elseif (strlen($data['title']) > 255) {
            $errors['title'] = 'Название задачи не должно превышать 255 символов';
        }

        if (isset($data['description']) && strlen($data['description']) > 1000) {
            $errors['description'] = 'Описание не должно превышать 1000 символов';
        }

        if (isset($data['status']) && !in_array($data['status'], Status::names())) {
            $errors['status'] = 'Недопустимый статус задачи';
        }

        if (isset($data['user_id']) && $data['user_id'] <= 0) {
            $errors['user_id'] = 'Недопустимый ID пользователя';
        }

        return $errors;
    }
}