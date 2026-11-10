<?php
/**
 * @var int             $status
 * @var string          $message
 * @var \Throwable|null $debug  Yalnızca APP_DEBUG=1 iken dolu
 */
?>
<div class="text-center py-5">
    <h1 class="display-4 fw-bold"><?= e($status) ?></h1>
    <p class="lead"><?= e($message) ?></p>
    <a href="/" class="btn btn-outline-primary">Ana sayfaya dön</a>
</div>

<?php if ($debug !== null): ?>
    <div class="alert alert-warning">
        <strong><?= e($debug::class) ?>:</strong> <?= e($debug->getMessage()) ?>
        <div class="small"><?= e($debug->getFile()) ?>:<?= e($debug->getLine()) ?></div>
    </div>
    <pre class="small bg-light p-3 overflow-auto"><?= e($debug->getTraceAsString()) ?></pre>
<?php endif; ?>
