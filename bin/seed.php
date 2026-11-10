<?php

declare(strict_types=1);

/*
 * Örnek veri (Aşama 2: storage/data/*.json). Tüm tabloları SİLİP yeniden doldurur.
 *
 *   docker compose exec app php bin/seed.php
 *
 * Tarihler çalıştırıldığı güne göre üretilir; randevular gerçek takvim slotlarından seçilir.
 * Tüm hesapların şifresi: Klinik123!
 */

use App\Core\Application;
use App\Core\Clock\ClockInterface;
use App\Model\Appointment;
use App\Model\Department;
use App\Model\Doctor;
use App\Model\Enum\AppointmentStatus;
use App\Model\Enum\UserRole;
use App\Model\Enum\WaitlistStatus;
use App\Model\Patient;
use App\Model\Schedule;
use App\Model\Slot;
use App\Model\TimeOff;
use App\Model\User;
use App\Model\WaitlistEntry;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\DepartmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\PatientRepositoryInterface;
use App\Repository\Contract\ScheduleRepositoryInterface;
use App\Repository\Contract\TimeOffRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\WaitlistRepositoryInterface;
use App\Repository\Json\JsonStore;
use App\Service\SlotService;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new Application(dirname(__DIR__));
$c = $app->container();

$store = $c->get(JsonStore::class);
foreach (['users', 'patients', 'doctors', 'departments', 'schedules', 'time_off', 'appointments', 'waitlist'] as $table) {
    $store->truncate($table);
}

$now = $c->get(ClockInterface::class)->now();
$slots = $c->get(SlotService::class);
$appointments = $c->get(AppointmentRepositoryInterface::class);
$password = password_hash('Klinik123!', PASSWORD_DEFAULT);
mt_srand(2026);

/** Bugünden sonraki ilk hafta içi günü. */
$nextWeekday = static function (DateTimeImmutable $from): DateTimeImmutable {
    $day = $from->setTime(0, 0)->modify('+1 day');
    while ((int) $day->format('N') > 5) {
        $day = $day->modify('+1 day');
    }

    return $day;
};

// --- Poliklinikler ---------------------------------------------------------------------------
$departments = [];
foreach ([
    'Dahiliye' => 'İç hastalıkları, genel muayene ve check-up',
    'Kardiyoloji' => 'Kalp ve damar hastalıkları',
    'Göz Hastalıkları' => 'Göz muayenesi, görme ve göz tansiyonu',
    'Ortopedi ve Travmatoloji' => 'Kas, kemik ve eklem hastalıkları',
    'Kulak Burun Boğaz' => 'KBB muayenesi, işitme ve sinüs',
] as $name => $description) {
    $departments[$name] = $c->get(DepartmentRepositoryInterface::class)->create(new Department(null, $name, $description));
}

// --- Doktorlar ve haftalık takvimler (öğle arası = iki satır arasındaki boşluk) ----------------
$fullWeek = static fn (string $amEnd = '12:00', string $pmStart = '13:00', string $pmEnd = '17:00', array $days = [1, 2, 3, 4, 5]): array
    => array_merge(...array_map(static fn (int $d): array => [[$d, '09:00', $amEnd], [$d, $pmStart, $pmEnd]], $days));

$doctorDefs = [
    ['Ayşe Yılmaz', 'ayse.yilmaz', 'Uzm. Dr.', 'Dahiliye', 20, $fullWeek()],
    ['Mehmet Kaya', 'mehmet.kaya', 'Uzm. Dr.', 'Dahiliye', 30, $fullWeek('12:00', '13:00', '16:00', [1, 3, 5])],
    ['Zeynep Demir', 'zeynep.demir', 'Doç. Dr.', 'Kardiyoloji', 30, $fullWeek('12:00', '13:00', '17:00', [1, 2, 3, 4])],
    ['Can Öztürk', 'can.ozturk', 'Uzm. Dr.', 'Kardiyoloji', 20, $fullWeek('12:00', '13:30', '17:30', [2, 3, 4, 5])],
    ['Elif Şahin', 'elif.sahin', 'Op. Dr.', 'Göz Hastalıkları', 15, array_merge($fullWeek('12:00', '13:00', '16:00', [3]), [[1, '09:00', '12:00'], [2, '09:00', '12:00'], [4, '09:00', '12:00'], [5, '09:00', '12:00']])],
    ['Ahmet Çelik', 'ahmet.celik', 'Prof. Dr.', 'Ortopedi ve Travmatoloji', 30, $fullWeek()],
    ['Selin Arslan', 'selin.arslan', 'Uzm. Dr.', 'Kulak Burun Boğaz', 20, $fullWeek('12:00', '13:00', '17:00', [1, 2, 4])],
];

/** @var array<string, Doctor> $doctors */
$doctors = [];
foreach ($doctorDefs as [$name, $login, $title, $department, $minutes, $rows]) {
    $doctor = $c->get(DoctorRepositoryInterface::class)->create(new Doctor(
        null,
        $name,
        $login . '@klinik.test',
        $password,
        (int) $departments[$department]->id,
        $title,
        $minutes,
        null,
        $now->modify('-60 days'),
        $department,
    ));
    foreach ($rows as [$day, $start, $end]) {
        $c->get(ScheduleRepositoryInterface::class)->add(new Schedule(null, $doctor->id(), $day, $start, $end));
    }
    $doctors[$login] = $doctor;
}

// --- Sekreter ve hastalar -------------------------------------------------------------------
$c->get(UserRepositoryInterface::class)->create(
    new User(null, 'Fatma Koç', 'sekreter@klinik.test', $password, UserRole::Secretary, null, $now->modify('-60 days')),
);

/** @var list<Patient> $patients */
$patients = [];
foreach ([
    ['Ali Veli', 'hasta@klinik.test', '0532 111 22 33'],
    ['Deniz Aydın', 'deniz.aydin@ornek.test', '0533 222 33 44'],
    ['Burak Yıldız', 'burak.yildiz@ornek.test', '0534 333 44 55'],
    ['Esra Kılıç', 'esra.kilic@ornek.test', '0535 444 55 66'],
    ['Murat Aksoy', 'murat.aksoy@ornek.test', '0536 555 66 77'],
    ['Gizem Polat', 'gizem.polat@ornek.test', '0537 666 77 88'],
    ['Hakan Erdem', 'hakan.erdem@ornek.test', '0538 777 88 99'],
    ['Seda Güneş', 'seda.gunes@ornek.test', '0539 888 99 00'],
] as [$name, $email, $phone]) {
    $patients[] = $c->get(PatientRepositoryInterface::class)->create(
        new Patient(null, $name, $email, $password, $phone, null, $now->modify('-30 days')),
    );
}
[$ali, $deniz, $burak] = $patients;

// --- İzinler ve istisnalar ------------------------------------------------------------------
$demoDay = $nextWeekday($now);
$timeOffs = $c->get(TimeOffRepositoryInterface::class);
// Tam gün izin: Ahmet Çelik, bir sonraki iş günü (ızgarada o gün boş görünür)
$timeOffs->add(new TimeOff(null, $doctors['ahmet.celik']->id(), $demoDay, $demoDay->modify('+1 day'), 'Yıllık izin'));
// Gün içi istisna: Ayşe Yılmaz, aynı gün 14:00-16:00
$timeOffs->add(new TimeOff(null, $doctors['ayse.yilmaz']->id(), $demoDay->setTime(14, 0), $demoDay->setTime(16, 0), 'Kurul toplantısı'));
// Bir hafta izin: Selin Arslan, 2 hafta sonra
$selinOff = $slots->weekStart($now)->modify('+14 days');
$timeOffs->add(new TimeOff(null, $doctors['selin.arslan']->id(), $selinOff, $selinOff->modify('+5 days'), 'Kongre'));

// --- Randevular -----------------------------------------------------------------------------
/** @var array<int, list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>> $busy hasta => dolu aralıklar (K7) */
$busy = [];
$patientIndex = 0;
$created = 0;

$book = static function (Doctor $doctor, Slot $slot, AppointmentStatus $status, ?Patient $patient = null, ?string $note = null)
 use (&$busy, &$patientIndex, &$created, $patients, $appointments, $now): bool {
    $candidates = $patient === null ? $patients : [$patient];
    for ($i = 0; $i < count($candidates); $i++) {
        $p = $patient ?? $patients[($patientIndex + $i) % count($patients)];
        foreach ($busy[$p->id()] ?? [] as [$s, $e]) {
            if ($slot->start < $e && $slot->end > $s) {
                continue 2;
            }
        }

        $createdAt = min($now, $slot->start->modify('-' . mt_rand(1, 10) . ' days'));
        $appointments->save(new Appointment(
            null,
            $p->id(),
            $doctor->id(),
            $slot->start,
            $slot->end,
            $status,
            $note,
            $createdAt,
            $status === AppointmentStatus::Cancelled ? min($now, $slot->start->modify('-1 day')) : null,
        ));
        if ($status === AppointmentStatus::Booked) {
            $busy[$p->id()][] = [$slot->start, $slot->end];
        }
        $patientIndex++;
        $created++;

        return true;
    }

    return false;
};

$notes = [null, null, null, 'Kontrol muayenesi', 'Baş ağrısı ve halsizlik', 'Tahlil sonuçlarını göstermek istiyorum', 'Göğüs ağrısı', 'Diz ağrısı'];
$pick = static fn (array $items): mixed => $items[mt_rand(0, count($items) - 1)];

$thisMonday = $slots->weekStart($now);
foreach ($doctors as $login => $doctor) {
    foreach ([$thisMonday->modify('-7 days'), $thisMonday, $thisMonday->modify('+7 days')] as $week) {
        foreach ($slots->weekGrid($doctor, $week) as $daySlots) {
            foreach ($daySlots as $slot) {
                $isPast = $slot->start <= $now;
                $isDemoDayCardiology = $login === 'zeynep.demir' && $slot->start->format('Y-m-d') === $demoDay->format('Y-m-d');

                // Zeynep Demir'in demo günü tamamen dolu -> bekleme listesi senaryosu
                $chance = $isDemoDayCardiology ? 100 : ($isPast ? 55 : 30);
                if (mt_rand(1, 100) > $chance) {
                    continue;
                }

                if ($isPast) {
                    $roll = mt_rand(1, 100);
                    // Bugünün geçmiş saatlerinin bir kısmı Booked kalır: doktor panelinde işaretlenebilsin
                    $status = match (true) {
                        $slot->start->format('Y-m-d') === $now->format('Y-m-d') && $roll <= 50 => AppointmentStatus::Booked,
                        $roll <= 72 => AppointmentStatus::Completed,
                        $roll <= 86 => AppointmentStatus::NoShow,
                        default => AppointmentStatus::Cancelled,
                    };
                } else {
                    $status = mt_rand(1, 100) <= 8 && !$isDemoDayCardiology ? AppointmentStatus::Cancelled : AppointmentStatus::Booked;
                }

                $book($doctor, $slot, $status, null, $pick($notes));
            }
        }
    }
}

// Ali Veli'nin (hasta@klinik.test) kendi ekranında görünecek randevular
$aliPlan = [
    ['mehmet.kaya', 'Booked'],
    ['elif.sahin', 'Booked'],
];
foreach ($aliPlan as [$login, $_]) {
    foreach ($slots->weekGrid($doctors[$login], $thisMonday->modify('+7 days')) as $daySlots) {
        $free = array_values(array_filter($daySlots, static fn (Slot $s): bool => $s->isAvailable()));
        if ($free !== [] && $book($doctors[$login], $free[0], AppointmentStatus::Booked, $ali, 'Kontrol muayenesi')) {
            break;
        }
    }
}

// --- Bekleme listesi: Zeynep Demir (dolu gün) ------------------------------------------------
$waitlist = $c->get(WaitlistRepositoryInterface::class);
$zeynep = $doctors['zeynep.demir'];
$waitlist->save(new WaitlistEntry(null, $ali->id(), $zeynep->id(), null, WaitlistStatus::Waiting, $now->modify('-2 hours')));
$waitlist->save(new WaitlistEntry(null, $deniz->id(), $zeynep->id(), $demoDay, WaitlistStatus::Waiting, $now->modify('-1 hours')));
$waitlist->save(new WaitlistEntry(null, $burak->id(), $doctors['ahmet.celik']->id(), null, WaitlistStatus::Waiting, $now->modify('-30 minutes')));

printf(
    "Örnek veri yüklendi (%s):\n  %d poliklinik, %d doktor, 1 sekreter, %d hasta, %d randevu, 3 bekleme kaydı\n",
    $store->directory(),
    count($departments),
    count($doctors),
    count($patients),
    $created,
);
printf("  Demo günü: %s (Zeynep Demir tamamen dolu, Ahmet Çelik izinli, Ayşe Yılmaz 14-16 toplantıda)\n", $demoDay->format('d.m.Y'));
echo "  Giriş: hasta@klinik.test | ayse.yilmaz@klinik.test | sekreter@klinik.test  (şifre: Klinik123!)\n";
