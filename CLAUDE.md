# CLAUDE.md

Bu dosya, bu depoda çalışan Claude Code (ve ekipteki diğer AI araçları) için proje bağlamını ve kuralları tanımlar.
Kaynak belgeler: `docs/proje_description.docx`, `docs/proje_requirements.docx`, `docs/proje_suggestion.docx` (hocanın isterleri).

> Durum: **İlk taslak (init).** Proje henüz iskelet aşamasında; bu dosya ilerledikçe genişletilecek.

## Proje Özeti

**Klinik Randevu Sistemi** — PHP Programlama dersi dönem projesi (Zorluk 4/5), 4 kişilik ekip.

Hasta poliklinik ve doktor seçip boş saatlerden randevu alır; doktorların çalışma takvimi ve izinleri tanımlanır; iptal olunca bekleme listesindeki ilk hastaya teklif gider; sekreter paneli randevuları yönetir.

### Çekirdek problem (notun belirleyicisi — yalnızca CRUD yeterli değildir)

1. **Slot üretimi:** Çalışma takviminden slot üretimi — çalışma günleri ve saatleri, öğle arası, randevu süresi, izin (`time_off`) ve istisna günleri.
2. **Çakışma önleme:** Aynı doktora aynı slota iki randevu verilemez (eşzamanlı isteklerde de).
3. **Bekleme listesi:** İptal olduğunda sıradaki hastaya süreli (ör. 30 dk) teklif gider; kabul etmezse / süre dolarsa bir sonrakine geçilir.

### Zorunlu özellikler

- En az **4 poliklinik** ve **6 doktor** (seed verisiyle)
- Haftalık slot ızgarası
- Hasta yalnızca **kendi** randevularını görür
- Randevu iptali ve değişikliği
- Sekreter ve doktor panelleri
- Bekleme listesi
- Günlük randevu listesi raporu

### Kullanılması beklenen PHP özellikleri

`DateTime`, `DateInterval`, `DatePeriod`; kuyruk mantığı; **Observer** (iptal olayı bekleme listesini tetikler); **PHP 8.1 enum** (randevu durumu); transaction; JOIN; **AJAX** (slotların sayfa yenilenmeden yüklenmesi); rol yetkilendirme.

### Asgari tablolar (en az 6)

`users`, `departments`, `doctors`, `schedules`, `time_off`, `appointments`, `waitlist`

### Kapsam dışı

SMS, e-posta, ödeme ve tıbbi kayıt **yoktur**. Bunlar için kod yazma.

### Sunumda kanıtlanacaklar (testlerle desteklenmeli)

- İzin gününde slot üretilmemesi
- Öğle arasının atlanması
- İptalde sıradaki hastaya teklifin gitmesi
- İki eşzamanlı randevu isteğinden yalnızca birinin kabulü

## Teknik Kısıtlar

- **PHP 8.2+**, Composer ile **PSR-4** autoload, namespace kullanımı, **PSR-12** kod stili.
- **Framework YASAK** (Laravel, Symfony, Slim, Lumen vb. ve bunların bileşenlerine dayanan yapılar). Router, controller, DI vb. mimariyi kendimiz kuruyoruz. Framework'e özgü kod/öneri üretme.
- CSS kütüphanesi (ör. Bootstrap) serbesttir.
- Dev bağımlılıkları: `phpunit/phpunit`, `phpstan/phpstan`.
- Veritabanı: MySQL/MariaDB, PDO ile.

## Docker

Proje **dockerize** çalışacak; yerelde WAMP/XAMPP/Laragon kurulumu gerekmez.

Planlanan servisler (dosyalar henüz oluşturulmadı):

- `app` — PHP 8.2+ (Apache veya Nginx + PHP-FPM), Composer içerir. **DocumentRoot = `public/`** (tarayıcıdan yalnızca `public/` erişilebilir olmalı).
- `db` — MySQL 8 / MariaDB; migration ve seed betikleri `database/` altından çalıştırılır.
- (Opsiyonel) `phpmyadmin`.

Kurallar:

- Tüm komutlar (composer, phpunit, phpstan, migration/seed) konteyner içinden çalıştırılır, ör. `docker compose exec app vendor/bin/phpunit`.
- Gizli bilgiler `.env` dosyasında tutulur ve depoya gönderilmez; örnek değerler `.env.example` içinde bulunur.

## Mimari (hocanın zorunlu kıldığı yapı)

**Katmanlı mimari:** `router → controller → service → repository → view`

| Katman | Sorumluluk |
|---|---|
| Router | `public/index.php` tek giriş noktası; isteği ilgili controller'a yönlendirir |
| Controller | İsteği karşılar, girdiyi alır, servisi çağırır, görünümü seçer. **İş mantığı içermez.** |
| Service | İş mantığı ve çekirdek algoritmalar (slot üretimi, çakışma kontrolü, bekleme listesi) burada |
| Repository | Veri erişimi. **Arayüz + uygulamalar**: Aşama 2'de JSON, Aşama 3'te PDO + MySQL |
| Model | Varlık sınıfları (entity), enum'lar |
| View | Yalnızca gösterim; iş mantığı yok, tüm çıktılar `htmlspecialchars` ile kaçışlı |

- Repository arayüzleri sayesinde JSON → MySQL geçişinde **yalnızca repository uygulaması değişir, servisler ve testler aynı kalır.** Servisler somut sınıfa değil arayüze bağımlı olmalı.
- **En az 2 tasarım deseni**, seçim gerekçesiyle birlikte (ör. Observer — iptal → bekleme listesi; Repository; Strategy vb.). Gerekçeler `docs/` altında belgelenir.

### Klasör yapısı (zorunlu temel yapı)

```
clinic-appointment-system/
├── public/            # index.php (tek giriş noktası), css, js, görseller
├── src/
│   ├── Controller/    # İstekleri karşılar, servisi çağırır, görünümü seçer
│   ├── Service/       # İş mantığı ve çekirdek algoritma burada
│   ├── Repository/    # Arayüzler + JSON ve MySQL uygulamaları
│   └── Model/         # Varlık sınıfları
├── views/             # Şablonlar (yalnızca gösterim, iş mantığı yok)
├── database/          # migration ve seed SQL dosyaları (Aşama 3)
├── tests/             # PHPUnit testleri
├── docs/              # SRS, diyagramlar, ai-gunlugu.md, test raporu
├── composer.json
└── README.md
```

Docker dosyaları (`Dockerfile`, `docker-compose.yml`, `docker/`) kök dizinde yer alacak.

## Ortak Asgari Şartlar (tüm projeler için)

- **Veritabanı:** En az 3NF, SQL migration dosyaları, seed betiği, en az bir JOIN'li rapor sorgusu.
- **Kullanıcı sistemi:** Kayıt, giriş, çıkış; roller (hasta, doktor, sekreter, gerekirse yönetici); **her işlemde yetki kontrolü**.
- **Güvenlik:** PDO hazırlıklı ifadeler, `password_hash`/`password_verify`, çıktı kaçışı (`htmlspecialchars`), CSRF token, girişte `session_regenerate_id`, güvenli dosya yükleme (tür kontrolü, rastgele ad).
- **Arama ve filtreleme:** En az bir arama ve bir filtre (ör. poliklinik, doktor, durum, tarih).
- **Rapor:** En az bir özet/istatistik ekranı (günlük randevu listesi + toplamlar/oranlar).
- **Örnek veri:** Seed betiğiyle yeterli veri; boş veritabanıyla demo yapılmaz.
- **Arayüz:** 375 px genişlikte yatay kaydırmasız responsive tasarım; anlaşılır başarı/hata mesajları; hatalı form gönderiminde girilen değerler korunur.
- **AJAX:** En az bir JSON döndüren uç nokta ve onu kullanan JavaScript (slot yükleme).
- **Eşzamanlılık:** Randevu oluşturma/teklif kabulünde transaction + satır kilidi (`SELECT ... FOR UPDATE`) veya koşullu `UPDATE` / unique kısıt.
- **Test ve kalite:** Çekirdek problem için **en az 10 PHPUnit testi**; PHPStan ile statik analiz (hatasız); gereksinim-test izlenebilirlik tablosu.
- **Süreç:** GitHub deposu, anlamlı commit geçmişi, pull request ile çalışma, GitHub Projects panosu (her kart bir gereksinim numarasına bağlı), `docs/ai-gunlugu.md`, kurulum talimatlı README.

## Dönem Planı

- **Aşama 2:** Veri JSON dosyalarında (Repository'nin JSON uygulaması).
- **Aşama 3:** PDO + MySQL uygulaması, migration ve seed.
- 3 aşama, 3 sunum, 14 hafta; final sunumları 13–14. haftalarda.

## Çalışma Kuralları (ekip + AI)

- **Açıklanamayan kod teslim edilmez.** Sunumda rastgele bir satır sorulacak; üretilen kod okunup anlaşılmalı.
- Büyük özellikleri küçük parçalara böl; **önce test, sonra kod**.
- Her değişiklikten sonra: çalıştır → PHPUnit → PHPStan → commit.
- İş mantığını controller'a veya view'a yazma; framework kodu önerme; güvenlik kontrollerini (yetki, CSRF, kaçış) atlama; eşzamanlılığı görmezden gelme; eski/kullanımdan kalkmış PHP fonksiyonları kullanma.
- Zaman/saat gerektiren mantık test edilebilir olmalı (ör. şimdiki zamanı dışarıdan alan bir `Clock` arayüzü).
- **AI günlüğü:** Önemli AI kullanımları `docs/ai-gunlugu.md` dosyasına eklenir: tarih, amaç, istem özeti, sonuç, değiştirilen/reddedilen kısım ve nedeni. AI'ın yanıldığı ve yakalanan durumlar özellikle not edilir.

### Git

- `master` korunur; her iş için ayrı branch açılır ve **pull request** ile birleştirilir.
- Commit mesajı ne yapıldığını söylemeli (ör. "Öğle arası slot atlama testi eklendi"; "güncelleme" kabul edilmez).
- Her çalışma oturumu sonunda commit + push.
- `.env`, şifreler ve `vendor/` depoya gönderilmez (`.gitignore`).

## Komutlar

> Docker ve Composer kurulumu yapıldığında doldurulacak.

```bash
# docker compose up -d
# docker compose exec app composer install
# docker compose exec app vendor/bin/phpunit
# docker compose exec app vendor/bin/phpstan analyse
```
