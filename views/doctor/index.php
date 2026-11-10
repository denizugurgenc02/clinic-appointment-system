<?php
/**
 * @var \DateTimeImmutable                  $date
 * @var list<\App\Model\AppointmentDetails> $rows
 * @var array<string, int>                  $counts
 * @var \DateTimeImmutable                  $now
 * @var string                              $csrfToken
 */

use App\Model\Enum\AppointmentStatus;

$prev = $date->modify('-1 day')->format('Y-m-d');
$next = $date->modify('+1 day')->format('Y-m-d');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Günlük Randevu Listesi</h1>
    <form method="get" action="/doctor" class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/doctor?date=<?= e($prev) ?>" aria-label="Önceki gün">‹</a>
        <input type="date" name="date" class="form-control" value="<?= e($date->format('Y-m-d')) ?>" onchange="this.form.submit()">
        <a class="btn btn-outline-secondary" href="/doctor?date=<?= e($next) ?>" aria-label="Sonraki gün">›</a>
    </form>
</div>

<div class="row g-2 mb-3">
    <?php foreach (AppointmentStatus::cases() as $status): ?>
        <div class="col-6 col-md-3">
            <div class="card text-center"><div class="card-body py-2">
                <div class="fs-4 fw-bold"><?= e($counts[$status->value] ?? 0) ?></div>
                <div class="small text-muted"><?= e($status->label()) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($rows === []): ?>
    <p class="text-muted">Bu gün için randevu yok.</p>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($rows as $row): ?>
            <?php $a = $row->appointment; ?>
            <div class="list-group-item">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <strong><?= e($a->startsAt->format('H:i')) ?>-<?= e($a->endsAt->format('H:i')) ?></strong>
                        &middot; <?= e($row->patientName) ?>
                        <span class="badge text-bg-<?= e($a->status->color()) ?>"><?= e($a->status->label()) ?></span>
                        <div class="small text-muted">📞 <?= e($row->patientPhone) ?></div>
                        <?php if ($a->patientNote !== null): ?>
                            <div class="small">📝 <?= e($a->patientNote) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($a->status === AppointmentStatus::Booked && $a->startsAt <= $now): ?>
                        <div class="d-flex gap-2 align-items-start">
                            <form method="post" action="/doctor/appointments/<?= e($a->id) ?>/status">
                                <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                <input type="hidden" name="status" value="<?= e(AppointmentStatus::Completed->value) ?>">
                                <button class="btn btn-sm btn-success">Tamamlandı</button>
                            </form>
                            <form method="post" action="/doctor/appointments/<?= e($a->id) ?>/status">
                                <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                <input type="hidden" name="status" value="<?= e(AppointmentStatus::NoShow->value) ?>">
                                <button class="btn btn-sm btn-outline-danger">Gelmedi</button>
                            </form>
                        </div>
                    <?php elseif ($a->status === AppointmentStatus::Booked): ?>
                        <span class="small text-muted">Randevu saati gelince işaretlenebilir</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
