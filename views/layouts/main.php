<?php
/**
 * Ana sayfa düzeni: rol bazlı menü, flash mesajları, içerik.
 *
 * @var string                      $content     Alt şablonun çıktısı (değerleri orada kaçışlandı)
 * @var string|null                 $title
 * @var array<string, list<string>> $flashes
 * @var string                      $csrfToken
 * @var \App\Model\User|null        $currentUser
 */

use App\Model\Enum\UserRole;

$flashes ??= [];
$currentUser ??= null;
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$menu = match ($currentUser?->role) {
    UserRole::Patient => [
        '/appointments/book' => 'Randevu Al',
        '/appointments' => 'Randevularım',
    ],
    UserRole::Doctor => [
        '/doctor' => 'Günlük Liste',
    ],
    UserRole::Secretary => [
        '/secretary' => 'Randevular',
        '/secretary/appointments/new' => 'Yeni Randevu',
        '/secretary/doctors' => 'Doktorlar & Takvim',
        '/secretary/waitlist' => 'Bekleme Listesi',
        '/reports/daily' => 'Günlük Rapor',
    ],
    UserRole::SuperAdmin => [
        '/admin' => 'Yönetim',
        '/secretary' => 'Randevular',
        '/secretary/appointments/new' => 'Yeni Randevu',
        '/secretary/doctors' => 'Doktorlar & Takvim',
        '/secretary/waitlist' => 'Bekleme Listesi',
        '/reports/daily' => 'Günlük Rapor',
    ],
    default => [],
};
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken ?? '') ?>">
    <title><?= e($title ?? 'Klinik Randevu Sistemi') ?> · Klinik Randevu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-body-tertiary">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="/">🏥 Klinik Randevu</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Menüyü aç/kapat">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menu as $href => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $path === $href ? ' active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <ul class="navbar-nav align-items-lg-center">
                <?php if ($currentUser === null): ?>
                    <li class="nav-item"><a class="nav-link" href="/login">Giriş Yap</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm ms-lg-2" href="/register">Kayıt Ol</a></li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2" href="/profile">
                            <?php if ($currentUser->photoPath !== null): ?>
                                <img src="/users/<?= e($currentUser->id) ?>/photo" alt="" class="avatar avatar-sm">
                            <?php endif; ?>
                            <span><?= e($currentUser->name) ?></span>
                            <span class="badge text-bg-light"><?= e($currentUser->role->label()) ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="/logout" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <button type="submit" class="btn btn-outline-light btn-sm ms-lg-2">Çıkış</button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <?php foreach ($flashes as $type => $messages): ?>
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<footer class="py-3 border-top text-center text-muted small bg-white">
    Klinik Randevu Sistemi &middot; PHP Programlama Dönem Projesi
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/slots.js"></script>
</body>
</html>
