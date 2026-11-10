<?php
/**
 * @var list<\App\Model\Doctor>     $doctors
 * @var list<\App\Model\Department> $departments
 * @var int|null                    $departmentId
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Doktorlar ve Takvimler</h1>
    <form method="get" action="/secretary/doctors">
        <select name="department_id" class="form-select" onchange="this.form.submit()" aria-label="Poliklinik filtresi">
            <option value="">Tüm poliklinikler</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= e($d->id) ?>"<?= $departmentId === $d->id ? ' selected' : '' ?>><?= e($d->name) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="row g-3">
    <?php foreach ($doctors as $doctor): ?>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex gap-3">
                    <?php if ($doctor->photoPath !== null): ?>
                        <img src="/users/<?= e($doctor->id) ?>/photo" alt="" class="avatar">
                    <?php else: ?>
                        <div class="avatar avatar-placeholder">👨‍⚕️</div>
                    <?php endif; ?>
                    <div>
                        <h2 class="h6 mb-1"><?= e($doctor->displayName()) ?></h2>
                        <p class="small text-muted mb-2"><?= e($doctor->departmentName) ?> &middot; <?= e($doctor->slotMinutes) ?> dk randevu</p>
                        <a href="/secretary/doctors/<?= e($doctor->id) ?>" class="btn btn-sm btn-outline-primary">Takvim ve İzinler</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
