<?php
/**
 * @var list<\App\Model\WaitlistDetails> $entries
 * @var \DateTimeImmutable               $now
 */
?>
<h1 class="h3 mb-1">Bekleme Listesi</h1>
<p class="text-muted small mb-3">
    Açık kayıtlar (sırada / teklif bekleyen). Sıra her doktor için ayrı ve FIFO'dur.
    Süresi dolan teklifler bu sayfa açılırken işlenir ve slot sıradaki hastaya geçer.
</p>

<?php if ($entries === []): ?>
    <p class="text-muted">Bekleme listesinde kimse yok.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle bg-white">
            <thead><tr><th>Sıra</th><th>Hasta</th><th>Doktor</th><th>Tercih</th><th>Durum</th><th>Kayıt</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $w): ?>
                <tr>
                    <td><?= e($w->position ?? '—') ?></td>
                    <td><?= e($w->patientName) ?></td>
                    <td><?= e($w->doctorName) ?><div class="small text-muted"><?= e($w->departmentName) ?></div></td>
                    <td><?= e($w->entry->desiredDate?->format('d.m.Y') ?? 'Herhangi') ?></td>
                    <td>
                        <span class="badge text-bg-<?= e($w->entry->status->color()) ?>"><?= e($w->entry->status->label()) ?></span>
                        <?php if ($w->entry->offeredStartsAt !== null): ?>
                            <div class="small">
                                Teklif: <?= e($w->entry->offeredStartsAt->format('d.m H:i')) ?>,
                                son: <?= e($w->entry->offerExpiresAt?->format('H:i')) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e($w->entry->createdAt->format('d.m.Y H:i')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
