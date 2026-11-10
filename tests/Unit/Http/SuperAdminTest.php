<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Model\Enum\AppointmentStatus;
use App\Model\Enum\UserRole;
use App\Model\User;
use App\Repository\BuiltIn\SuperAdminUserRepository;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\DepartmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Service\AppointmentService;
use App\Service\AuthService;
use App\Service\Exception\ValidationException;
use App\Service\ClinicAdminService;
use Tests\Support\AppTestCase;

/**
 * Yerleşik süper yönetici: veri kaynağı boşken de vardır ve hiçbir kısıta tabi değildir.
 */
final class SuperAdminTest extends AppTestCase
{
    private const CSRF = 'test-token';

    /**
     * @param array<string, string> $body
     * @param array<string, string> $headers
     */
    private function asAdmin(string $method, string $path, array $body = [], array $headers = []): Response
    {
        $_SESSION = ['_csrf_token' => self::CSRF, 'user_id' => SuperAdminUserRepository::ID];
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        /** @var array<string, mixed> $query */
        return $this->app->handle(new Request($method, $path, $query, $body + ['_csrf' => self::CSRF], [], $headers));
    }

    private function admin(): User
    {
        $admin = $this->get(UserRepositoryInterface::class)->find(SuperAdminUserRepository::ID);
        self::assertNotNull($admin);

        return $admin;
    }

    public function testAdminCanLogInWithUsernameEvenWhenDataStoreIsEmpty(): void
    {
        $user = $this->get(AuthService::class)->attempt('admin', 'asdf1234');

        self::assertSame(UserRole::SuperAdmin, $user->role);
        self::assertTrue($user->hasFullAccess());
        self::assertSame([], $this->get(\App\Repository\Json\JsonStore::class)->rows('users'), 'Veri kaynağına yazılmaz');
    }

    public function testWrongAdminPasswordIsRejected(): void
    {
        $this->expectException(\DomainException::class);

        $this->get(AuthService::class)->attempt('admin', 'yanlis');
    }

    public function testAdminPassesEveryRoleRestriction(): void
    {
        foreach (['/admin', '/secretary', '/secretary/doctors', '/secretary/waitlist', '/reports/daily', '/doctor', '/appointments', '/appointments/book', '/profile'] as $path) {
            self::assertSame(200, $this->asAdmin('GET', $path)->status(), $path);
        }
    }

    public function testOtherRolesCannotUseAdminEndpoints(): void
    {
        $_SESSION = ['_csrf_token' => self::CSRF, 'user_id' => $this->patient()->id()];

        $response = $this->app->handle(new Request('POST', '/admin/departments', body: ['_csrf' => self::CSRF, 'name' => 'Kaçak']));

        self::assertSame(403, $response->status());
        self::assertNull($this->get(DepartmentRepositoryInterface::class)->findByName('Kaçak'));
    }

    public function testAdminBypassesOwnershipRules(): void
    {
        $doctor = $this->doctor();
        $appointments = $this->get(AppointmentService::class);
        $first = $appointments->book($this->patient('A')->id(), $doctor->id(), self::at('2026-11-17 10:00'));
        $second = $appointments->book($this->patient('B')->id(), $doctor->id(), self::at('2026-11-17 10:30'));

        $appointments->cancel($first->id(), $this->admin());

        $this->clock->set(self::at('2026-11-17 11:00'));
        $appointments->markOutcome($second->id(), AppointmentStatus::Completed, $this->admin());

        $repository = $this->get(AppointmentRepositoryInterface::class);
        self::assertSame(AppointmentStatus::Cancelled, $repository->find($first->id())?->status);
        self::assertSame(AppointmentStatus::Completed, $repository->find($second->id())?->status);
    }

    public function testAdminCreatesDepartmentViaFormPost(): void
    {
        $response = $this->asAdmin('POST', '/admin/departments', ['name' => 'Dermatoloji', 'description' => 'Cilt hastalıkları']);

        self::assertSame(302, $response->status());
        self::assertSame('/admin', $response->header('Location'));
        self::assertSame('Cilt hastalıkları', $this->get(DepartmentRepositoryInterface::class)->findByName('dermatoloji')?->description);
    }

    public function testAdminCreatesDepartmentViaJsonAndDuplicateIsRejected(): void
    {
        $headers = ['accept' => 'application/json'];

        $created = $this->asAdmin('POST', '/admin/departments', ['name' => 'Nöroloji'], $headers);
        $duplicate = $this->asAdmin('POST', '/admin/departments', ['name' => 'nöroloji'], $headers);

        self::assertSame(201, $created->status());
        self::assertSame('Nöroloji', json_decode($created->body(), true)['data']['name'] ?? null);
        self::assertSame(422, $duplicate->status());
        self::assertArrayHasKey('name', json_decode($duplicate->body(), true)['errors'] ?? []);
    }

    public function testAdminCreatesDoctorThatCanBeBookedAfterScheduleIsAdded(): void
    {
        $department = $this->department('Dermatoloji');
        $admin = $this->get(ClinicAdminService::class);

        $doctor = $admin->createDoctor('Yeni Doktor', 'yeni.doktor@klinik.test', 'Doktor123', (int) $department->id, 'Uzm. Dr.', 20);
        $this->get(\App\Service\ScheduleService::class)->addRow($doctor->id(), 2, '09:00', '10:00');
        $appointment = $this->get(AppointmentService::class)->book($this->patient()->id(), $doctor->id(), self::at('2026-11-17 09:20'));

        self::assertSame('Dermatoloji', $this->get(DoctorRepositoryInterface::class)->find($doctor->id())?->departmentName);
        self::assertSame('09:40', $appointment->endsAt->format('H:i'));
        self::assertSame(UserRole::Doctor, $this->get(AuthService::class)->attempt('yeni.doktor@klinik.test', 'Doktor123')->role);
    }

    public function testAdminUsernameIsReservedInTheDecoratedRepository(): void
    {
        $users = $this->get(UserRepositoryInterface::class);
        self::assertInstanceOf(SuperAdminUserRepository::class, $users, 'JSON repository Decorator ile sarılı olmalı');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ayrılmıştır');

        $users->create(new User(null, 'Sahte Admin', 'ADMIN', 'x', UserRole::Secretary));
    }

    public function testStaffAccountValidation(): void
    {
        try {
            $this->get(ClinicAdminService::class)->createSecretary('Ay', 'gecersiz', 'kisa');
            self::fail('Doğrulama hatası bekleniyordu');
        } catch (ValidationException $e) {
            self::assertEqualsCanonicalizing(['name', 'email', 'password'], array_keys($e->errors()));
        }
    }
}
