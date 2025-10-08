<?php

namespace App\Repositories;

use App\Core\Data\DQL\Query;
use App\Core\Data\Repository;
use App\Models\User;

class UserRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(User::class);
    }

    public function findByEmail(string $email): ?User
    {
        $query = Query::select('users')->from()->equals('email', $email);
        return $this->query($query, true);
    }

    public function findByRole(string $role): ?User
    {
        $query = Query::select('users')->from()->equals('role', $role);
        return $this->query($query, true);
    }
}