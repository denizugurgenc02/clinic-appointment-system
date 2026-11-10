<?php
/**
 * @var \App\Model\Doctor        $doctor
 * @var list<\App\Model\Schedule> $rows
 * @var list<\App\Model\TimeOff>  $timeOffs
 * @var array<int, string>       $dayNames
 * @var array<string, mixed>     $old
 * @var array<string, string>    $errors
 * @var string                   $csrfToken
 */
$errors ??= [];
?>
<a href="/secretary/doctors" class="small">‹ Doktorlar</a>
<h1 class="h3 mt-1 mb-1"><?= e($doctor->displayName()) ?></h1>
<p class="text-muted"><?= e($doctor->departmentName) ?> &middot; <?= e($doctor->slotMinutes) ?> dakikalık randevular</p>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Haftalık Çalışma Takvimi</h2>
                <p class="small text-muted">Öğle arası, aynı günün iki satırı arasındaki boşluktur (ör. 09:00-12:00 ve 13:00-17:00).</p>
                <?php if ($rows === []): ?>
                    <p class="text-muted">Takvim tanımlı değil; bu doktor için slot üretilmez.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Gün</th><th>Saat</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row->dayName()) ?></td>
                                <td><?= e($row->startTime) ?> - <?= e($row->endTime) ?></td>
                                <td class="text-end">
                                    <form method="post" action="/secretary/schedules/<?= e($row->id) ?>/delete"
                                          onsubmit="return confirm('Bu çalışma satırı silinsin mi?');">
                                        <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                        <button class="btn btn-sm btn-link text-danger p-0">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <form method="post" action="/secretary/doctors/<?= e($doctor->id) ?>/schedules" class="row g-2 align-items-end" novalidate>
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <div class="col-12 col-sm-4">
                        <label for="day_of_week" class="form-label small">Gün</label>
                        <select id="day_of_week" name="day_of_week" class="form-select<?= isset($errors['day_of_week']) ? ' is-invalid' : '' ?>">
                            <?php foreach ($dayNames as $num => $name): ?>
                                <option value="<?= e($num) ?>"<?= (string) ($old['day_of_week'] ?? '') === (string) $num ? ' selected' : '' ?>><?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3">
                        <label for="start_time" class="form-label small">Başlangıç</label>
                        <input type="time" id="start_time" name="start_time" class="form-control<?= isset($errors['start_time']) ? ' is-invalid' : '' ?>"
                               value="<?= e($old['start_time'] ?? '09:00') ?>">
                    </div>
                    <div class="col-6 col-sm-3">
                        <label for="end_time" class="form-label small">Bitiş</label>
                        <input type="time" id="end_time" name="end_time" class="form-control<?= isset($errors['end_time']) ? ' is-invalid' : '' ?>"
                               value="<?= e($old['end_time'] ?? '12:00') ?>">
                    </div>
                    <div class="col-12 col-sm-2">
                        <button class="btn btn-primary w-100">Ekle</button>
                    </div>
                    <?php foreach (['day_of_week', 'start_time', 'end_time'] as $field): ?>
                        <?php require __DIR__ . '/../partials/field_error.php'; ?>
                    <?php endforeach; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">İzinler ve İstisnalar</h2>
                <p class="small text-muted">Tam gün izin için 00:00 - ertesi gün 00:00 girin; gün içi istisna için saat aralığı girin.</p>
                <?php if ($timeOffs === []): ?>
                    <p class="text-muted">Yaklaşan izin yok.</p>
                <?php else: ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($timeOffs as $t): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <strong><?= e($t->startsAt->format('d.m.Y H:i')) ?></strong> → <?= e($t->endsAt->format('d.m.Y H:i')) ?>
                                    <?php if ($t->reason !== ''): ?><div class="small text-muted"><?= e($t->reason) ?></div><?php endif; ?>
                                </div>
                                <form method="post" action="/secretary/time-off/<?= e($t->id) ?>/delete"
                                      onsubmit="return confirm('İzin kaydı silinsin mi?');">
                                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                    <button class="btn btn-sm btn-link text-danger p-0">Sil</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="/secretary/doctors/<?= e($doctor->id) ?>/time-off" class="row g-2" novalidate>
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <div class="col-12 col-sm-6">
                        <label for="starts_at" class="form-label small">Başlangıç</label>
                        <input type="datetime-local" id="starts_at" name="starts_at" class="form-control<?= isset($errors['starts_at']) ? ' is-invalid' : '' ?>"
                               value="<?= e($old['starts_at'] ?? '') ?>" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="ends_at" class="form-label small">Bitiş</label>
                        <input type="datetime-local" id="ends_at" name="ends_at" class="form-control<?= isset($errors['ends_at']) ? ' is-invalid' : '' ?>"
                               value="<?= e($old['ends_at'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="reason" class="form-label small">Açıklama</label>
                        <input type="text" id="reason" name="reason" maxlength="200" class="form-control" value="<?= e($old['reason'] ?? '') ?>"
                               placeholder="ör. Yıllık izin, kongre, kurul toplantısı">
                    </div>
                    <?php foreach (['starts_at', 'ends_at', 'reason'] as $field): ?>
                        <?php require __DIR__ . '/../partials/field_error.php'; ?>
                    <?php endforeach; ?>
                    <div class="col-12">
                        <button class="btn btn-warning">İzin Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
