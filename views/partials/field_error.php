<?php
/**
 * Bir alanın hata mesajı. Kullanım:
 *   <?php $field = 'email'; require __DIR__ . '/../partials/field_error.php'; ?>
 *
 * @var array<string, string> $errors
 * @var string                $field
 */
$errors ??= [];
?>
<?php if (isset($errors[$field])): ?>
    <div class="invalid-feedback d-block"><?= e($errors[$field]) ?></div>
<?php endif; ?>
