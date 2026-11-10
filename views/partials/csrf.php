<?php
/**
 * Her POST formunun içine eklenir:
 *   <?php require __DIR__ . '/../partials/csrf.php'; ?>
 *
 * @var string $csrfToken
 */
?>
<input type="hidden" name="_csrf" value="<?= e($csrfToken ?? '') ?>">
