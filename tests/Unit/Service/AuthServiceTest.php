<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Enum\UserRole;
use App\Service\AuthService;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\ValidationException;
use Tests\Support\AppTestCase;

final class AuthServiceTest extends AppTestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->get(AuthService::class);
    }

    public function testRegisterHashesPasswordAndLogsPatientIn(): void
    {
        $patient = $this->auth->register('Ayşe Kaya', 'AYSE@ornek.test', 'Sifre1234', 'Sifre1234', '0555 111 22 33');

        self::assertSame('ayse@ornek.test', $patient->email);
        self::assertNotSame('Sifre1234', $patient->passwordHash);
        self::assertTrue(password_verify('Sifre1234', $patient->passwordHash));
        self::assertSame(UserRole::Patient, $this->auth->user()?->role);
        self::assertSame($patient->id, $_SESSION['user_id']);
    }

    public function testInvalidRegistrationReportsEachField(): void
    {
        try {
            $this->auth->register('A', 'gecersiz', 'kisa', 'farkli', '12');
            self::fail('Doğrulama hatası bekleniyordu');
        } catch (ValidationException $e) {
            self::assertEqualsCanonicalizing(['name', 'email', 'password', 'phone'], array_keys($e->errors()));
        }
    }

    public function testEmailMustBeUnique(): void
    {
        $this->auth->register('Ayşe Kaya', 'ayse@ornek.test', 'Sifre1234', 'Sifre1234', '0555 111 22 33');

        $this->expectException(ValidationException::class);

        $this->auth->register('Başka Ayşe', 'ayse@ornek.test', 'Sifre1234', 'Sifre1234', '0555 111 22 34');
    }

    public function testLoginRejectsWrongPasswordWithGenericMessage(): void
    {
        $this->auth->register('Ayşe Kaya', 'ayse@ornek.test', 'Sifre1234', 'Sifre1234', '0555 111 22 33');
        $this->auth->logout();

        try {
            $this->auth->attempt('ayse@ornek.test', 'YanlisSifre1');
            self::fail('Giriş reddedilmeliydi');
        } catch (DomainRuleException $e) {
            self::assertSame('E-posta veya şifre hatalı.', $e->getMessage());
        }

        self::assertNull($this->auth->user());
        self::assertSame('Ayşe Kaya', $this->auth->attempt('ayse@ornek.test', 'Sifre1234')->name);
    }
}
