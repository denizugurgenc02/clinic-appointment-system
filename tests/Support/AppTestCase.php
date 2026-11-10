<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Application;
use App\Core\Clock\FrozenClock;
use App\Core\Config;
use App\Core\Container;
use App\Model\Department;
use App\Model\Doctor;
use App\Model\Patient;
use App\Model\Schedule;
use App\Repository\BuiltIn\SuperAdminUserRepository;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\DepartmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\PatientRepositoryInterface;
use App\Repository\Contract\ScheduleRepositoryInterface;
use App\Repository\Contract\TimeOffRepositoryInterface;
use App\Repository\Contract\TransactionManagerInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\WaitlistRepositoryInterface;
use Tests\Support\InMemory\InMemoryAppointmentRepository;
use Tests\Support\InMemory\InMemoryDatabase;
use Tests\Support\InMemory\InMemoryDepartmentRepository;
use Tests\Support\InMemory\InMemoryDoctorRepository;
use Tests\Support\InMemory\InMemoryPatientRepository;
use Tests\Support\InMemory\InMemoryScheduleRepository;
use Tests\Support\InMemory\InMemoryTimeOffRepository;
use Tests\Support\InMemory\InMemoryTransactionManager;
use Tests\Support\InMemory\InMemoryUserRepository;
use Tests\Support\InMemory\InMemoryWaitlistRepository;
use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Servis testleri için gerçek uygulama kurulumu (config/services.php + config/events.php,
 * gerçek router/middleware/servisler), sabitlenmiş saat ile.
 *
 * Veri katmanı varsayılan olarak BELLEK İÇİ sahte repository'lerdir (tests/Support/InMemory):
 * testler dosyaya dokunmaz ve hızlıdır. Ayrı PHP süreçlerinin aynı veriyi görmesi gereken testler
 * (eşzamanlılık) useJsonStorage() ile gerçek JSON repository'lerini geçici bir klasörde kullanır.
 *
 * Aynı servis kodunun iki farklı veri katmanıyla çalışması, Aşama 3'teki MySQL geçişinin
 * yalnızca repository bağlamasını değiştireceğinin de kanıtıdır.
 *
 * "Şimdi" varsayılan olarak 16.11.2026 Pazartesi 08:00'dir.
 */
abstract class AppTestCase extends TestCase
{
    protected const NOW = '2026-11-16 08:00:00';

    protected string $dataDir;
    protected FrozenClock $clock;
    protected Application $app;
    protected Container $container;
    protected InMemoryDatabase $memory;

    /**
     * true: gerçek JSON repository'leri (geçici klasörde). Varsayılan: bellek içi.
     */
    protected function useJsonStorage(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->dataDir = sys_get_temp_dir() . '/clinic-test-' . bin2hex(random_bytes(6));
        mkdir($this->dataDir, 0775, true);
        $this->clock = FrozenClock::at(static::NOW);
        $this->app = new Application(self::rootPath(), [
            'debug' => true,
            'paths' => ['data' => $this->dataDir, 'uploads' => $this->dataDir . '/uploads'],
        ], $this->clock);
        $this->container = $this->app->container();
        $this->memory = new InMemoryDatabase();

        if (!$this->useJsonStorage()) {
            $this->bindInMemoryRepositories();
        }
    }

    /**
     * config/services.php'deki JSON bağlamalarını bellek içi uygulamalarla değiştirir.
     * Servisler henüz üretilmediği için (lazy) bütün zincir bu repository'lerle kurulur.
     */
    private function bindInMemoryRepositories(): void
    {
        $db = $this->memory;
        $config = $this->container->get(Config::class);
        $c = $this->container;

        $c->singleton(UserRepositoryInterface::class, static fn (): UserRepositoryInterface => new SuperAdminUserRepository(
            new InMemoryUserRepository($db),
            $config->string('super_admin.username'),
            $config->string('super_admin.name'),
            $config->string('super_admin.password_hash'),
        ));
        $c->singleton(PatientRepositoryInterface::class, static fn (): PatientRepositoryInterface => new InMemoryPatientRepository($db));
        $c->singleton(DoctorRepositoryInterface::class, static fn (): DoctorRepositoryInterface => new InMemoryDoctorRepository($db));
        $c->singleton(DepartmentRepositoryInterface::class, static fn (): DepartmentRepositoryInterface => new InMemoryDepartmentRepository($db));
        $c->singleton(ScheduleRepositoryInterface::class, static fn (): ScheduleRepositoryInterface => new InMemoryScheduleRepository($db));
        $c->singleton(TimeOffRepositoryInterface::class, static fn (): TimeOffRepositoryInterface => new InMemoryTimeOffRepository($db));
        $c->singleton(AppointmentRepositoryInterface::class, static fn (): AppointmentRepositoryInterface => new InMemoryAppointmentRepository($db));
        $c->singleton(WaitlistRepositoryInterface::class, static fn (): WaitlistRepositoryInterface => new InMemoryWaitlistRepository($db));
        $c->singleton(TransactionManagerInterface::class, static fn (): TransactionManagerInterface => new InMemoryTransactionManager($db));
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->dataDir);
        $_SESSION = [];
    }

    public static function rootPath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function get(string $class): object
    {
        return $this->container->get($class);
    }

    protected function department(string $name = 'Dahiliye'): Department
    {
        return $this->get(DepartmentRepositoryInterface::class)->create(new Department(null, $name));
    }

    /**
     * Varsayılan: hafta içi her gün 09:00-12:00 ve 13:00-17:00, 30 dk slot.
     *
     * @param list<array{0: int, 1: string, 2: string}>|null $rows [gün, başlangıç, bitiş]
     */
    protected function doctor(string $name = 'Dr. Test', int $slotMinutes = 30, ?array $rows = null, ?int $departmentId = null): Doctor
    {
        $departmentId ??= $this->department('Poliklinik ' . bin2hex(random_bytes(3)))->id;
        \assert($departmentId !== null);

        $doctor = $this->get(DoctorRepositoryInterface::class)->create(new Doctor(
            null,
            $name,
            strtolower(str_replace([' ', '.'], '', $name)) . bin2hex(random_bytes(3)) . '@klinik.test',
            password_hash('Klinik123!', PASSWORD_DEFAULT, ['cost' => 4]),
            $departmentId,
            'Uzm. Dr.',
            $slotMinutes,
        ));

        $rows ??= [];
        if ($rows === []) {
            foreach (range(1, 5) as $day) {
                $rows[] = [$day, '09:00', '12:00'];
                $rows[] = [$day, '13:00', '17:00'];
            }
        }

        foreach ($rows as [$day, $start, $end]) {
            $this->get(ScheduleRepositoryInterface::class)->add(new Schedule(null, $doctor->id(), $day, $start, $end));
        }

        return $doctor;
    }

    protected function patient(string $name = 'Hasta Test'): Patient
    {
        return $this->get(PatientRepositoryInterface::class)->create(new Patient(
            null,
            $name,
            strtolower(str_replace(' ', '.', $name)) . bin2hex(random_bytes(3)) . '@ornek.test',
            password_hash('Hasta1234', PASSWORD_DEFAULT, ['cost' => 4]),
            '0555 000 00 00',
            null,
            $this->clock->now(),
        ));
    }

    protected static function at(string $datetime): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime);
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
