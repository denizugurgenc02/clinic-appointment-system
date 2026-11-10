# Klinik Randevu Sistemi

PHP Programlama dersi dönem projesi. Hasta poliklinik ve doktor seçip boş saatlerden randevu alır; doktorların çalışma
takvimi ve izinleri tanımlanır; iptal olunca bekleme listesindeki ilk hastaya süreli teklif gider; sekreter paneli
randevuları yönetir.

Framework kullanılmadan, katmanlı mimariyle (router → controller → service → repository → view) yazılmıştır.

- Tasarım: [`docs/tasarim.md`](docs/tasarim.md)
- Test raporu ve gereksinim-test izlenebilirliği: [`docs/test-raporu.md`](docs/test-raporu.md)
- AI günlüğü: [`docs/ai-gunlugu/`](docs/ai-gunlugu/)

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

# 4. Örnek veriyi yükleyin (tarihler bugüne göre üretilir; tekrar çalıştırmak veriyi sıfırlar)
docker compose exec app php bin/seed.php
```

Uygulama: <http://localhost:8000> (port `.env` içindeki `APP_PORT` ile değiştirilebilir).

> **Aşama 2:** Veritabanı bağlantısı yoktur. Veriler `storage/data/*.json` dosyalarında tutulur
> (Repository arayüzlerinin JSON uygulaması). Aşama 3'te yalnızca `config/services.php` içindeki bağlamalar
> MySQL uygulamasına çevrilecektir.

## Demo Hesapları

Tüm hesapların şifresi: **`Klinik123!`**

| Rol | E-posta | Not |
| --- | --- | --- |
| Hasta | `hasta@klinik.test` | Ali Veli; Doç. Dr. Zeynep Demir'in bekleme listesinde 1. sırada |
| Doktor | `ayse.yilmaz@klinik.test` | Diğer doktorlar: `mehmet.kaya`, `zeynep.demir`, `can.ozturk`, `elif.sahin`, `ahmet.celik`, `selin.arslan` (`@klinik.test`) |
| Sekreter | `sekreter@klinik.test` | Fatma Koç |

## Demo Senaryosu (çekirdek problemler)

Seed çıktısında yazan **demo günü** (bugünden sonraki ilk iş günü) için:

1. **İzin günü / istisna:** Hasta olarak *Randevu Al* → Ortopedi → Prof. Dr. Ahmet Çelik: demo gününde hiç slot yok
   (tam gün izin). Uzm. Dr. Ayşe Yılmaz: demo günü 14:00–16:00 arası slot yok (kurul toplantısı).
2. **Öğle arası:** Herhangi bir doktorun ızgarasında 12:00–13:00 arasında slot üretilmez.
3. **Bekleme listesi:** Doç. Dr. Zeynep Demir'in demo günü tamamen dolu. Sekreter olarak *Randevular* ekranında
   o günün bir randevusunu iptal edin → `hasta@klinik.test` ile girin → *Randevularım*'da 30 dakikalık teklif
   kartı görünür → *Kabul Et*. Reddedilirse teklif sıradaki hastaya (Deniz Aydın) geçer.
4. **Eşzamanlılık:** `docker compose exec app vendor/bin/phpunit --filter ConcurrentBooking`
   (iki ayrı PHP süreci aynı slotu ister, yalnızca biri kabul edilir).
5. **Günlük rapor:** Sekreter → *Günlük Rapor*: liste, durum dağılımı, doluluk/iptal/gelmeme oranları.

Süresi dolan teklifler sayfalar açılırken otomatik işlenir; elle tetiklemek için:
`docker compose exec app php bin/process-waitlist.php`

## Geliştirme Komutları

Tüm komutlar konteyner içinde çalışır:

```bash
docker compose exec app composer test       # PHPUnit (73 test)
docker compose exec app composer analyse    # PHPStan (seviye 8)
docker compose exec app composer check      # ikisi birden
```

> Windows'ta Git Bash kullanıyorsanız yol dönüşümünü kapatmak için komutların başına `MSYS_NO_PATHCONV=1` ekleyin.

## Özellikler

| Rol | Özellikler |
| --- | --- |
| Hasta | Kayıt/giriş, poliklinik → doktor → **haftalık slot ızgarası (AJAX/JSON)**, not ile randevu, randevularım (yalnızca kendi), iptal, tarih değiştirme, bekleme listesi (teklif kabul/ret, sıradan çıkma), profil fotoğrafı |
| Doktor | Günlük randevu listesi (tarih seçici, durum sayıları), başlamış randevuyu *Tamamlandı / Gelmedi* işaretleme |
| Sekreter | Randevu arama (hasta adı/e-posta) ve filtreleme (poliklinik, doktor, durum, tarih), hasta adına randevu, iptal/değiştirme, doktor takvimi ve izin yönetimi, bekleme listesi izleme, günlük rapor |

## Klasör Yapısı

| Klasör | İçerik |
| --- | --- |
| `public/` | Tek giriş noktası (`index.php`), CSS/JS (`slots.js`: AJAX ızgara). Web sunucusunun kök dizini. |
| `src/Core/` | Kendi yazdığımız altyapı: Router, DI Container, Request/Response, Session, CSRF, View, Clock, EventDispatcher |
| `src/Controller/` | İstekleri karşılar, servisi çağırır, görünümü seçer |
| `src/Middleware/` | Rol yetkilendirmesi (RequirePatient, RequireSecretary ...) |
| `src/Service/` | İş mantığı: slot üretimi, çakışma önleme, bekleme listesi, rapor |
| `src/Repository/` | Arayüzler (`Contract/`), JSON uygulaması (`Json/`), Aşama 3'te MySQL |
| `src/Model/` | Varlıklar, enum'lar, olaylar |
| `views/` | Şablonlar (yalnızca gösterim) |
| `config/` | Ayarlar, DI bağlamaları, olay dinleyicileri, rotalar |
| `bin/` | `seed.php`, `process-waitlist.php` |
| `storage/` | JSON veriler, yüklenen fotoğraflar, loglar (git'e girmez, dışarıdan erişilemez) |
| `tests/` | PHPUnit: `Unit/` ve `Integration/` (eşzamanlılık) |
| `docs/` | Tasarım, test raporu, SRS, UI taslakları, AI günlüğü |
