<!-- views/patient/home.php -->
<div class="container mt-5">
    <div class="p-5 mb-4 bg-light rounded-3">
        <div class="container-fluid py-5">
            <h1 class="display-5 fw-bold">Hoş Geldiniz, <?= htmlspecialchars($kullaniciAdi); ?></h1>
            <p class="col-md-8 fs-4">Klinik Randevu Sistemine başarıyla giriş yaptınız. Bu sayfa tamamen sunucu tarafında (PHP) render edilmiştir.</p>
            <a href="/appointments/book" class="btn btn-primary btn-lg" type="button">Hemen Randevu Al</a>
        </div>
    </div>
</div>