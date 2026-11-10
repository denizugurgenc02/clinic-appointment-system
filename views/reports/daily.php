<?php
/**
 * @var \App\Model\DailyReport      $report
 * @var list<\App\Model\Department> $departments
 * @var int|null                    $departmentId
 */

use App\Model\Enum\AppointmentStatus;

$date = $report->date;
$stats = [
    ['Toplam randevu', $report->total(), null],
    ['Kapasite (slot)', $report->capacity, null],
    ['Doluluk oranı', '%' . number_format($report->occupancyRate(), 1, ',', '.'), 'Dolu slot / toplam slot'],
    ['İptal oranı', '%' . number_format($report->cancellationRate(), 1, ',', '.'), 'İptal / toplam randevu'],
    ['Gelmeme oranı', '%' . number_format($report->noShowRate(), 1, ',', '.'), 'Gelmedi / sonuçlanan'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Günlük Randevu Raporu</h1>
    <form method="get" action="/reports/daily" class="d-flex flex-wrap gap-2">
        <input type="date" name="date" class="form-control w-auto" value="<?= e($date->format('Y-m-d')) ?>">
        <select name="department_id" class="form-select w-auto" aria-label="Poliklinik">
            <option value="">Tüm poliklinikler</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= e($d->id) ?>"<?= $departmentId === $d->id ? ' selected' : '' ?>><?= e($d->name) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Göster</button>
    </form>
</div>

<p class="text-muted"><?= e($date->format('d.m.Y')) ?> tarihli rapor</p>

<div class="row g-2 mb-4">
    <?php foreach ($stats as [$label, $value, $hint]): ?>
        <div class="col-6 col-md-4 col-lg">
            <div class="card text-center h-100"><div class="card-body py-3">
                <div class="fs-4 fw-bold"><?= e($value) ?></div>
                <div class="small"><?= e($label) ?></div>
                <?php if ($hint !== null): ?><div class="small text-muted"><?= e($hint) ?></div><?php endif; ?>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <h2 class="h5">Durum Dağılımı</h2>
        <ul class="list-group mb-4">
            <?php foreach (AppointmentStatus::cases() as $status): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><span class="badge text-bg-<?= e($status->color()) ?>">&nbsp;</span> <?= e($status->label()) ?></span>
                    <strong><?= e($report->count($status)) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>

        <h2 class="h5">Poliklinik Bazında</h2>
        <div class="table-responsive">
            <table class="table table-sm bg-white">
                <thead><tr><th>Poliklinik</th><th class="text-end">Randevu</th><th class="text-end">Doluluk</th></tr></thead>
                <tbody>
                <?php foreach ($report->byDepartment as $row): ?>
                    <tr>
                        <td><?= e($row['department']) ?></td>
                        <td class="text-end"><?= e($row['appointments']) ?> / <?= e($row['capacity']) ?></td>
                        <td class="text-end">%<?= e($row['capacity'] > 0 ? number_format($row['appointments'] / $row['capacity'] * 100, 1, ',', '.') : '0') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <h2 class="h5">Randevu Listesi</h2>
        <?php if ($report->rows === []): ?>
            <p class="text-muted">Bu gün için randevu yok.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle bg-white">
                    <thead><tr><th>Saat</th><th>Hasta</th><th>Doktor</th><th class="d-none d-md-table-cell">Poliklinik</th><th>Durum</th></tr></thead>
                    <tbody>
                    <?php foreach ($report->rows as $row): ?>
                        <tr>
                            <td><?= e($row->appointment->startsAt->format('H:i')) ?></td>
                            <td><?= e($row->patientName) ?></td>
                            <td><?= e($row->doctorName) ?></td>
                            <td class="d-none d-md-table-cell"><?= e($row->departmentName) ?></td>
                            <td><span class="badge text-bg-<?= e($row->appointment->status->color()) ?>"><?= e($row->appointment->status->label()) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
