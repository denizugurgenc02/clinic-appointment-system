# Klinik Randevu Sistemi

PHP Programlama dersi dönem projesi. Hasta poliklinik ve doktor seçip boş saatlerden randevu alır; doktorların çalışma
takvimi ve izinleri tanımlanır; iptal olunca bekleme listesindeki ilk hastaya süreli teklif gider; sekreter paneli
randevuları yönetir.

Framework kullanılmadan, katmanlı mimariyle (router → controller → service → repository → view) yazılmıştır.

- Tasarım: [`docs/tasarim.md`](docs/tasarim.md)
- Gereksinim-test izlenebilirlik tablosu: [`docs/izlenebilirlik.md`](docs/izlenebilirlik.md)
- Yapay zekâ günlüğü: [`docs/ai-gunlugu.md`](docs/ai-gunlugu.md) (oturum dosyaları `docs/ai-gunlugu/`)
- Süreç raporu: [`docs/surec-raporu.md`](docs/surec-raporu.md)

## Ekip

- 2307231001 - Hatice AYVAZ
- 2307231054 - Mehmet Efe KAYA
- 2307231029 - Eren ÇİT
- 2307231030 - Deniz Uğur GENÇ

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

Seed hesaplarının şifresi: **`Klinik123!`**

| Rol | E-posta | Not |
| --- | --- | --- |
| Hasta | `hasta@klinik.test` | Ali Veli; Doç. Dr. Zeynep Demir'in bekleme listesinde 1. sırada |
| Doktor | `ayse.yilmaz@klinik.test` | Diğer doktorlar: `mehmet.kaya`, `zeynep.demir`, `can.ozturk`, `elif.sahin`, `ahmet.celik`, `selin.arslan` (`@klinik.test`) |
| Sekreter | `sekreter@klinik.test` | Fatma Koç |
| **Süper yönetici** | kullanıcı adı **`admin`**, şifre **`asdf1234`** | Yerleşik hesap: seed çalıştırılmasa da (veri boşken de) vardır; hiçbir kısıta tabi değildir |

## Demo Senaryosu (çekirdek problemler)

Seed çıktısında yazan **demo günü** (bugünden sonraki ilk iş günü) için:

1. **İzin günü / istisna:** Hasta olarak *Randevu Al* → Ortopedi → Prof. Dr. Ahmet Çelik: demo gününde hiç slot yok
   (tam gün izin). Uzm. Dr. Ayşe Yılmaz: demo günü 14:00–16:00 arası slot yok (kurul toplantısı).
2. **Öğle arası:** Herhangi bir doktorun ızgarasında 12:00–13:00 arasında slot üretilmez.
3. **Bekleme listesi:** Doç. Dr. Zeynep Demir'in demo günü tamamen dolu. Sekreter olarak *Randevular* ekranında
   o günün bir randevusunu iptal edin → `hasta@klinik.test` ile girin → *Randevularım*'da 30 dakikalık teklif
   kartı görünür → *Kabul Et*. Reddedilirse teklif sıradaki hastaya (Deniz Aydın) geçer.
4. **Hata durumu:** Hasta, randevusuna 1 saatten az kala iptal edemez (FR4); geçersiz form gönderiminde
   alan hataları gösterilir ve girilen değerler korunur.
5. **Yetkisiz erişim:** Hasta olarak `/secretary` adresine gidin (403) veya başka hastanın randevu id'siyle
   `/appointments/{id}/reschedule` deneyin (reddedilir).
6. **Eşzamanlılık:** `docker compose exec app vendor/bin/phpunit --filter ConcurrentBooking`
   (iki ayrı PHP süreci aynı slotu ister, yalnızca biri kabul edilir).
7. **Günlük rapor:** Sekreter → *Günlük Rapor*: liste, durum dağılımı, doluluk/iptal/gelmeme oranları.

Süresi dolan teklifler sayfalar açılırken otomatik işlenir; elle tetiklemek için:
`docker compose exec app php bin/process-waitlist.php`

## Yönetim API'si (süper yönetici)

Yönetim POST uçları HTML formunun yanında JSON da kabul eder. Oturum çerezi ve `X-CSRF-Token` başlığı
(sayfadaki `<meta name="csrf-token">`) gereklidir:

```bash
curl -b cookies.txt -H "Content-Type: application/json" -H "X-CSRF-Token: <jeton>" \
     -d '{"name":"Dermatoloji","description":"Cilt hastalıkları"}' http://localhost:8000/admin/departments
# 201 {"message":"...","data":{"id":6,"name":"Dermatoloji",...}}   |   422 {"error":"...","errors":{"name":"..."}}
```

Uçlar: `POST /admin/departments`, `POST /admin/departments/{id}`, `POST /admin/doctors`, `POST /admin/secretaries`, `GET /api/departments`.

> Üretimde varsayılan yönetici şifresi `SUPER_ADMIN_PASSWORD_HASH` ortam değişkeniyle değiştirilmelidir
> (`php -r 'echo password_hash("yeni-sifre", PASSWORD_DEFAULT);'`).

## Geliştirme Komutları

Tüm komutlar konteyner içinde çalışır:

```bash
docker compose exec app vendor/bin/phpunit  # PHPUnit (93 test)  — kısaca: composer test
docker compose exec app composer analyse    # PHPStan (seviye 8)
docker compose exec app composer lint       # PSR-12 (phpcs); otomatik düzeltme: composer lint-fix
docker compose exec app composer check      # üçü birden
```

> Windows'ta Git Bash kullanıyorsanız yol dönüşümünü kapatmak için komutların başına `MSYS_NO_PATHCONV=1` ekleyin.

## Özellikler

| Rol | Özellikler |
| --- | --- |
| Hasta | Kayıt/giriş, poliklinik → doktor → **haftalık slot ızgarası (AJAX/JSON)**, not ile randevu, randevularım (yalnızca kendi), iptal, tarih değiştirme, bekleme listesi (teklif kabul/ret, sıradan çıkma), profil fotoğrafı |
| Doktor | Günlük randevu listesi (tarih seçici, durum sayıları), başlamış randevuyu *Tamamlandı / Gelmedi* işaretleme |
| Sekreter | Randevu arama (hasta adı/e-posta) ve filtreleme (poliklinik, doktor, durum, tarih), hasta adına randevu, iptal/değiştirme, doktor takvimi ve izin yönetimi, bekleme listesi izleme, günlük rapor |
| Süper yönetici | Yukarıdakilerin tamamı (kısıtsız) + poliklinik ekleme/güncelleme, doktor ve sekreter hesabı açma (form veya JSON) |

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
| `tests/` | PHPUnit: `Unit/`, `Integration/` (eşzamanlılık), `Support/InMemory` (bellek içi sahte repository'ler) |
| `docs/` | Tasarım, test raporu, SRS, UI taslakları, AI günlüğü |
