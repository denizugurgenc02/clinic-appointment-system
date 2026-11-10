<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\UserRole;
use DateTimeImmutable;

/**
 * Tüm rollerin ortak hesap bilgisi. Patient ve Doctor bu sınıftan türer;
 * sekreterin ek özniteliği olmadığı için doğrudan User olarak temsil edilir.
 */
class User
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
        public string $passwordHash,
        public readonly UserRole $role,
        public ?string $photoPath = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function is(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Kayıtlı (id atanmış) kullanıcının kimliği.
     */
    public function id(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Kullanıcı henüz kaydedilmedi.');
        }

        return $this->id;
    }
}
