<?php
/**
 * Ana sayfa düzeni.
 *
 * @var string                      $content  Render edilen sayfa içeriği (zaten HTML)
 * @var string|null                 $title
 * @var array<string, list<string>> $flashes  Tek seferlik mesajlar
 * @var string                      $csrfToken
 */
$flashes ??= [];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken ?? '') ?>">
    <title><?= e($title ?? 'Klinik Randevu Sistemi') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="/">Klinik Randevu</a>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <?php foreach ($flashes as $type => $messages): ?>
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?= e($type) ?>" role="alert"><?= e($message) ?></div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?= $content /* Alt şablonun çıktısı; içindeki değerler orada e() ile kaçışlandı */ ?>
</main>

<footer class="py-3 border-top text-center text-muted small">
    Klinik Randevu Sistemi &middot; PHP Programlama Dönem Projesi
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
