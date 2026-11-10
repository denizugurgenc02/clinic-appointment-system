<?php
/**
 * @var \App\Model\Patient|null     $patient
 * @var string                      $patientQuery
 * @var list<\App\Model\Patient>    $patients
 * @var list<\App\Model\Department> $departments
 * @var list<\App\Model\Doctor>     $doctors
 * @var array<string, mixed>        $old
 * @var string                      $csrfToken
 */
$selectedDoctorId = null;
?>
<h1 class="h3 mb-3">Hasta Adına Randevu</h1>

<?php if ($patient === null): ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5">1. Hastayı seçin</h2>
            <form method="get" action="/secretary/appointments/new" class="d-flex gap-2 mb-3">
                <input type="search" name="patient_q" class="form-control" placeholder="Ad, e-posta veya telefon" value="<?= e($patientQuery) ?>">
                <button class="btn btn-outline-primary">Ara</button>
            </form>
            <?php if ($patients === []): ?>
                <p class="text-muted mb-0">Hasta bulunamadı.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($patients as $p): ?>
                        <a class="list-group-item list-group-item-action" href="/secretary/appointments/new?patient_id=<?= e($p->id) ?>">
                            <strong><?= e($p->name) ?></strong>
                            <span class="small text-muted d-block"><?= e($p->email) ?> &middot; <?= e($p->phone) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-secondary d-flex flex-wrap justify-content-between gap-2">
        <span>Hasta: <strong><?= e($patient->name) ?></strong> (<?= e($patient->phone) ?>)</span>
        <a href="/secretary/appointments/new">Hastayı değiştir</a>
    </div>
    <form method="post" action="/secretary/appointments" class="card shadow-sm" novalidate>
        <div class="card-body">
            <h2 class="h5">2. Doktor ve saat seçin</h2>
            <?php require __DIR__ . '/../partials/csrf.php'; ?>
            <input type="hidden" name="patient_id" value="<?= e($patient->id) ?>">
            <?php require __DIR__ . '/../partials/slot_picker.php'; ?>
            <div class="mt-3">
                <label for="note" class="form-label">Not <span class="text-muted">(isteğe bağlı)</span></label>
                <textarea id="note" name="note" class="form-control" rows="2" maxlength="500"><?= e($old['note'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary mt-3" data-submit disabled>Randevuyu Oluştur</button>
        </div>
    </form>
<?php endif; ?>
