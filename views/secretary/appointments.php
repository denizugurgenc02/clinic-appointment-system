<?php
/**
 * @var list<\App\Model\AppointmentDetails> $rows
 * @var array{q: string, department_id: ?int, doctor_id: ?int, status: ?\App\Model\Enum\AppointmentStatus, date_from: ?\DateTimeImmutable, date_to: ?\DateTimeImmutable} $filters
 * @var list<\App\Model\Department>         $departments
 * @var list<\App\Model\Doctor>             $doctors
 * @var list<\App\Model\Enum\AppointmentStatus> $statuses
 * @var \DateTimeImmutable                  $now
 * @var string                              $csrfToken
 * @var \App\Model\User|null                $currentUser
 */

use App\Model\Enum\AppointmentStatus;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Randevu Yönetimi</h1>
    <a href="/secretary/appointments/new" class="btn btn-primary">+ Hasta Adına Randevu</a>
</div>

<form method="get" action="/secretary" class="card card-body shadow-sm mb-3">
    <input type="hidden" name="filter" value="1">
    <div class="row g-2">
        <div class="col-12 col-md-4">
            <label for="q" class="form-label small">Hasta ara (ad / e-posta)</label>
            <input type="search" id="q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="ör. Ali">
        </div>
        <div class="col-6 col-md-4">
            <label for="f-department" class="form-label small">Poliklinik</label>
            <select id="f-department" name="department_id" class="form-select">
                <option value="">Tümü</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= e($d->id) ?>"<?= $filters['department_id'] === $d->id ? ' selected' : '' ?>><?= e($d->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4">
            <label for="f-doctor" class="form-label small">Doktor</label>
            <select id="f-doctor" name="doctor_id" class="form-select">
                <option value="">Tümü</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?= e($doc->id) ?>"<?= $filters['doctor_id'] === $doc->id ? ' selected' : '' ?>><?= e($doc->displayName()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4">
            <label for="f-status" class="form-label small">Durum</label>
            <select id="f-status" name="status" class="form-select">
                <option value="">Tümü</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s->value) ?>"<?= $filters['status'] === $s ? ' selected' : '' ?>><?= e($s->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="date_from" class="form-label small">Başlangıç</label>
            <input type="date" id="date_from" name="date_from" class="form-control" value="<?= e($filters['date_from']?->format('Y-m-d')) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label for="date_to" class="form-label small">Bitiş</label>
            <input type="date" id="date_to" name="date_to" class="form-control" value="<?= e($filters['date_to']?->format('Y-m-d')) ?>">
        </div>
        <div class="col-6 col-md-2 d-flex align-items-end gap-2">
            <button class="btn btn-primary w-100">Filtrele</button>
        </div>
    </div>
    <div class="small mt-2"><a href="/secretary">Filtreleri sıfırla</a> &middot; <?= e(count($rows)) ?> kayıt</div>
</form>

<?php if ($rows === []): ?>
    <p class="text-muted">Ölçütlere uyan randevu yok.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white">
            <thead>
            <tr><th>Tarih</th><th>Hasta</th><th class="d-none d-md-table-cell">Doktor / Poliklinik</th><th>Durum</th><th class="text-end">İşlem</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $a = $row->appointment; ?>
                <tr>
                    <td class="text-nowrap"><?= e($a->startsAt->format('d.m.Y')) ?><div class="small text-muted"><?= e($a->startsAt->format('H:i')) ?></div></td>
                    <td><?= e($row->patientName) ?><div class="small text-muted"><?= e($row->patientPhone) ?></div>
                        <div class="small text-muted d-md-none"><?= e($row->doctorName) ?></div></td>
                    <td class="d-none d-md-table-cell"><?= e($row->doctorName) ?><div class="small text-muted"><?= e($row->departmentName) ?></div></td>
                    <td><span class="badge text-bg-<?= e($a->status->color()) ?>"><?= e($a->status->label()) ?></span></td>
                    <td class="text-end">
                        <?php if ($a->canBeCancelled($now)): ?>
                            <div class="d-flex flex-wrap justify-content-end gap-1">
                                <a class="btn btn-sm btn-outline-primary" href="/appointments/<?= e($a->id) ?>/reschedule">Değiştir</a>
                                <form method="post" action="/appointments/<?= e($a->id) ?>/cancel"
                                      onsubmit="return confirm('Bu randevu iptal edilsin mi?');">
                                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                    <button class="btn btn-sm btn-outline-danger">İptal</button>
                                </form>
                            </div>
                        <?php elseif ($currentUser?->hasFullAccess() && $a->status === AppointmentStatus::Booked): ?>
                            <?php /* Süper yönetici, başlamış randevunun sonucunu doktor adına işaretleyebilir */ ?>
                            <div class="d-flex flex-wrap justify-content-end gap-1">
                                <?php foreach ([AppointmentStatus::Completed, AppointmentStatus::NoShow] as $outcome): ?>
                                    <form method="post" action="/doctor/appointments/<?= e($a->id) ?>/status">
                                        <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                        <input type="hidden" name="status" value="<?= e($outcome->value) ?>">
                                        <button class="btn btn-sm btn-outline-<?= e($outcome->color()) ?>"><?= e($outcome->label()) ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
