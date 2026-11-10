<?php

declare(strict_types=1);

use App\Core\Clock\ClockInterface;
use App\Core\Config;
use App\Core\Container;
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
use App\Repository\Json\FileLockTransactionManager;
use App\Repository\Json\JsonAppointmentRepository;
use App\Repository\Json\JsonDepartmentRepository;
use App\Repository\Json\JsonDoctorRepository;
use App\Repository\Json\JsonPatientRepository;
use App\Repository\Json\JsonScheduleRepository;
use App\Repository\Json\JsonStore;
use App\Repository\Json\JsonTimeOffRepository;
use App\Repository\Json\JsonUserRepository;
use App\Repository\Json\JsonWaitlistRepository;
use App\Service\AppointmentService;
use App\Service\ProfilePhotoService;
use App\Service\SlotGenerator;
use App\Service\SlotService;
use App\Service\WaitlistService;

/*
 * DI konteyner bağlamaları.
 *
 * Repository arayüzleri burada somut uygulamaya bağlanır. Aşama 2'de JSON uygulaması
 * bağlıdır; Aşama 3'te yalnızca bu bölümdeki "new Json..." satırları "new Pdo..." olur.
 * Servisler, controller'lar ve testler değişmez.
 *
 * Bağlanmayan somut sınıflar (controller'lar, çoğu servis) yapıcı tiplerinden otomatik üretilir.
 */

return static function (Container $c, Config $config): void {
    $dataDir = $config->string('paths.data');

    // --- Veri katmanı (Aşama 2: JSON) ---------------------------------------------------
    $c->singleton(JsonStore::class, static fn (): JsonStore => new JsonStore($dataDir));

    $c->singleton(
        TransactionManagerInterface::class,
        static fn (Container $c): TransactionManagerInterface => new FileLockTransactionManager($c->get(JsonStore::class), $dataDir . '/.lock'),
    );

    // Kullanıcılar: JSON uygulaması, yerleşik süper yöneticiyi ekleyen Decorator ile sarılır.
    // Aşama 3'te yalnızca içteki "new JsonUserRepository" -> "new PdoUserRepository" olur.
    $c->singleton(UserRepositoryInterface::class, static fn (Container $c): UserRepositoryInterface => new SuperAdminUserRepository(
        new JsonUserRepository($c->get(JsonStore::class)),
        $config->string('super_admin.username', 'admin'),
        $config->string('super_admin.name', 'Sistem Yöneticisi'),
        $config->string('super_admin.password_hash'),
    ));

    $repositories = [
        PatientRepositoryInterface::class => JsonPatientRepository::class,
        DoctorRepositoryInterface::class => JsonDoctorRepository::class,
        DepartmentRepositoryInterface::class => JsonDepartmentRepository::class,
        ScheduleRepositoryInterface::class => JsonScheduleRepository::class,
        TimeOffRepositoryInterface::class => JsonTimeOffRepository::class,
        AppointmentRepositoryInterface::class => JsonAppointmentRepository::class,
        WaitlistRepositoryInterface::class => JsonWaitlistRepository::class,
    ];

    foreach ($repositories as $contract => $implementation) {
        $c->singleton($contract, static fn (Container $c): object => new $implementation($c->get(JsonStore::class)));
    }

    // --- Ayar değeri isteyen servisler ----------------------------------------------------
    $c->singleton(SlotService::class, static fn (Container $c): SlotService => new SlotService(
        $c->get(SlotGenerator::class),
        $c->get(ScheduleRepositoryInterface::class),
        $c->get(TimeOffRepositoryInterface::class),
        $c->get(AppointmentRepositoryInterface::class),
        $c->get(ClockInterface::class),
        $config->int('booking_horizon_weeks', 4),
    ));

    $c->singleton(WaitlistService::class, static fn (Container $c): WaitlistService => new WaitlistService(
        $c->get(WaitlistRepositoryInterface::class),
        $c->get(DoctorRepositoryInterface::class),
        $c->get(AppointmentRepositoryInterface::class),
        $c->get(AppointmentService::class),
        $c->get(SlotService::class),
        $c->get(TransactionManagerInterface::class),
        $c->get(ClockInterface::class),
        $config->int('waitlist_offer_minutes', 30),
    ));

    $c->singleton(ProfilePhotoService::class, static function (Container $c) use ($config): ProfilePhotoService {
        /** @var array<string, string> $allowed */
        $allowed = $config->array('upload.allowed_mime');

        return new ProfilePhotoService(
            $c->get(UserRepositoryInterface::class),
            $config->string('paths.uploads'),
            $config->int('upload.max_bytes', 2 * 1024 * 1024),
            $allowed,
        );
    });
};
