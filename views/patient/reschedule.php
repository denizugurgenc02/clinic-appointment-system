<?php
/**
 * @var \App\Model\AppointmentDetails $details
 * @var string                        $csrfToken
 * @var \App\Model\User|null          $currentUser
 */

use App\Model\Enum\UserRole;

$a = $details->appointment;
$fixedDoctorId = $a->doctorId; // slot_picker: doktor seçimi yok, yalnızca bu doktorun ızgarası
$departments = [];
$doctors = [];
?>
<h1 class="h3 mb-3">Randevu Tarihini Değiştir</h1>

<div class="alert alert-secondary">
    Mevcut randevu: <strong><?= e($a->startsAt->format('d.m.Y H:i')) ?></strong> —
    <?= e($details->doctorName) ?> (<?= e($details->departmentName) ?>)
    <?php if ($currentUser?->is(UserRole::Secretary)): ?>
        <br><span class="small">Hasta: <?= e($details->patientName) ?></span>
    <?php endif; ?>
</div>

<form method="post" action="/appointments/<?= e($a->id) ?>/reschedule" class="card shadow-sm" novalidate>
    <div class="card-body">
        <?php require __DIR__ . '/../partials/csrf.php'; ?>
        <?php require __DIR__ . '/../partials/slot_picker.php'; ?>
        <p class="small text-muted mt-3 mb-0">
            Yeni saat alındığında eski randevu otomatik iptal edilir ve boşalan saat bekleme listesindeki hastaya teklif edilir.
        </p>
        <button type="submit" class="btn btn-primary mt-3" data-submit disabled>Yeni Saati Onayla</button>
    </div>
</form>
