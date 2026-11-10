# Klinik Randevu Sistemi

PHP Programlama dersi dönem projesi. Hasta poliklinik ve doktor seçip boş saatlerden randevu alır; doktorların çalışma
takvimi ve izinleri tanımlanır; iptal olunca bekleme listesindeki ilk hastaya süreli teklif gider; sekreter paneli
randevuları yönetir.

Framework kullanılmadan, katmanlı mimariyle (router → controller → service → repository → view) yazılmıştır.
**Aşama 2:** Veritabanı bağlantısı yoktur; veriler `storage/data/*.json` dosyalarında tutulur.

| Belge | İçerik |
| --- | --- |
| [`docs/tasarim.md`](docs/tasarim.md) | Mimari, iş kuralları, sınıf / sıralama / ER diyagramları |
| [`docs/izlenebilirlik.md`](docs/izlenebilirlik.md) | Gereksinim (SRS FR/NFR) → test eşleştirmesi |
| [`docs/ai-gunlugu.md`](docs/ai-gunlugu.md) | Yapay zekâ günlüğü (oturum dosyaları `docs/ai-gunlugu/`) |
| [`docs/surec-raporu.md`](docs/surec-raporu.md) | Aşama 2 süreç raporu |
| [`docs/Phase-2.txt`](docs/Phase-2.txt) | Aşama 2 ödev isterleri |

## Ekip

| Öğrenci No | Ad Soyad |
| --- | --- |
| 2307231001 | Hatice AYVAZ |
| 2307231054 | Mehmet Efe KAYA |
| 2307231029 | Eren ÇİT |
| 2307231030 | Deniz Uğur GENÇ |

## Gereksinimler

Uygulama Docker içinde çalışır; bilgisayarınıza PHP, Apache, Composer veya MySQL kurmanız **gerekmez**.

| Yazılım | Sürüm | Not |
| --- | --- | --- |
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | 4.x veya üzeri (Docker Compose v2 dahil) | Windows'ta WSL 2 altyapısı açık olmalı. Kurulumdan sonra Docker Desktop'ın **çalışır durumda** olduğundan emin olun. |
| [Git](https://git-scm.com/) | Herhangi bir güncel sürüm | Depoyu klonlamak için |
| Boş port | `8000` | Doluysa `.env` içindeki `APP_PORT` değiştirilebilir |

Konteynerin içinde gelenler: PHP 8.2 + Apache (`mod_rewrite`, DocumentRoot = `public/`), Composer 2.
Geliştirme bağımlılıkları (Composer ile kurulur): PHPUnit 10, PHPStan 2, PHP_CodeSniffer (PSR-12).

## Kurulum

Aşağıdaki adımlar temiz bir klasörde, projeyi daha önce hiç kurmamış biri için yazılmıştır.

**1. Depoyu klonlayın ve klasöre girin**

```bash
git clone https://github.com/denizugurgenc02/clinic-appointment-system.git
cd clinic-appointment-system
```

**2. Ortam dosyasını oluşturun**

```bash
cp .env.example .env          # Linux / macOS / Git Bash
copy .env.example .env        # Windows Komut İstemi (cmd)
```

`.env` içindeki varsayılanlar (`APP_PORT=8000`, `APP_DEBUG=0`) genellikle değiştirilmeden kullanılabilir.

**3. Konteyneri derleyip başlatın** (ilk seferde imaj indirildiği için birkaç dakika sürebilir)

```bash
docker compose up -d --build
```

**4. PHP bağımlılıklarını kurun** (`vendor/` klasörü oluşur)

```bash
docker compose exec app composer install
```

**5. Örnek veriyi yükleyin**

```bash
docker compose exec app php bin/seed.php
```

Betik 5 poliklinik, 7 doktor, 1 sekreter, 8 hasta, randevular ve bekleme listesi kayıtları oluşturur. Tarihler
çalıştırıldığı güne göre üretilir; tekrar çalıştırmak tüm veriyi sıfırlar.

**6. Uygulamayı açın:** <http://localhost:8000>

**7. (İsteğe bağlı) Kurulumu doğrulayın**

```bash
docker compose exec app composer check    # PSR-12 + 93 PHPUnit testi + PHPStan; hepsi hatasız olmalı
```

### Durdurma ve sorun giderme

| Durum | Çözüm |
| --- | --- |
| Uygulamayı durdurmak | `docker compose down` (veriler `storage/data` içinde kalır) |
| `port is already allocated` hatası | `.env` içinde `APP_PORT` değerini değiştirip `docker compose up -d` |
| `Cannot connect to the Docker daemon` | Docker Desktop'ı başlatın, ardından komutu tekrarlayın |
| `vendor/autoload.php` bulunamadı | 4. adımı (`composer install`) çalıştırın |
| Sayfalar boş, poliklinik yok | 5. adımı (`php bin/seed.php`) çalıştırın |
| Hata ayrıntısını görmek | `storage/logs/app.log`; yalnızca yerel geliştirmede `.env` → `APP_DEBUG=1` |
| Windows Git Bash'te yol hatası | Komutların başına `MSYS_NO_PATHCONV=1` ekleyin |

## Süper Yönetici (Yerleşik Hesap)

| Kullanıcı adı | Şifre |
| --- | --- |
| **`admin`** | **`asdf1234`** |

- Giriş ekranında "E-posta veya kullanıcı adı" alanına `admin` yazılır.
- Hesap **koda gömülüdür**: veri kaynağında (JSON, Aşama 3'te MySQL) kaydı yoktur. Seed çalıştırılmamış, veriler boş olsa bile giriş yapılabilir.
  - `SuperAdminUserRepository` (Decorator), kullanıcı repository'sini sarar ve bu hesabı ekler.
  - Kullanıcı adı ve şifre hash'i `config/app.php` → `super_admin` içindedir. Koda düz şifre değil, yalnızca bcrypt hash'i yazılmıştır.
- **Hiçbir rol veya sahiplik kısıtına tabi değildir:** tüm panellere girer; her randevuyu iptal eder, değiştirir ve sonuçlandırır.
- Ek olarak **Yönetim** (`/admin`) ekranından poliklinik ekler ve günceller, doktor ve sekreter hesabı açar.

> **Güvenlik notu:** `asdf1234` herkesin bildiği bir demo şifresidir. Gerçek kullanımda `.env` dosyasına yeni bir hash
> yazılarak değiştirilmelidir:
> ```bash
> docker compose exec app php -r 'echo password_hash("yeni-sifre", PASSWORD_DEFAULT), PHP_EOL;'
> # .env:  SUPER_ADMIN_PASSWORD_HASH='<çıktı>'
> ```

### Yönetim API'si

Yönetim POST uçları HTML formunun yanında JSON da kabul eder. Süper yönetici oturum çerezi ve `X-CSRF-Token`
başlığı (sayfadaki `<meta name="csrf-token">`) gereklidir:

```bash
curl -b cookies.txt -H "Content-Type: application/json" -H "X-CSRF-Token: <jeton>" \
     -d '{"name":"Dermatoloji","description":"Cilt hastalıkları"}' http://localhost:8000/admin/departments
# 201 {"message":"...","data":{"id":6,"name":"Dermatoloji",...}}   |   422 {"error":"...","errors":{"name":"..."}}
```

Uçlar: `POST /admin/departments`, `POST /admin/departments/{id}`, `POST /admin/doctors`, `POST /admin/secretaries`,
`GET /api/departments`.

## Demo Hesapları (seed verisi)

Seed hesaplarının şifresi: **`Klinik123!`**

| Rol | E-posta | Not |
| --- | --- | --- |
| Hasta | `hasta@klinik.test` | Ali Veli; Doç. Dr. Zeynep Demir'in bekleme listesinde 1. sırada |
| Doktor | `ayse.yilmaz@klinik.test` | Diğer doktorlar: `mehmet.kaya`, `zeynep.demir`, `can.ozturk`, `elif.sahin`, `ahmet.celik`, `selin.arslan` (`@klinik.test`) |
| Sekreter | `sekreter@klinik.test` | Fatma Koç |
| Süper yönetici | `admin` / `asdf1234` | Yukarıdaki bölüme bakın |

## Demo Senaryosu

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
6. **Kısmi yenileme (AJAX):** *Randevu Al* ekranında doktor ve hafta değiştikçe slot ızgarası sayfa yenilenmeden
   `GET /api/slots` (JSON) ile yüklenir.
7. **Eşzamanlılık:** `docker compose exec app vendor/bin/phpunit --filter ConcurrentBooking`
   (iki ayrı PHP süreci aynı slotu ister, yalnızca biri kabul edilir).
8. **Günlük rapor:** Sekreter → *Günlük Rapor*: liste, durum dağılımı, doluluk / iptal / gelmeme oranları.

Süresi dolan teklifler sayfalar açılırken otomatik işlenir; elle tetiklemek için:
`docker compose exec app php bin/process-waitlist.php`

## Geliştirme Komutları

Tüm komutlar konteyner içinde çalışır:

```bash
docker compose exec app vendor/bin/phpunit  # PHPUnit (93 test) — kısaca: composer test
docker compose exec app composer analyse    # PHPStan (seviye 8)
docker compose exec app composer lint       # PSR-12 (phpcs); otomatik düzeltme: composer lint-fix
docker compose exec app composer check      # üçü birden
```

## Özellikler

| Rol | Özellikler |
| --- | --- |
| Hasta | Kayıt/giriş, poliklinik → doktor → **haftalık slot ızgarası (AJAX/JSON)**, not ile randevu, randevularım (yalnızca kendi), iptal ve tarih değiştirme (en geç 1 saat önce), bekleme listesi (teklif kabul/ret, sıradan çıkma), profil fotoğrafı |
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
| `src/Repository/` | Arayüzler (`Contract/`), JSON uygulaması (`Json/`), yerleşik yönetici (`BuiltIn/`); Aşama 3'te MySQL |
| `src/Model/` | Varlıklar, enum'lar, olaylar |
| `views/` | Şablonlar (yalnızca gösterim) |
| `config/` | Ayarlar, DI bağlamaları, olay dinleyicileri, rotalar |
| `bin/` | `seed.php`, `process-waitlist.php` |
| `storage/` | JSON veriler, yüklenen fotoğraflar, loglar (git'e girmez, dışarıdan erişilemez) |
| `tests/` | PHPUnit: `Unit/`, `Integration/` (eşzamanlılık), `Support/InMemory` (bellek içi sahte repository'ler) |
| `docs/` | Tasarım, izlenebilirlik, süreç raporu, AI günlüğü, SRS, UI taslakları, ödev isterleri |
