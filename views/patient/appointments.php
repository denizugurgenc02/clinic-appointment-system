<?php
/**
 * @var list<\App\Model\AppointmentDetails> $upcoming
 * @var list<\App\Model\AppointmentDetails> $past
 * @var list<\App\Model\WaitlistDetails>    $waitlist
 * @var \DateTimeImmutable                  $now
 * @var string                              $csrfToken
 */

use App\Model\Enum\WaitlistStatus;

$offers = array_filter($waitlist, static fn ($w) => $w->entry->status === WaitlistStatus::Offered);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Randevularım</h1>
    <a href="/appointments/book" class="btn btn-primary">+ Yeni Randevu</a>
</div>

<?php foreach ($offers as $offer): ?>
    <?php $minutes = max(0, (int) ceil(($offer->entry->offerExpiresAt?->getTimestamp() - $now->getTimestamp()) / 60)); ?>
    <div class="card border-warning shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">🎉 Size bir randevu teklifi var!</h2>
            <p class="mb-2">
                <strong><?= e($offer->doctorName) ?></strong> (<?= e($offer->departmentName) ?>) —
                <strong><?= e($offer->entry->offeredStartsAt?->format('d.m.Y H:i')) ?></strong>
            </p>
            <p class="small text-muted mb-3">
                Teklif <?= e($offer->entry->offerExpiresAt?->format('H:i')) ?>'e kadar geçerli
                (yaklaşık <?= e($minutes) ?> dk). Süre dolarsa sıradaki hastaya geçer.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="/waitlist/<?= e($offer->entry->id) ?>/accept">
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <button class="btn btn-success">Kabul Et</button>
                </form>
                <form method="post" action="/waitlist/<?= e($offer->entry->id) ?>/decline">
                    <?php require __DIR__ . '/../partials/csrf.php'; ?>
                    <button class="btn btn-outline-secondary">Reddet</button>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<h2 class="h5 mt-4">Yaklaşan Randevular</h2>
<?php if ($upcoming === []): ?>
    <p class="text-muted">Yaklaşan randevunuz yok.</p>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($upcoming as $row): ?>
            <?php $a = $row->appointment; ?>
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h3 class="h6 mb-1"><?= e($a->startsAt->format('d.m.Y H:i')) ?> - <?= e($a->endsAt->format('H:i')) ?></h3>
                            <span class="badge text-bg-<?= e($a->status->color()) ?> align-self-start"><?= e($a->status->label()) ?></span>
                        </div>
                        <p class="mb-1"><?= e($row->doctorName) ?></p>
                        <p class="small text-muted mb-2"><?= e($row->departmentName) ?></p>
                        <?php if ($a->patientNote !== null): ?>
                            <p class="small mb-2">📝 <?= e($a->patientNote) ?></p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="/appointments/<?= e($a->id) ?>/reschedule">Tarihi Değiştir</a>
                            <form method="post" action="/appointments/<?= e($a->id) ?>/cancel"
                                  onsubmit="return confirm('Randevuyu iptal etmek istediğinize emin misiniz?');">
                                <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                <button class="btn btn-sm btn-outline-danger">İptal Et</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 class="h5 mt-4">Bekleme Listesi</h2>
<?php if ($waitlist === []): ?>
    <p class="text-muted">Bekleme listesinde kaydınız yok.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle bg-white">
            <thead><tr><th>Doktor</th><th>Tercih</th><th>Durum</th><th>Sıra</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($waitlist as $w): ?>
                <tr>
                    <td><?= e($w->doctorName) ?><div class="small text-muted"><?= e($w->departmentName) ?></div></td>
                    <td><?= e($w->entry->desiredDate?->format('d.m.Y') ?? 'Herhangi bir gün') ?></td>
                    <td><span class="badge text-bg-<?= e($w->entry->status->color()) ?>"><?= e($w->entry->status->label()) ?></span></td>
                    <td><?= e($w->position ?? '-') ?></td>
                    <td class="text-end">
                        <?php if ($w->entry->status->isOpen()): ?>
                            <form method="post" action="/waitlist/<?= e($w->entry->id) ?>/leave">
                                <?php require __DIR__ . '/../partials/csrf.php'; ?>
                                <button class="btn btn-sm btn-link text-danger p-0">Sıradan çık</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2 class="h5 mt-4">Geçmiş ve İptal Edilen Randevular</h2>
<?php if ($past === []): ?>
    <p class="text-muted">Kayıt yok.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle bg-white">
            <thead><tr><th>Tarih</th><th>Doktor</th><th>Durum</th></tr></thead>
            <tbody>
            <?php foreach ($past as $row): ?>
                <tr>
                    <td><?= e($row->appointment->startsAt->format('d.m.Y H:i')) ?></td>
                    <td><?= e($row->doctorName) ?><div class="small text-muted"><?= e($row->departmentName) ?></div></td>
                    <td><span class="badge text-bg-<?= e($row->appointment->status->color()) ?>"><?= e($row->appointment->status->label()) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
