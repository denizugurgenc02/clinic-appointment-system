# Klinik Randevu Sistemi

PHP Programlama dersi dönem projesi. Hasta poliklinik ve doktor seçip boş saatlerden randevu alır; doktorların çalışma
takvimi ve izinleri tanımlanır; iptal olunca bekleme listesindeki ilk hastaya süreli teklif gider; sekreter paneli
randevuları yönetir.

Framework kullanılmadan, katmanlı mimariyle (router → controller → service → repository → view) yazılmıştır.
Ayrıntılı tasarım: [`docs/tasarim.md`](docs/tasarim.md).

## Ekip

- 2307231001 - Hatice AYVAZ
- 2307231054 - Mehmet Efe KAYA
- 2307231029 - Eren ÇİT

## Gereksinimler

- Docker Desktop (Docker Compose v2)
- Yerelde PHP, Apache veya MySQL kurulumu **gerekmez**.

## Kurulum

```bash
# 1. Ortam dosyasını oluşturun
cp .env.example .env

# 2. Konteyneri derleyip başlatın
docker compose up -d --build

# 3. Bağımlılıkları kurun
docker compose exec app composer install
```

Uygulama: <http://localhost:8000> (port `.env` içindeki `APP_PORT` ile değiştirilebilir).

> **Aşama 2:** Veritabanı yoktur; veriler `storage/data/*.json` dosyalarındadır. Örnek veri betiği
> (`bin/seed.php`) Faz 4'te eklenecektir.

## Geliştirme Komutları

Tüm komutlar konteyner içinde çalışır:

```bash
docker compose exec app composer test       # PHPUnit
docker compose exec app composer analyse    # PHPStan (seviye 8)
docker compose exec app composer check      # ikisi birden
```

> Windows'ta Git Bash kullanıyorsanız yol dönüşümünü kapatmak için komutların başına `MSYS_NO_PATHCONV=1` ekleyin.

## Klasör Yapısı

| Klasör | İçerik |
| --- | --- |
| `public/` | Tek giriş noktası (`index.php`), CSS/JS. Web sunucusunun kök dizini. |
| `src/Core/` | Kendi yazdığımız altyapı: Router, DI Container, Request/Response, Session, CSRF, View, Clock, EventDispatcher |
| `src/Controller/` | İstekleri karşılar, servisi çağırır, görünümü seçer |
| `src/Service/` | İş mantığı: slot üretimi, çakışma önleme, bekleme listesi |
| `src/Repository/` | Arayüzler (`Contract/`), JSON uygulaması (`Json/`), Aşama 3'te MySQL |
| `src/Model/` | Varlıklar, enum'lar, olaylar |
| `views/` | Şablonlar (yalnızca gösterim) |
| `config/` | Ayarlar, DI bağlamaları, olay dinleyicileri, rotalar |
| `storage/` | JSON veriler, yüklenen fotoğraflar, loglar (git'e girmez, dışarıdan erişilemez) |
| `tests/` | PHPUnit testleri |
| `docs/` | Tasarım belgesi, SRS, UI taslakları, AI günlüğü |
