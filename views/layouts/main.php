<!-- views/layouts/main.php -->
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klinik Randevu Sistemi</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">Klinik Otomasyonu</a>
        </div>
    </nav>

    <!-- Controller'dan gelen sayfa içeriği buraya enjekte edilecek -->
    <main>
        <?= $content; ?>
    </main>

    <footer class="text-center py-4 mt-5 text-muted border-top">
        <p>&copy; 2026 Klinik Randevu Sistemi - Pure PHP</p>
    </footer>
</body>
</html>