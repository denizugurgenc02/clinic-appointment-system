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

    // Yerleşik süper yönetici: veri kaynağında (JSON / MySQL) kaydı olmasa da her zaman vardır.
    // Koda düz şifre değil yalnızca hash'i yazılır. Varsayılan şifre: asdf1234
    // Üretimde SUPER_ADMIN_PASSWORD_HASH ortam değişkeniyle değiştirilmelidir.
    'super_admin' => [
        'username' => 'admin',
        'name' => 'Sistem Yöneticisi',
        'password_hash' => getenv('SUPER_ADMIN_PASSWORD_HASH') ?: '$2y$10$Vkde8vplwyHrJ2r5Bc90dOE1AkQn2XUA6wwVh1xl.ONVrvPmE7nsO',
    ],

    // Profil fotoğrafı yükleme kuralları
    'upload' => [
        'max_bytes' => 2 * 1024 * 1024,
        'allowed_mime' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ],
];
