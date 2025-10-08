<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Http\BaseController;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Utils\PasswordHasher;

class AuthController extends BaseController
{
    protected UserRepository $userRepository;
    public function __construct()
    {

        parent::__construct();
        $this->userRepository = new UserRepository();
    }

    /**
     * GET /
     * @return void
     */
    public function home(): void
    {
        $this->redirect('/login');
    }

    public function register(): void
    {
        // Получаем данные из запроса
        $username = $this->request->get('username');
        $email = $this->request->get('email');
        $password = $this->request->get('password');
        $confirmPassword = $this->request->get('confirm_password');

        $errors = $this->validateRegistration($username, $email, $password, $confirmPassword);

        if (!empty($errors)) {
            // Если есть ошибки, показываем форму с ошибками
            $data = [
                'title' => 'Register - Task Manager',
                'page_title' => 'Create Account',
                'errors' => $errors,
                'old' => [
                    'username' => $username,
                    'email' => $email
                ]
            ];

            $this->render('auth/register.html.twig', $data);
            return;
        }

        $this->userRepository->create([
            'name' => $username,
            'email' => $email,
            'password' => PasswordHasher::hash($password),
            'role' => 'ADMIN'
        ]);

        $this->redirect('/login');
    }

    /**
     * POST /login
     * Обработать данные входа
     */
    public function login(): void
    {
        if ($this->request->isGet()) {
            if ($this->session->getCurrentUser()) {
                $this->redirect('/admin/dashboard');
            } else {
                $this->render('auth/login.html.twig');
            }

            return;
        }

        // Получаем данные из запроса
        $email = $this->request->get('email');
        $password = $this->request->get('password');
        $remember = $this->request->get('remember', false);

        // Валидация данных
        $errors = $this->validateLogin($email, $password);
        if (!empty($errors)) {
            // Если есть ошибки, показываем форму с ошибками
            $data = [
                'title' => 'Login - Task Manager',
                'page_title' => 'Login',
                'errors' => $errors,
                'old' => ['email' => $email]
            ];
            $this->render('auth/login.html.twig', $data);
            return;
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user || !PasswordHasher::verify($password, $user->password())) {
            $this->json(['success' => false, 'message' => 'Invalid username or password.'], 400);
        }


        if ($this->session->login($user)) {
            // После успешного входа перенаправляем на главную страницу
            $this->redirect('/admin');
        } else {
            $data = [
                'title' => 'Login - Task Manager',
                'page_title' => 'Login',
                'error_message' => 'Invalid username or password.',
                'old' => ['email' => $email]
            ];

            $this->render('auth/login.html.twig', $data);
        }
    }

    /**
     * Валидация данных регистрации
     */
    private function validateRegistration(?string $username, ?string $email, ?string $password, ?string $confirmPassword): array
    {
        $errors = [];

        if (empty($username)) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        }

        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }

        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        return $errors;
    }

    /**
     * Валидация данных входа
     */
    private function validateLogin(?string $email, ?string $password): array
    {
        $errors = [];

        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required';
        }

        return $errors;
    }

    /**
     * Выход из системы
     */
    public function logout(): void
    {
        $this->session->logout();
        $this->redirect('/');
    }
}