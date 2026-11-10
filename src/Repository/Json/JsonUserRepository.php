<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\User;
use App\Repository\Contract\UserRepositoryInterface;

final class JsonUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly JsonStore $store)
    {
    }

    public function find(int $id): ?User
    {
        $row = $this->store->find('users', $id);

        return $row === null ? null : Row::toUser($row);
    }

    public function findByEmail(string $email): ?User
    {
        $email = mb_strtolower(trim($email));

        foreach ($this->store->rows('users') as $row) {
            if (mb_strtolower(Row::string($row, 'email')) === $email) {
                return Row::toUser($row);
            }
        }

        return null;
    }

    public function create(User $user): User
    {
        $user->id = $this->store->insert('users', Row::fromUser($user));

        return $user;
    }

    public function updatePhoto(int $userId, ?string $photoPath): void
    {
        $this->store->update('users', $userId, ['photo_path' => $photoPath]);
    }
}
