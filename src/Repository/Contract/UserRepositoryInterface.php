<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Model\User;

interface UserRepositoryInterface
{
    public function find(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * Ek tablosu olmayan kullanıcıyı (sekreter) kaydeder.
     */
    public function create(User $user): User;

    public function updatePhoto(int $userId, ?string $photoPath): void;
}
