<?php

declare(strict_types=1);

/*
 * Uygulama ayarları. Ortama göre değişenler .env / docker-compose ile gelir.
 */

$root = dirname(__DIR__);

return [
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => getenv('APP_DEBUG') === '1',
    'timezone' => 'Europe/Istanbul',

    // Bekleme listesinde hastaya verilen teklifin geçerlilik süresi (dakika)
    'waitlist_offer_minutes' => 30,

    // Slot ızgarasında gösterilecek ileri hafta sayısı
    'booking_horizon_weeks' => 4,

    'paths' => [
        // Aşama 2: JSON veri dosyaları (tarayıcıdan erişilemez)
        'data' => $root . '/storage/data',
        'uploads' => $root . '/storage/uploads',
    ],

    // Profil fotoğrafı yükleme kuralları
    'upload' => [
        'max_bytes' => 2 * 1024 * 1024,
        'allowed_mime' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ],
];
