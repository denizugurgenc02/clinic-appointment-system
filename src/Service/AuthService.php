<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Core\Session\Session;
use App\Model\Patient;
use App\Model\User;
use App\Repository\Contract\PatientRepositoryInterface;
use App\Repository\Contract\TransactionManagerInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\ValidationException;

/**
 * Kayıt (yalnızca hasta kendini kaydeder), giriş, çıkış ve oturumdaki kullanıcı.
 * Parolalar password_hash ile saklanır, password_verify ile doğrulanır.
 */
final class AuthService
{
    private const SESSION_KEY = 'user_id';

    private ?User $current = null;
    private bool $resolved = false;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PatientRepositoryInterface $patients,
        private readonly Session $session,
        private readonly TransactionManagerInterface $tx,
        private readonly ClockInterface $clock,
    ) {
    }

    public function register(string $name, string $email, string $password, string $passwordConfirmation, string $phone): Patient
    {
        $name = trim($name);
        $email = mb_strtolower(trim($email));
        $phone = trim($phone);

        $errors = [];
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            $errors['name'] = 'Ad soyad 3-100 karakter olmalı.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Geçerli bir e-posta adresi girin.';
        }
        if (preg_match('/^[0-9 +()\-]{10,20}$/', $phone) !== 1) {
            $errors['phone'] = 'Telefon 10-20 haneli olmalı (ör. 0555 123 45 67).';
        }
        if (mb_strlen($password) < 8 || preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $errors['password'] = 'Şifre en az 8 karakter olmalı ve harf ile rakam içermeli.';
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Şifreler eşleşmiyor.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $patient = $this->tx->run(function () use ($name, $email, $password, $phone): Patient {
            if ($this->users->findByEmail($email) !== null) {
                throw new ValidationException(['email' => 'Bu e-posta adresiyle kayıtlı bir hesap var.']);
            }

            $patient = new Patient(null, $name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, null, $this->clock->now());

            return $this->patients->create($patient);
        });

        $this->startSession($patient);

        return $patient;
    }

    public function attempt(string $email, string $password): User
    {
        $user = $this->users->findByEmail($email);

        // Kullanıcı yoksa da aynı mesaj: hangi e-postaların kayıtlı olduğu sızdırılmaz
        if ($user === null || !password_verify($password, $user->passwordHash)) {
            throw new DomainRuleException('E-posta veya şifre hatalı.');
        }

        $this->startSession($user);

        return $user;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->current = null;
        $this->resolved = true;
    }

    public function user(): ?User
    {
        if (!$this->resolved) {
            $id = $this->session->get(self::SESSION_KEY);
            $this->current = is_int($id) ? $this->users->find($id) : null;
            $this->resolved = true;
        }

        return $this->current;
    }

    /**
     * Profil fotoğrafı değişince oturumdaki kopyayı yeniler.
     */
    public function refresh(): void
    {
        $this->resolved = false;
    }

    private function startSession(User $user): void
    {
        // Oturum sabitleme (session fixation) saldırısına karşı kimlik yenilenir
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->id());
        $this->current = $user;
        $this->resolved = true;
    }
}
