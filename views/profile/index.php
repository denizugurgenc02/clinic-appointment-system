<?php
/**
 * @var \App\Model\User $user
 * @var string          $csrfToken
 */
?>
<h1 class="h3 mb-3">Profilim</h1>

<div class="row g-4">
    <div class="col-12 col-md-5 col-lg-4">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <?php if ($user->photoPath !== null): ?>
                    <img src="/users/<?= e($user->id) ?>/photo" alt="Profil fotoğrafı" class="avatar avatar-lg mb-3">
                <?php else: ?>
                    <div class="avatar avatar-lg avatar-placeholder mx-auto mb-3">👤</div>
                <?php endif; ?>
                <h2 class="h5 mb-0"><?= e($user->name) ?></h2>
                <p class="text-muted small mb-0"><?= e($user->email) ?> &middot; <?= e($user->role->label()) ?></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-7 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Profil Fotoğrafı Yükle</h2>
                <form method="post" action="/profile/photo" enctype="multipart/form-data">
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
                    <div class="mb-3">
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                        <div class="form-text">JPG, PNG veya WEBP; en fazla 2 MB. Dosya içeriği kontrol edilir ve rastgele bir adla saklanır.</div>
                    </div>
                    <button class="btn btn-primary">Yükle</button>
                </form>
            </div>
        </div>
    </div>
</div>
