<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Model\Appointment;
use App\Model\AppointmentFilter;
use App\Model\Department;
use App\Model\Doctor;
use App\Model\Enum\AppointmentStatus;
use App\Model\Enum\WaitlistStatus;
use App\Model\Patient;
use App\Model\WaitlistEntry;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\DepartmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\PatientRepositoryInterface;
use App\Repository\Contract\WaitlistRepositoryInterface;
use App\Repository\Json\JsonAppointmentRepository;
use App\Repository\Json\JsonDepartmentRepository;
use App\Repository\Json\JsonDoctorRepository;
use App\Repository\Json\JsonPatientRepository;
use App\Repository\Json\JsonStore;
use App\Repository\Json\JsonWaitlistRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemory\InMemoryAppointmentRepository;
use Tests\Support\InMemory\InMemoryDatabase;
use Tests\Support\InMemory\InMemoryDepartmentRepository;
use Tests\Support\InMemory\InMemoryDoctorRepository;
use Tests\Support\InMemory\InMemoryPatientRepository;
use Tests\Support\InMemory\InMemoryWaitlistRepository;

/**
 * Sözleşme testleri: aynı senaryolar hem JSON repository'lerine hem de testlerde kullanılan
 * bellek içi sahte repository'lere uygulanır. Servis testlerinin sahte repository ile verdiği
 * sonuçların gerçek uygulamayla aynı olduğunu güvenceye alır. Aşama 3'te MySQL uygulaması
 * da bu listeye eklenecektir.
 */
final class RepositoryContractTest extends TestCase
{
    /** @var list<string> */
    private static array $tempDirs = [];

    /**
     * @return array<string, array{0: callable(): array{departments: DepartmentRepositoryInterface, doctors: DoctorRepositoryInterface, patients: PatientRepositoryInterface, appointments: AppointmentRepositoryInterface, waitlist: WaitlistRepositoryInterface}}>
     */
    public static function implementations(): array
    {
        return [
            'json' => [static function (): array {
                $dir = sys_get_temp_dir() . '/contract-' . bin2hex(random_bytes(4));
                self::$tempDirs[] = $dir;
                $store = new JsonStore($dir);

                return [
                    'departments' => new JsonDepartmentRepository($store),
                    'doctors' => new JsonDoctorRepository($store),
                    'patients' => new JsonPatientRepository($store),
                    'appointments' => new JsonAppointmentRepository($store),
                    'waitlist' => new JsonWaitlistRepository($store),
                ];
            }],
            'bellek içi' => [static function (): array {
                $db = new InMemoryDatabase();

                return [
                    'departments' => new InMemoryDepartmentRepository($db),
                    'doctors' => new InMemoryDoctorRepository($db),
                    'patients' => new InMemoryPatientRepository($db),
                    'appointments' => new InMemoryAppointmentRepository($db),
                    'waitlist' => new InMemoryWaitlistRepository($db),
                ];
            }],
        ];
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$tempDirs as $dir) {
            array_map('unlink', array_filter(glob($dir . '/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
            @rmdir($dir);
        }
    }

    /**
     * @param array{departments: DepartmentRepositoryInterface, doctors: DoctorRepositoryInterface, patients: PatientRepositoryInterface} $r
     * @return array{0: Doctor, 1: Patient, 2: Patient}
     */
    private static function seed(array $r): array
    {
        $department = $r['departments']->create(new Department(null, 'Kardiyoloji'));
        $doctor = $r['doctors']->create(new Doctor(null, 'Zeynep Demir', 'z@k.test', 'h', (int) $department->id, 'Doç. Dr.', 30));
        $ali = $r['patients']->create(new Patient(null, 'Ali Veli', 'ali@o.test', 'h', '0555'));
        $ayse = $r['patients']->create(new Patient(null, 'Ayşe Kaya', 'ayse@o.test', 'h', '0556'));

        return [$doctor, $ali, $ayse];
    }

    private static function at(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    /**
     * @param callable(): array{departments: DepartmentRepositoryInterface, doctors: DoctorRepositoryInterface, patients: PatientRepositoryInterface, appointments: AppointmentRepositoryInterface, waitlist: WaitlistRepositoryInterface} $factory
     */
    #[DataProvider('implementations')]
    public function testAppointmentRoundTripAndActiveOverlapQuery(callable $factory): void
    {
        $r = $factory();
        [$doctor, $ali] = self::seed($r);
        $now = self::at('2026-11-16 08:00');

        $a = $r['appointments']->save(Appointment::book($ali->id(), $doctor->id(), self::at('2026-11-17 10:00'), self::at('2026-11-17 10:30'), 'Not', $now));
        $b = $r['appointments']->save(Appointment::book($ali->id(), $doctor->id(), self::at('2026-11-17 11:00'), self::at('2026-11-17 11:30'), null, $now));
        $b->cancel($now);
        $r['appointments']->save($b);

        $found = $r['appointments']->find($a->id());
        self::assertSame('Not', $found?->patientNote);
        self::assertSame('2026-11-17 10:30', $found->endsAt->format('Y-m-d H:i'));
        self::assertSame(AppointmentStatus::Cancelled, $r['appointments']->find($b->id())?->status);

        $active = $r['appointments']->activeForDoctorBetween($doctor->id(), self::at('2026-11-17 00:00'), self::at('2026-11-18 00:00'));
        self::assertSame([$a->id], array_map(static fn (Appointment $x): ?int => $x->id, $active), 'İptal edilen randevu aktif sayılmaz');

        // Sınır: [10:30, 11:00) aralığı 10:00-10:30 randevusuyla kesişmez
        self::assertSame([], $r['appointments']->activeForPatientBetween($ali->id(), self::at('2026-11-17 10:30'), self::at('2026-11-17 11:00')));
    }

    /**
     * @param callable(): array{departments: DepartmentRepositoryInterface, doctors: DoctorRepositoryInterface, patients: PatientRepositoryInterface, appointments: AppointmentRepositoryInterface, waitlist: WaitlistRepositoryInterface} $factory
     */
    #[DataProvider('implementations')]
    public function testSearchJoinsNamesAndAppliesFilters(callable $factory): void
    {
        $r = $factory();
        [$doctor, $ali, $ayse] = self::seed($r);
        $now = self::at('2026-11-16 08:00');
        $r['appointments']->save(Appointment::book($ali->id(), $doctor->id(), self::at('2026-11-17 10:00'), self::at('2026-11-17 10:30'), null, $now));
        $r['appointments']->save(Appointment::book($ayse->id(), $doctor->id(), self::at('2026-11-18 10:00'), self::at('2026-11-18 10:30'), null, $now));

        $byTerm = $r['appointments']->search(new AppointmentFilter(term: 'AYŞE'));
        self::assertCount(1, $byTerm);
        self::assertSame('Doç. Dr. Zeynep Demir', $byTerm[0]->doctorName);
        self::assertSame('Kardiyoloji', $byTerm[0]->departmentName);

        $byDay = $r['appointments']->search(new AppointmentFilter(dateFrom: self::at('2026-11-17'), dateTo: self::at('2026-11-17')));
        self::assertSame(['Ali Veli'], array_map(static fn ($d): string => $d->patientName, $byDay));
    }

    /**
     * @param callable(): array{departments: DepartmentRepositoryInterface, doctors: DoctorRepositoryInterface, patients: PatientRepositoryInterface, appointments: AppointmentRepositoryInterface, waitlist: WaitlistRepositoryInterface} $factory
     */
    #[DataProvider('implementations')]
    public function testWaitlistQueueIsFifoWithIdAsTieBreaker(callable $factory): void
    {
        $r = $factory();
        [$doctor, $ali, $ayse] = self::seed($r);
        $sameTime = self::at('2026-11-16 08:00');

        $first = $r['waitlist']->save(new WaitlistEntry(null, $ali->id(), $doctor->id(), null, WaitlistStatus::Waiting, $sameTime));
        $second = $r['waitlist']->save(new WaitlistEntry(null, $ayse->id(), $doctor->id(), null, WaitlistStatus::Waiting, $sameTime));

        self::assertSame([$first->id, $second->id], array_map(static fn (WaitlistEntry $e): ?int => $e->id, $r['waitlist']->waitingQueue($doctor->id())));
        self::assertSame($first->id, $r['waitlist']->openForPatientAndDoctor($ali->id(), $doctor->id())?->id);

        $positions = [];
        foreach ($r['waitlist']->details() as $details) {
            $positions[$details->patientName] = $details->position;
        }
        self::assertSame(['Ali Veli' => 1, 'Ayşe Kaya' => 2], [ 'Ali Veli' => $positions['Ali Veli'], 'Ayşe Kaya' => $positions['Ayşe Kaya']]);
    }

    /**
     * @param callable(): array{departments: DepartmentRepositoryInterface, doctors: DoctorRepositoryInterface, patients: PatientRepositoryInterface, appointments: AppointmentRepositoryInterface, waitlist: WaitlistRepositoryInterface} $factory
     */
    #[DataProvider('implementations')]
    public function testDepartmentLookupIsCaseInsensitiveAndDoctorCarriesDepartmentName(callable $factory): void
    {
        $r = $factory();
        [$doctor] = self::seed($r);

        self::assertNotNull($r['departments']->findByName('  kardiyoloji '));
        self::assertSame('Kardiyoloji', $r['doctors']->find($doctor->id())?->departmentName);
        self::assertCount(1, $r['doctors']->all($doctor->departmentId));
        self::assertSame([], $r['doctors']->all($doctor->departmentId + 99));
    }
}
