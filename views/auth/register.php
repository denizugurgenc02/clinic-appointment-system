<?php
/**
 * @var array<string, mixed>  $old
 * @var array<string, string> $errors
 * @var string                $csrfToken
 */
$fields = [
    ['name', 'Ad Soyad', 'text', 'name'],
    ['email', 'E-posta', 'email', 'email'],
    ['phone', 'Telefon', 'tel', 'tel'],
    ['password', 'Şifre', 'password', 'new-password'],
    ['password_confirmation', 'Şifre (tekrar)', 'password', 'new-password'],
];
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Hasta Kaydı</h1>
                <p class="text-muted small mb-4">Kayıt olduktan sonra hemen randevu alabilirsiniz.</p>
                <form method="post" action="/register" novalidate>
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <?php foreach ($fields as [$field, $label, $type, $autocomplete]): ?>
                        <div class="mb-3">
                            <label for="<?= e($field) ?>" class="form-label"><?= e($label) ?></label>
                            <input type="<?= e($type) ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" required
                                   autocomplete="<?= e($autocomplete) ?>"
                                   class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>"
                                   value="<?= $type === 'password' ? '' : e($old[$field] ?? '') ?>">
                            <?php require __DIR__ . '/../partials/field_error.php'; ?>
                        </div>
                    <?php endforeach; ?>
                    <p class="form-text">Şifre en az 8 karakter olmalı, harf ve rakam içermeli.</p>
                    <button type="submit" class="btn btn-primary w-100">Kayıt Ol</button>
                </form>
                <p class="text-center small mt-3 mb-0">Zaten hesabınız var mı? <a href="/login">Giriş yapın</a></p>
            </div>
        </div>
    </div>
</div>
