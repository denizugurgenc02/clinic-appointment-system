#!/bin/sh
# Konteyner açılışı: "docker compose up" sonrası projeyi ek komut gerektirmeden çalışır hale getirir.
set -e

cd /var/www/html

# 1. PHP bağımlılıkları (ilk açılışta veya vendor/ silindiyse)
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Composer bağımlılıkları kuruluyor..."
    composer install --no-interaction --prefer-dist --no-progress
fi

# 2. Çalışma klasörleri; Apache (www-data) JSON verilerini, logları ve yüklenen dosyaları yazabilmeli
mkdir -p storage/data storage/logs storage/uploads
chown -R www-data:www-data storage

# 3. Örnek veri (yalnızca veri yoksa; mevcut veriye dokunulmaz)
if [ ! -f storage/data/users.json ]; then
    echo "[entrypoint] Örnek veri yükleniyor..."
    php bin/seed.php
    chown -R www-data:www-data storage
fi

echo "[entrypoint] Uygulama hazır: http://localhost:${APP_PORT:-8000}"

# 4. Asıl süreç (Apache)
exec "$@"
