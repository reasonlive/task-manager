<?php
declare(strict_types=1);
namespace App\Core\Auth;

use App\Core\Env;
use App\Models\User;
use InvalidArgumentException;

class SessionService
{
    private string $sessionKey;
    private int $sessionLifetime;
    private bool $regenerateId = true;

    public function __construct()
    {
        $this->sessionKey = ENV::get("SESSION_KEY", 'auth');
        $this->sessionLifetime = (int)Env::get("SESSION_LIFETIME", 86400);
        $this->initializeSession();
    }

    /**
     * Инициализация сессии с настройками безопасности
     */
    private function initializeSession(): void
    {
        session_start();

        if (session_status() === PHP_SESSION_NONE) {
            // Настройки безопасности сессии
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');

            session_set_cookie_params([
                'lifetime' => $this->sessionLifetime,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }

        // Регенерация ID сессии для защиты от fixation атак
        if ($this->regenerateId && empty($_SESSION['_generated'])) {
            session_regenerate_id(true);
            $_SESSION['_generated'] = time();
        }
    }

    /**
     * Аутентификация пользователя
     */
    public function login(User $user): bool
    {
        // Базовая валидация данных пользователя
        if (empty($user->id()) || empty($user->email())) {
            throw new InvalidArgumentException('User data must contain id and email');
        }

        // Очищаем предыдущую сессию
        //$this->logout();
        // Сохраняем данные пользователя
        $_SESSION[$this->sessionKey] = [
            'id' => (int)$user->id(),
            'name' => $user->name() ?? "noname",
            'email' => $user->email(),
            'role' => $user->role() ?? 'USER',
            'login_time' => time(),
            'last_activity' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        // Регенерируем ID сессии после логина
        return session_regenerate_id(true);
    }

    /**
     * Выход пользователя
     */
    public function logout(): void
    {
        // Очищаем данные сессии
        unset($_SESSION[$this->sessionKey]);

        // Уничтожаем сессию
        session_destroy();

        // Очищаем cookie сессии
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    /**
     * Проверка аутентификации пользователя
     */
    public function isAuthenticated(): bool
    {
        if (empty($_SESSION[$this->sessionKey])) {
            return false;
        }

        $user = $_SESSION[$this->sessionKey];

        // Проверяем время жизни сессии
        if (time() - $user['last_activity'] > $this->sessionLifetime) {
            $this->logout();
            return false;
        }

        // Проверяем IP адрес (опционально)
        if ($user['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
            $this->logout();
            return false;
        }

        // Проверяем User-Agent (опционально)
        if ($user['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            // Можно добавить логирование подозрительной активности
            return false;
        }

        // Обновляем время последней активности
        $_SESSION[$this->sessionKey]['last_activity'] = time();

        return true;
    }

    /**
     * Получение данных текущего пользователя
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $_SESSION[$this->sessionKey];
    }

    /**
     * Получение ID текущего пользователя
     */
    public function getUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user['id'] ?? null;
    }

    /**
     * Проверка роли пользователя
     */
    public function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === $role;
    }

    /**
     * Проверка нескольких ролей
     */
    public function hasAnyRole(array $roles): bool
    {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role'], $roles, true);
    }

    /**
     * Обновление данных пользователя в сессии
     */
    public function updateUserData(array $newData): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        // Сохраняем системные поля
        $systemFields = ['id', 'login_time', 'last_activity', 'ip_address', 'user_agent'];
        $currentData = $_SESSION[$this->sessionKey];

        foreach ($systemFields as $field) {
            if (isset($newData[$field])) {
                unset($newData[$field]);
            }
        }

        $_SESSION[$this->sessionKey] = array_merge($currentData, $newData);
        return true;
    }

    /**
     * Установка времени жизни сессии
     */
    public function setSessionLifetime(int $seconds): void
    {
        $this->sessionLifetime = $seconds;

        // Обновляем параметры cookie
        $params = session_get_cookie_params();

        session_set_cookie_params([
            'lifetime' => $seconds,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite']
        ]);
    }

    /**
     * Получение оставшегося времени сессии
     */
    public function getRemainingSessionTime(): int
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return 0;
        }

        $elapsed = time() - $user['last_activity'];

        return max(0, $this->sessionLifetime - $elapsed);
    }
}