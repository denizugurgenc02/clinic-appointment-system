<?php
/**
 * @var string $title
 * @var list<array{department: \App\Model\Department, doctors: list<\App\Model\Doctor>}> $departments
 */
?>
<section class="p-4 p-md-5 mb-4 bg-white rounded-3 border">
    <h1 class="display-6 fw-bold"><?= e($title) ?></h1>
    <p class="lead mb-4">
        Poliklinik ve doktor seçin, haftalık takvimden boş saatinizi alın. Uygun saat yoksa bekleme
        listesine girin; bir randevu iptal edildiğinde sıradaki hastaya 30 dakikalık teklif gönderilir.
    </p>
    <div class="d-flex flex-wrap gap-2">
        <a href="/register" class="btn btn-primary btn-lg">Kayıt Ol</a>
        <a href="/login" class="btn btn-outline-primary btn-lg">Giriş Yap</a>
    </div>
</section>

<h2 class="h4 mb-3">Polikliniklerimiz</h2>
<div class="row g-3">
    <?php foreach ($departments as $item): ?>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h5 card-title"><?= e($item['department']->name) ?></h3>
                    <?php if ($item['department']->description !== ''): ?>
                        <p class="card-text text-muted small"><?= e($item['department']->description) ?></p>
                    <?php endif; ?>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($item['doctors'] as $doctor): ?>
                            <li>👨‍⚕️ <?= e($doctor->displayName()) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($departments === []): ?>
        <div class="col-12">
            <div class="alert alert-warning mb-0">
                Henüz veri yok. Örnek veriyi yüklemek için: <code>docker compose exec app php bin/seed.php</code>
            </div>
        </div>
    <?php endif; ?>
</div>
