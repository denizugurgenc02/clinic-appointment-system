<?php
/**
 * @var list<\App\Model\Department> $departments
 * @var list<\App\Model\Doctor>     $doctors
 * @var int|null                    $selectedDoctorId
 * @var array<string, mixed>        $old
 * @var array<string, string>       $errors
 * @var string                      $csrfToken
 */
?>
<h1 class="h3 mb-3">Randevu Al</h1>

<form method="post" action="/appointments" class="card shadow-sm mb-4" novalidate>
    <div class="card-body">
        <?php require __DIR__ . '/../partials/csrf.php'; ?>
        <?php require __DIR__ . '/../partials/slot_picker.php'; ?>

        <div class="mt-3">
            <label for="note" class="form-label">Şikâyetiniz / notunuz <span class="text-muted">(isteğe bağlı)</span></label>
            <textarea id="note" name="note" class="form-control" rows="2" maxlength="500"><?= e($old['note'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary mt-3" data-submit disabled>Randevuyu Onayla</button>
    </div>
</form>

<div class="card border-warning" data-waitlist>
    <div class="card-body">
        <h2 class="h5">Uygun saat bulamadınız mı?</h2>
        <p class="small text-muted">
            Seçtiğiniz doktorun bekleme listesine girin. Bir randevu iptal edildiğinde sıradaki hastaya
            30 dakika geçerli bir teklif gönderilir; teklifi "Randevularım" sayfasından kabul edebilirsiniz.
        </p>
        <form method="post" action="/waitlist" class="row g-2 align-items-end" novalidate>
            <?php require __DIR__ . '/../partials/csrf.php'; ?>
            <input type="hidden" name="doctor_id" value="<?= e($selectedDoctorId ?? '') ?>" data-waitlist-doctor>
            <div class="col-12 col-sm-6">
                <label for="desired_date" class="form-label">Tercih ettiğiniz gün <span class="text-muted">(boş = herhangi)</span></label>
                <input type="date" id="desired_date" name="desired_date" class="form-control" value="<?= e($old['desired_date'] ?? '') ?>">
            </div>
            <div class="col-12 col-sm-6">
                <button type="submit" class="btn btn-outline-warning w-100" data-waitlist-submit disabled>Bekleme Listesine Gir</button>
            </div>
        </form>
    </div>
</div>
