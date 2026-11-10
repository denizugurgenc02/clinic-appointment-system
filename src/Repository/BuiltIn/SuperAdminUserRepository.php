<?php

declare(strict_types=1);

namespace App\Repository\BuiltIn;

use App\Model\Enum\UserRole;
use App\Model\User;
use App\Repository\Contract\UserRepositoryInterface;
use DomainException;

/**
 * Decorator: herhangi bir UserRepositoryInterface uygulamasını (JSON, ileride MySQL) sarar
 * ve yerleşik süper yönetici hesabını veri kaynağından bağımsız olarak ekler.
 *
 * - Diğer tüm kullanıcılar için çağrılar olduğu gibi içteki repository'ye iletilir.
 * - Süper yönetici veri kaynağına hiç yazılmaz; veritabanı boş olsa da giriş yapılabilir.
 * - id = 0 ayrılmıştır: JSON ve MySQL'de otomatik artan id'ler 1'den başlar, çakışma olmaz.
 *
 * Open-closed: mevcut repository'ler değiştirilmeden yeni davranış eklenir.
 */
final class SuperAdminUserRepository implements UserRepositoryInterface
{
    public const ID = 0;

    public function __construct(
        private readonly UserRepositoryInterface $inner,
        private readonly string $username,
        private readonly string $name,
        private readonly string $passwordHash,
    ) {
    }

    public function find(int $id): ?User
    {
        return $id === self::ID ? $this->superAdmin() : $this->inner->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        if (mb_strtolower(trim($email)) === mb_strtolower($this->username)) {
            return $this->superAdmin();
        }

        return $this->inner->findByEmail($email);
    }

    public function create(User $user): User
    {
        if (mb_strtolower($user->email) === mb_strtolower($this->username)) {
            throw new DomainException('Bu kullanıcı adı sisteme ayrılmıştır.');
        }

        return $this->inner->create($user);
    }

    public function updatePhoto(int $userId, ?string $photoPath): void
    {
        if ($userId === self::ID) {
            throw new DomainException('Yerleşik yönetici hesabı için profil fotoğrafı saklanmaz.');
        }

        $this->inner->updatePhoto($userId, $photoPath);
    }

    private function superAdmin(): User
    {
        return new User(self::ID, $this->name, $this->username, $this->passwordHash, UserRole::SuperAdmin);
    }
}
