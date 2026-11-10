<?php
/**
 * @var array<string, mixed>  $old
 * @var array<string, string> $errors
 * @var string                $csrfToken
 */
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-4">Giriş Yap</h1>
                <form method="post" action="/login" novalidate>
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta veya kullanıcı adı</label>
                        <input type="text" id="email" name="email" class="form-control" required autofocus
                               autocomplete="username" value="<?= e($old['email'] ?? '') ?>">
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Şifre</label>
                        <input type="password" id="password" name="password" class="form-control" required
                               autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                </form>
                <p class="text-center small mt-3 mb-0">Hesabınız yok mu? <a href="/register">Kayıt olun</a></p>
            </div>
        </div>
        <details class="mt-3 small text-muted">
            <summary>Demo hesapları (seed verisi)</summary>
            <ul class="mb-0 mt-2">
                <li>Hasta: <code>hasta@klinik.test</code></li>
                <li>Doktor: <code>ayse.yilmaz@klinik.test</code></li>
                <li>Sekreter: <code>sekreter@klinik.test</code></li>
                <li>Süper yönetici: <code>admin</code> / <code>asdf1234</code></li>
                <li>Şifre (hepsi): <code>Klinik123!</code></li>
            </ul>
        </details>
    </div>
</div>
