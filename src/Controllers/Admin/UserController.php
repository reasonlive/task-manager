<?php

namespace App\Controllers\Admin;

use App\Core\Http\BaseController;
use App\Models\Model;
use App\Models\User;
use App\Utils\PasswordHasher;

class UserController extends BaseController
{
    public bool $admin = true;
    /**
     * GET /admin/users
     * @return void
     */
    public function index(): void
    {
        $role = $this->request->get('role');
        $isActive = $this->request->get('is_active');
        $sort = $this->request->get('sort');
        $order = $this->request->get('order');

        $params = [];
        $sortParams = [];

        if ($role) {
            $params['role'] = $role;
        }

        if (is_numeric($isActive)) {
            $params['is_active'] = $isActive;
        }

        if ($sort) {
            $sortParams[] = $sort;
            $sortParams[] = $order ?? 'ASC';
        }

        $users = Model::getInstance(User::class)->findAll($params, $sortParams);

        /*if ($role || $isActive) {
            $users = $this->getFilteredUsers($role, $isActive);
        } else {
            $users = Model::getInstance(User::class)->findAll();
        }*/

        $this->render('admin/users/index.html.twig', ['users' => $users]);
    }

    /**
     * POST /admin/users
     * @return void
     */
    public function create(): void
    {
        if (!$this->request->isPost()) {
            $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        $data = $this->request->getBody();

        // Валидация данных
        $validation = $this->validateUserData($data);
        if (!$validation['success']) {
            $this->json($validation, 400);
            return;
        }

        // Проверяем, существует ли пользователь с таким email
        $existingUser = Model::getInstance(User::class)
            ->findByEmail($data['email']);
        if ($existingUser) {
            $this->json(['success' => false, 'message' => 'User with this email already exists']);
            return;
        }

        // Подготавливаем данные для создания
        $userData = [
            'email' => $data['email'],
            'name' => $data['name'],
            'password' => PasswordHasher::hash($data['password']),
            'role' => $data['role'] ?? 'USER',
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Создаем пользователя
        $userId = Model::getInstance(User::class)
            ->create($userData);

        if ($userId) {
            $this->json(['success' => true, 'message' => 'User created successfully']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to create user'], 422);
        }
    }

    /**
     * POST /admin/users/update
     * @return void
     */
    public function update(): void
    {
        $id = $this->request->get('id');

        if (!$this->request->isPost()) {
            $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        $repository = Model::getInstance(User::class);
        $data = $this->request->getBody();

        // Проверяем существование пользователя
        $user = $repository->findById($id);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found'], 404);
            return;
        }

        $updated = ['updated_at' => date('Y-m-d H:i:s')];

        // Проверяем email на уникальность (исключая текущего пользователя)
        if (isset($data['email'])) {
            $existingUser = $repository->findByEmail($data['email']);
            if ($existingUser && $existingUser['id'] != $id) {
                $this->json(['success' => false, 'message' => 'User with this email already exists']);
                return;
            }

            $updated['email'] = $data['email'];
        }

        if (isset($data['name'])) {
            $updated['name'] = $data['name'];
        }

        if (isset($data['is_active'])) {
            $updated['is_active'] = (int)$data['is_active'];
        }

        if (isset($data['role'])) {
            $updated['role'] = $data['role'];
        }

        // Обновляем пользователя
        $result = $repository->update($id, $updated);
        if ($result) {
            $this->json(['success' => true, 'message' => 'User updated successfully']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update user']);
        }
    }

    /**
     * Просмотр деталей пользователя
     */
    public function show(int $id): void
    {
        $user = Model::getInstance(User::class)
            ->findById($id);

        if ($user) {
            $this->render('admin/users/show.html.twig', ['user' => $user]);
        } else {
            $this->render('errors/404.html.twig');
        }
    }

    /**
     * Вспомогательный метод для фильтрации пользователей
     */
    private function getFilteredUsers(?string $role, ?int $isActive): array
    {
        $users = Model::getInstance(User::class)->findAll();

        // Применяем фильтры
        if ($role !== null) {
            $users = array_filter($users, function($user) use ($role) {
                return $user['role'] === $role;
            });
        }

        if ($isActive !== null) {
            $users = array_filter($users, function($user) use ($isActive) {
                return $user['is_active'] == $isActive;
            });
        }

        return $users;
        //return array_values($users);
    }

    /**
     * Валидация данных пользователя
     */
    private function validateUserData(array $data): array
    {
        if (empty($data['email']) || empty($data['name']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        if (strlen($data['password']) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
        }

        $allowedRoles = ['USER', 'ADMIN', 'MODERATOR'];
        if (isset($data['role']) && !in_array($data['role'], $allowedRoles)) {
            return ['success' => false, 'message' => 'Invalid role'];
        }

        return ['success' => true];
    }
}