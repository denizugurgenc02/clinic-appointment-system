<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\User;
use App\Repository\Contract\UserRepositoryInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function find(int $id): ?User
    {
        return isset($this->db->users[$id]) ? clone $this->db->users[$id] : null;
    }

    public function findByEmail(string $email): ?User
    {
        $email = mb_strtolower(trim($email));

        foreach ($this->db->users as $user) {
            if (mb_strtolower($user->email) === $email) {
                return clone $user;
            }
        }

        return null;
    }

    public function create(User $user): User
    {
        $user->id = $this->db->nextId('users');
        $this->db->users[$user->id] = clone $user;

        return $user;
    }

    public function updatePhoto(int $userId, ?string $photoPath): void
    {
        if (isset($this->db->users[$userId])) {
            $this->db->users[$userId]->photoPath = $photoPath;
        }
    }
}
