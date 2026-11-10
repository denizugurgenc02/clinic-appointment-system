<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\Doctor;
use App\Model\Enum\UserRole;
use App\Model\Patient;
use App\Model\User;
use DateTimeImmutable;
use UnexpectedValueException;

/**
 * JSON satırlarındaki (array<string, mixed>) değerleri tipli okuma ve
 * varlık <-> satır dönüşümünde ortak kullanılan yardımcılar.
 */
final class Row
{
    public const DATETIME = 'Y-m-d H:i:s';
    public const DATE = 'Y-m-d';

    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (!is_int($value)) {
            throw new UnexpectedValueException(sprintf('"%s" tamsayı olmalı.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf('"%s" metin olmalı.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function date(array $row, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(self::string($row, $key));
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableDate(array $row, string $key): ?DateTimeImmutable
    {
        $value = self::nullableString($row, $key);

        return $value === null ? null : new DateTimeImmutable($value);
    }

    public static function formatDate(?DateTimeImmutable $date, string $format = self::DATETIME): ?string
    {
        return $date?->format($format);
    }

    /**
     * @param array<string, mixed> $row users satırı
     */
    public static function toUser(array $row): User
    {
        return new User(
            self::int($row, 'id'),
            self::string($row, 'name'),
            self::string($row, 'email'),
            self::string($row, 'password_hash'),
            UserRole::from(self::string($row, 'role')),
            self::nullableString($row, 'photo_path'),
            self::nullableDate($row, 'created_at'),
        );
    }

    /**
     * @param array<string, mixed> $user    users satırı
     * @param array<string, mixed> $patient patients satırı
     */
    public static function toPatient(array $user, array $patient): Patient
    {
        return new Patient(
            self::int($user, 'id'),
            self::string($user, 'name'),
            self::string($user, 'email'),
            self::string($user, 'password_hash'),
            self::string($patient, 'phone'),
            self::nullableString($user, 'photo_path'),
            self::nullableDate($user, 'created_at'),
        );
    }

    /**
     * @param array<string, mixed> $user   users satırı
     * @param array<string, mixed> $doctor doctors satırı
     */
    public static function toDoctor(array $user, array $doctor, string $departmentName): Doctor
    {
        return new Doctor(
            self::int($user, 'id'),
            self::string($user, 'name'),
            self::string($user, 'email'),
            self::string($user, 'password_hash'),
            self::int($doctor, 'department_id'),
            self::string($doctor, 'title'),
            self::int($doctor, 'slot_minutes'),
            self::nullableString($user, 'photo_path'),
            self::nullableDate($user, 'created_at'),
            $departmentName,
        );
    }

    /**
     * @return array<string, mixed> users satırı (id hariç)
     */
    public static function fromUser(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'password_hash' => $user->passwordHash,
            'role' => $user->role->value,
            'photo_path' => $user->photoPath,
            'created_at' => self::formatDate($user->createdAt ?? new DateTimeImmutable()),
        ];
    }

    /**
     * Satırları id'ye göre dizinler (JSON'da JOIN yapmak için).
     *
     * @param list<array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function indexBy(array $rows, string $key = 'id'): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[self::int($row, $key)] = $row;
        }

        return $indexed;
    }
}
