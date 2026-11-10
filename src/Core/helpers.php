<?php

declare(strict_types=1);

/*
 * Görünümlerde (views/) kullanılan küçük global yardımcılar.
 * composer.json -> autoload.files ile her istekte yüklenir.
 */

if (!function_exists('e')) {
    /**
     * HTML çıktısını XSS'e karşı kaçışlar. Görünümlerde kullanıcıdan gelen
     * HER değer bu fonksiyondan geçirilerek basılır.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return '';
    }
}
