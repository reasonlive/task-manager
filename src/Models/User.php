<?php
declare(strict_types=1);
namespace App\Models;

class User extends Model
{
    protected string $table = 'users';

    /** @var array|string[] fields for creating and updating */
    protected array $fillable = [
        'email',
        'name',
        'role',
        'is_active',
        'password',
        'created_at',
        'updated_at'
    ];

    public function find(int $id): ?array
    {
        $user = $this->findById($id);

        if (isset($user['password'])) {
            unset($user['password']);
        }

        return $user;
    }

    public function findActiveUsers(): array
    {
        return $this->where('is_active', 1);
    }

    public function findByRole(string $role): array
    {
        $user = $this->where('role', strtoupper($role));
        if (isset($user['password'])) {
            unset($user['password']);
        }

        return $user;
    }

    public function findByEmail(string $email): ?array
    {
        $user = $this->firstWhere('email', $email);
        /*if (isset($user['password'])) {
            unset($user['password']);
        }*/

        return $user;
    }

    public static function isAdmin(mixed $data): bool
    {
        if (is_numeric($data)) {
            ['role' => $role] = Model::getInstance(User::class)->findById($data);
        }
        else if (is_string($data)) {
            ['role' => $role] = Model::getInstance(User::class)->findByEmail($data);
            return $role === 'ADMIN';
        }
        else if (is_array($data)) {
            return isset($data['role']) && $data['role'] === 'ADMIN';
        }

        return false;
    }
}
