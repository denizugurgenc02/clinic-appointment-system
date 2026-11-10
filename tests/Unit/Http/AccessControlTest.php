<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Model\Enum\AppointmentStatus;
use App\Model\Enum\UserRole;
use App\Model\User;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Service\AppointmentService;
use Tests\Support\AppTestCase;

/**
 * Rol yetkilendirmesi ve "hasta yalnızca kendi randevularını görür" kuralı,
 * gerçek router + middleware + controller zinciri üzerinden (HTTP isteği simülasyonu).
 */
final class AccessControlTest extends AppTestCase
{
    private const CSRF = 'test-token';

    /**
     * @param array<string, string> $body
     */
    private function request(string $method, string $path, ?int $userId = null, array $body = []): Response
    {
        $_SESSION = ['_csrf_token' => self::CSRF];
        if ($userId !== null) {
            $_SESSION['user_id'] = $userId;
        }

        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        /** @var array<string, mixed> $query */
        return $this->app->handle(new Request($method, $path, $query, $body + ['_csrf' => self::CSRF]));
    }

    private function secretary(): User
    {
        return $this->get(UserRepositoryInterface::class)->create(
            new User(null, 'Sekreter', 'sekreter@test.local', 'x', UserRole::Secretary),
        );
    }

    public function testGuestIsRedirectedToLoginAndApiReturns401(): void
    {
        $page = $this->request('GET', '/appointments');
        self::assertSame(302, $page->status());
        self::assertSame('/login', $page->header('Location'));

        self::assertSame(401, $this->request('GET', '/api/slots?doctor_id=1')->status());
    }

    public function testEachRoleIsLimitedToItsOwnPanels(): void
    {
        $patient = $this->patient()->id();
        $doctor = $this->doctor()->id();
        $secretary = $this->secretary()->id();

        $expectations = [
            // [kullanıcı, yol, beklenen durum]
            [$patient, '/appointments', 200],
            [$patient, '/secretary', 403],
            [$patient, '/doctor', 403],
            [$patient, '/reports/daily', 403],
            [$doctor, '/doctor', 200],
            [$doctor, '/appointments', 403],
            [$doctor, '/secretary/doctors', 403],
            [$secretary, '/secretary', 200],
            [$secretary, '/reports/daily', 200],
            [$secretary, '/doctor', 403],
            [$secretary, '/appointments/book', 403],
        ];

        foreach ($expectations as [$userId, $path, $status]) {
            self::assertSame($status, $this->request('GET', $path, $userId)->status(), $path);
        }
    }

    public function testPatientSeesOnlyOwnAppointments(): void
    {
        $doctor = $this->doctor();
        $me = $this->patient('Benim Adım');
        $other = $this->patient('Başka Hasta');
        $service = $this->get(AppointmentService::class);
        $service->book($me->id(), $doctor->id(), self::at('2026-11-17 10:00'));
        $service->book($other->id(), $doctor->id(), self::at('2026-11-17 11:00'));

        $html = $this->request('GET', '/appointments', $me->id())->body();

        self::assertStringContainsString('17.11.2026 10:00', $html);
        self::assertStringNotContainsString('17.11.2026 11:00', $html, 'Başka hastanın randevusu görünmemeli');
    }

    public function testPatientCannotCancelAnotherPatientsAppointmentViaHttp(): void
    {
        $doctor = $this->doctor();
        $owner = $this->patient('Sahip');
        $attacker = $this->patient('Saldırgan');
        $appointment = $this->get(AppointmentService::class)->book($owner->id(), $doctor->id(), self::at('2026-11-17 10:00'));

        $response = $this->request('POST', '/appointments/' . $appointment->id() . '/cancel', $attacker->id());

        self::assertSame(302, $response->status());
        self::assertSame(
            AppointmentStatus::Booked,
            $this->get(AppointmentRepositoryInterface::class)->find($appointment->id())?->status,
            'Randevu iptal edilmemiş olmalı',
        );
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $_SESSION = ['_csrf_token' => self::CSRF, 'user_id' => $this->patient()->id()];

        $response = $this->app->handle(new Request('POST', '/appointments', body: ['doctor_id' => '1']));

        self::assertSame(403, $response->status());
    }

    public function testSlotApiReturnsJsonGridForPatient(): void
    {
        $doctor = $this->doctor(slotMinutes: 30, rows: [[2, '09:00', '12:00'], [2, '13:00', '14:00']]);

        $response = $this->request('GET', '/api/slots?doctor_id=' . $doctor->id() . '&week=2026-11-16', $this->patient()->id());
        self::assertSame(200, $response->status(), $response->body());
        /** @var array{days: list<array{date: string, slots: list<array{time: string}>}>} $data */
        $data = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertCount(7, $data['days']);
        $tuesday = array_column($data['days'][1]['slots'], 'time');
        self::assertSame(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '13:00', '13:30'], $tuesday);
    }
}
