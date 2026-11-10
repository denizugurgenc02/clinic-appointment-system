<?php
/**
 * @var list<\App\Model\Department> $departments
 * @var array<int, int>             $doctorCounts
 * @var list<\App\Model\Doctor>     $doctors
 * @var array<string, mixed>        $old
 * @var array<string, string>       $errors
 * @var string                      $csrfToken
 */
$errors ??= [];
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
// Hatalı gönderimden dönülen form: doktor formunda "slot_minutes" alanı vardır, poliklinik formunda yoktur
$doctorFormFailed = isset($old['slot_minutes']);
$deptOld = $doctorFormFailed ? [] : ($old ?? []);
$doctorOld = $doctorFormFailed ? $old : [];
?>
<h1 class="h3 mb-1">Yönetim</h1>
<p class="text-muted">Süper yönetici tüm panellere ve işlemlere erişebilir. Buradan poliklinik ve personel tanımlanır.</p>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Poliklinikler</h2>
                <div class="list-group mb-3">
                    <?php foreach ($departments as $d): ?>
                        <details class="list-group-item">
                            <summary class="d-flex justify-content-between">
                                <span><?= e($d->name) ?></span>
                                <span class="badge text-bg-light"><?= e($doctorCounts[$d->id] ?? 0) ?> doktor</span>
                            </summary>
                            <form method="post" action="/admin/departments/<?= e($d->id) ?>" class="row g-2 mt-2">
                                <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                <div class="col-12 col-sm-5">
                                    <input type="text" name="name" class="form-control form-control-sm" value="<?= e($d->name) ?>" required aria-label="Ad">
                                </div>
                                <div class="col-12 col-sm-5">
                                    <input type="text" name="description" class="form-control form-control-sm" value="<?= e($d->description) ?>" aria-label="Açıklama">
                                </div>
                                <div class="col-12 col-sm-2">
                                    <button class="btn btn-sm btn-outline-primary w-100">Kaydet</button>
                                </div>
                            </form>
                        </details>
                    <?php endforeach; ?>
                </div>

                <h3 class="h6">Yeni Poliklinik</h3>
                <form method="post" action="/admin/departments" class="row g-2" novalidate>
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <div class="col-12 col-sm-5">
                        <input type="text" name="name" class="form-control<?= $invalid('name') ?>" placeholder="ör. Dermatoloji"
                               value="<?= e($deptOld['name'] ?? '') ?>" required aria-label="Poliklinik adı">
                    </div>
                    <div class="col-12 col-sm-5">
                        <input type="text" name="description" class="form-control<?= $invalid('description') ?>" placeholder="Açıklama"
                               value="<?= e($deptOld['description'] ?? '') ?>" aria-label="Açıklama">
                    </div>
                    <div class="col-12 col-sm-2">
                        <button class="btn btn-primary w-100">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">Yeni Doktor</h2>
                <form method="post" action="/admin/doctors" class="row g-2" novalidate>
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <div class="col-12 col-sm-4">
                        <label class="form-label small" for="d-title">Unvan</label>
                        <input type="text" id="d-title" name="title" class="form-control<?= $invalid('title') ?>" value="<?= e($doctorOld['title'] ?? 'Uzm. Dr.') ?>">
                    </div>
                    <div class="col-12 col-sm-8">
                        <label class="form-label small" for="d-name">Ad Soyad</label>
                        <input type="text" id="d-name" name="name" class="form-control<?= $invalid('name') ?>" value="<?= e($doctorOld['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small" for="d-email">E-posta</label>
                        <input type="email" id="d-email" name="email" class="form-control<?= $invalid('email') ?>" value="<?= e($doctorOld['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small" for="d-password">Şifre</label>
                        <input type="password" id="d-password" name="password" class="form-control<?= $invalid('password') ?>" required autocomplete="new-password">
                    </div>
                    <div class="col-8">
                        <label class="form-label small" for="d-department">Poliklinik</label>
                        <select id="d-department" name="department_id" class="form-select<?= $invalid('department_id') ?>" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= e($d->id) ?>"<?= (string) ($doctorOld['department_id'] ?? '') === (string) $d->id ? ' selected' : '' ?>><?= e($d->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small" for="d-slot">Süre (dk)</label>
                        <input type="number" id="d-slot" name="slot_minutes" min="5" max="120" step="5" class="form-control<?= $invalid('slot_minutes') ?>"
                               value="<?= e($doctorOld['slot_minutes'] ?? 20) ?>">
                    </div>
                    <?php if ($doctorFormFailed): ?>
                        <?php foreach (['name', 'email', 'password', 'department_id', 'slot_minutes', 'title'] as $field): ?>
                            <?php require __DIR__ . '/../partials/field_error.php'; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="col-12">
                        <button class="btn btn-primary">Doktor Oluştur</button>
                        <span class="small text-muted ms-2">Ardından çalışma takvimi eklenir.</span>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Yeni Sekreter</h2>
                <form method="post" action="/admin/secretaries" class="row g-2" novalidate>
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <div class="col-12 col-sm-4">
                        <input type="text" name="name" class="form-control" placeholder="Ad Soyad" required aria-label="Ad Soyad">
                    </div>
                    <div class="col-12 col-sm-4">
                        <input type="email" name="email" class="form-control" placeholder="E-posta" required aria-label="E-posta">
                    </div>
                    <div class="col-12 col-sm-4">
                        <input type="password" name="password" class="form-control" placeholder="Şifre" required aria-label="Şifre" autocomplete="new-password">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-primary">Sekreter Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
