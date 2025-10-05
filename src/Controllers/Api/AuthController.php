<?php

namespace App\Controllers\Api;

use App\Core\Auth\JwtService;
use App\Core\Http\BaseController;
use App\Models\Model;
use App\Models\User;
use App\Utils\PasswordHasher;

class AuthController extends BaseController
{
    private JwtService $authService;
    private User $userRepository;
    public function __construct()
    {
        parent::__construct();
        $this->authService = new JwtService();
        $this->userRepository = User::getInstance();
    }
    public function register()
    {
        $body = $this->request->getBody();

        if (!isset($body['email']) || !isset($body['password'])) {
            $this->json(['success' => false, 'error' => 'Email and password cannot be empty.'], 400);
        }

        if ($this->userRepository->findByEmail($body['email'])) {
            $this->json(['success' => false, 'error' => 'Email already exists.'], 400);
        }

        $id = $this->userRepository->create([
            'name' => $body['name'],
            'email' => $body['email'],
            'password' => PasswordHasher::hash($body['password'])
        ]);

        if (!$id) {
            $this->json(['success' => false, 'error' => 'Error creating user'], 500);
        } else {
            $this->json(['success' => true, 'id' => $id, 'message' => 'User created successfully.']);
        }
    }

    public function login()
    {
        $body = $this->request->getBody();

        if (!isset($body['email']) || !isset($body['password'])) {
            $this->json(['success' => false, 'error' => 'Email and password cannot be empty.'], 400);
        }

        $user = $this->userRepository->findByEmail($body['email']);

        if (!$user || !PasswordHasher::verify($body['password'], $user['password'])) {
            $this->json(['success' => false, 'error' => 'User not found.'], 404);
        }

        $token = $this->authService->createToken([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        $this->json(['success' => true, 'token' => $token]);
    }
}