# Klinik Randevu Sistemi — Proje Dokümanı (Aşama 2)

> **Ders:** PHP Programlama · **Grup:** Hatice AYVAZ (2307231001), Mehmet Efe KAYA (2307231054), Eren ÇİT (2307231029),
> Deniz Uğur GENÇ (2307231030)
> **Depo:** https://github.com/denizugurgenc02/clinic-appointment-system · **Dal:** `asama-2/prototip` (PR #35)
> **Tarih:** 24.11.2026

Bu belge, projede **neyin planlandığını ve hangi dosyada yapıldığını**, **hangi analizlerin yapıldığını ve
sonuçlarını**, **hangi testin hangi dosyada neyi sınadığını**, **arayüzleri (PHP arayüzleri, ekranlar, uç
noktalar)**, **use case'leri** ve **yapay zekâ kaynaklı düzeltmeleri** tek yerde toplar. Ayrıntılı kaynak belgeler:

| Konu | Ayrıntılı belge |
| --- | --- |
| Gereksinimler (FR/NFR), use case diyagramları | `docs/Klinik Yönetim Sistemi SRS.pdf` (Aşama 1) |
| Mimari, sınıf/sıralama/ER diyagramları, desenler, riskler | `docs/tasarim.md` |
| Gereksinim–test izlenebilirlik tablosu | `docs/izlenebilirlik.md` |
| Yapay zekâ günlüğü (dizin + oturumlar) | `docs/ai-gunlugu.md`, `docs/ai-gunlugu/*.md` |
| Süreç raporu, iş dağılımı | `docs/surec-raporu.md` |
| Arayüz taslakları | `docs/UI/*.jpeg`, `docs/UI/sekreterpanel.png` |
| Kurulum | `README.md` |

---

## 1. Proje özeti ve çekirdek problem

Hasta poliklinik ve doktor seçip doktorun takviminden üretilen boş saatlerden randevu alır. Sekreter randevuları,
doktor takvimlerini ve izinlerini yönetir; doktor günlük listesini görür. Veriler Aşama 2'de **JSON dosyalarında**
(`storage/data/*.json`) tutulur; Aşama 3'te yalnızca Repository uygulaması MySQL'e geçecektir.

Projeyi CRUD'dan ayıran üç çekirdek problem ve çözüldükleri yer:

| # | Çekirdek problem | Çözüldüğü dosya / fonksiyon | Kanıtlayan test dosyası |
| --- | --- | --- | --- |
| 1 | **Slot üretimi** (çalışma saatleri, öğle arası, randevu süresi, izin/istisna, geçmiş saat) | `src/Service/SlotGenerator.php` → `generate()`; `src/Service/SlotService.php` → `weekGrid()`, `bookableSlot()` | `tests/Unit/Service/SlotGeneratorTest.php` |
| 2 | **Çakışma önleme** (aynı slota iki randevu yok, eşzamanlı isteklerde de) | `src/Service/AppointmentService.php` → `book()`, `reschedule()`; `src/Repository/Json/FileLockTransactionManager.php` → `run()` (`flock` + atomik `rename`) | `tests/Integration/ConcurrentBookingTest.php`, `tests/Unit/Service/AppointmentServiceTest.php` |
| 3 | **Bekleme listesi** (iptalde sıradakine 30 dk teklif, ret/süre dolumunda sonrakine geç) | `src/Service/WaitlistService.php` → `offerFreedSlot()`, `accept()`, `decline()`, `expireStale()`; Observer: `src/Service/WaitlistListener.php`, `config/events.php` | `tests/Unit/Service/WaitlistServiceTest.php` |

---

## 2. Yapılan analizler ve sonuçları

| # | Analiz | Ne yapıldı | Sonuç | Belge / dosya |
| --- | --- | --- | --- | --- |
| A1 | **Gereksinim analizi (SRS)** | Paydaşlar ve roller belirlendi; 19 fonksiyonel (FR1–FR19) ve 5 fonksiyonel olmayan (NFR1–NFR5) gereksinim yazıldı; Hasta / Doktor / Sekreter için use case diyagramları çizildi. | Her FR için kabul kriterli GitHub issue'su açıldı (#10–#27) ve üyelere dağıtıldı. | `docs/Klinik Yönetim Sistemi SRS.pdf`, `docs/surec-raporu.md` §2 |
| A2 | **Risk analizi** | Olasılık × etki tablosu (9 risk): çift randevu, izin/öğle arasının yanlış yansıması, teklifin yanlış hastaya gitmesi, süre dolumunun işlenmemesi, yetkisiz erişim, yarım yazılan JSON vb. | Her risk için önlem belirlendi ve teste bağlandı (ör. çift randevu → `flock` + eşzamanlılık testi). | `docs/tasarim.md` §7 |
| A3 | **Veri modeli / normalizasyon analizi** | Sınıflardan tablolara geçiş, Chen ER diyagramı, anahtar ve kısıtlar, 3NF kontrolü. | 8 tablo: `users`, `departments`, `doctors`, `patients`, `schedules`, `time_off`, `appointments`, `waitlist`; 3NF'e uygun. Aşama 2'de her tablo bir JSON dosyası. | `docs/tasarim.md` §4 |
| A4 | **Tasarım tutarlılık analizi** (AI destekli, oturum #004 Etkileşim 1) | Aşama 1 belgesinin bölümleri ve mevcut iskelet kod karşılaştırıldı. | 7+ çelişki bulundu (klasör yapısı, "AJAX'ta JSON kullanılmaz" ifadesi, enum/tablo adları, öğle arası modeli, 404 sayfasında XSS…). Her madde için ekip kararı verildi (bkz. §8). | `docs/ai-gunlugu/004-10.11.2026.md`, `docs/tasarim.md` §1.7 |
| A5 | **İş kuralı analizi** | Çekirdek problem, test edilebilir 12 kurala (K1–K12) indirildi. | Her kural en az bir teste bağlandı. | `docs/tasarim.md` §1.6, `docs/izlenebilirlik.md` §4 |
| A6 | **Eşzamanlılık analizi** | Kilitli ve kilitsiz yazma, iki ayrı PHP süreciyle karşılaştırıldı (kayıt 300 ms geciktirilerek). | Kilitli: biri `BOOKED`, diğeri `TAKEN`. Kilitsiz: ikisine de "alındı" denildi (çift randevu / kayıp güncelleme) → tek kilit dosyalı transaction seçildi. | `tests/Integration/ConcurrentBookingTest.php`, `docs/surec-raporu.md` §3 |
| A7 | **Hoca isterlerine (Phase-2) uyum denetimi** | `docs/Phase-2.txt` maddeleri tek tek kodla karşılaştırıldı. | Eksikler kapatıldı (PRG, alan bazlı hata, hata loglama, AJAX hata mesajı, `flock` + geçici dosya, kural bozma deneyi). | `docs/ai-gunlugu/004-10.11.2026.md` Etkileşim 7 |
| A8 | **Test kalitesi analizi (kural bozma / mutasyon deneyi)** | Üretim kodunda 10 kural tek tek bilerek bozuldu, test paketi çalıştırıldı. | **10/10 bozma en az bir testi kırdı.** İlk denemede M1'i hiçbir test yakalamadı → test düzeltildi (bkz. §8). | `docs/izlenebilirlik.md` §5 |
| A9 | **Statik analiz ve kod stili** | PHPStan seviye 8, PHP_CodeSniffer PSR-12. | PHPStan: **0 hata**. PSR-12: **0 hata** (yalnızca >120 karakter satır uyarıları). | `phpstan.neon`, `phpcs.xml` |
| A10 | **Test sonucu** | `vendor/bin/phpunit` | **93 test / 250 doğrulama, hepsi başarılı** (89 test metodu; `RepositoryContractTest`'in 4 metodu JSON ve bellek içi olmak üzere 2'şer kez çalışır). | `phpunit.xml`, `docs/izlenebilirlik.md` |
| A11 | **Uçtan uca HTTP kontrolü** | Docker'daki Apache üzerinde `curl` ile seed verisiyle senaryolar denendi. | Roller, yetkisiz erişim (403/401), PRG, çift randevu, iptal → teklif → kabul, bozuk JSON → sade 500 mesajı, sahte PNG reddi doğrulandı. | `docs/izlenebilirlik.md` §7 |

---

## 3. Plan: neyi planladık, hangi dosyada yaptık

### 3.1 Faz planı

| Faz | Planlanan iş | Yapıldığı dosyalar | Durum |
| --- | --- | --- | --- |
| 0 | Tasarım belgesinin netleştirilmesi | `docs/tasarim.md` | ✅ |
| 1 | Altyapı: Docker, Router, DI Container, Request/Response, Session/Flash/CSRF, View, Clock, EventDispatcher | `Dockerfile`, `docker-compose.yml`, `public/index.php`, `src/Core/**` | ✅ |
| 2 | Model: varlıklar, enum'lar, olay | `src/Model/**` | ✅ |
| 3 | `SlotGenerator` (önce test) | `src/Service/SlotGenerator.php`, `tests/Unit/Service/SlotGeneratorTest.php` | ✅ |
| 4 | Repository arayüzleri + JSON uygulamaları, transaction, seed | `src/Repository/Contract/*`, `src/Repository/Json/*`, `bin/seed.php` | ✅ |
| 5 | Randevu alma/iptal/değiştirme, Observer, bekleme listesi, eşzamanlılık testi | `src/Service/AppointmentService.php`, `WaitlistService.php`, `WaitlistListener.php`, `config/events.php` | ✅ |
| 6 | Kayıt/giriş/çıkış, rol middleware'leri, profil fotoğrafı | `src/Service/AuthService.php`, `src/Middleware/*`, `src/Service/ProfilePhotoService.php` | ✅ |
| 7 | Ekranlar: hasta, doktor, sekreter, günlük rapor | `src/Controller/*`, `views/**`, `public/assets/**` | ✅ |
| 8 | Kalite: PHPStan seviye 8, izlenebilirlik tablosu | `phpstan.neon`, `docs/izlenebilirlik.md` | ✅ |
| 9 | Süper yönetici + poliklinik/doktor/sekreter oluşturma | `src/Repository/BuiltIn/SuperAdminUserRepository.php`, `src/Service/ClinicAdminService.php`, `src/Controller/AdminController.php` | ✅ |
| Aşama 3 | MySQL migration + seed, `Pdo*Repository`, `PdoTransactionManager` | `database/`, `src/Repository/Mysql/` (planlandı) | ⏳ |

### 3.2 Gereksinim → fonksiyon → dosya eşlemesi

| Gereksinim | Fonksiyon(lar) | Dosya(lar) |
| --- | --- | --- |
| FR1 Poliklinik/doktor/tarihe göre arama, haftalık ızgara | `ApiController::doctors()`, `ApiController::slots()` → `SlotService::weekGrid()` → `SlotGenerator::generate()`; JS: `slots.js` | `src/Controller/ApiController.php`, `src/Service/SlotService.php`, `src/Service/SlotGenerator.php`, `public/assets/js/slots.js`, `views/partials/slot_picker.php` |
| FR2 Uygun saate randevu, dolu ise reddet | `AppointmentController::store()` → `AppointmentService::book()` → `SlotService::bookableSlot()` | `src/Controller/AppointmentController.php`, `src/Service/AppointmentService.php` |
| FR3 / FR7 Doktor / sekreter hesabı | `AdminController::storeDoctor()`, `storeSecretary()` → `ClinicAdminService::createDoctor()`, `createSecretary()` | `src/Controller/AdminController.php`, `src/Service/ClinicAdminService.php` |
| FR4 İptal (hasta en az 1 saat önce) | `AppointmentController::cancel()` → `AppointmentService::cancel()`, `cancellationNoticeFor()`; `Appointment::canBeCancelled()` | `src/Service/AppointmentService.php`, `src/Model/Appointment.php`, `config/app.php` (`cancellation_notice_minutes`) |
| FR5 İptal onayı | `confirm()` (tarayıcı) | `public/assets/js/app.js`, `views/patient/appointments.php` |
| FR6 / FR14 Çalışma takvimi, öğle arası, izin | `ScheduleController::addRow()`, `deleteRow()`, `addTimeOff()`, `deleteTimeOff()` → `ScheduleService::*`; `Schedule::overlaps()`, `TimeOff::overlaps()` | `src/Controller/ScheduleController.php`, `src/Service/ScheduleService.php`, `src/Model/Schedule.php`, `src/Model/TimeOff.php` |
| FR8 Sekreter yetkilendirme | `RoleMiddleware::process()`, `RequireSecretary::roles()` | `src/Middleware/RoleMiddleware.php`, `src/Middleware/Require*.php`, `config/routes.php` |
| FR9 İptalde bekleme listesine teklif | `AppointmentService::cancel()` → `EventDispatcher::dispatch(AppointmentCancelled)` → `WaitlistListener::handle()` → `WaitlistService::offerFreedSlot()`; `WaitlistController::accept()/decline()` | `src/Service/WaitlistService.php`, `src/Service/WaitlistListener.php`, `src/Core/Event/EventDispatcher.php`, `src/Model/Event/AppointmentCancelled.php`, `src/Model/WaitlistEntry.php` |
| FR10 Eşzamanlı istekte tek kabul | `TransactionManagerInterface::run()` → `FileLockTransactionManager::run()`; `JsonStore::beginTransaction()/commit()/rollBack()` | `src/Repository/Json/FileLockTransactionManager.php`, `src/Repository/Json/JsonStore.php` |
| FR11 Bekleme listesine kayıt | `WaitlistController::join()` → `WaitlistService::join()`; `WaitlistRepositoryInterface::waitingQueue()` | `src/Controller/WaitlistController.php`, `src/Service/WaitlistService.php` |
| FR12 Tarih/saat değiştirme | `AppointmentController::edit()`, `reschedule()` → `AppointmentService::reschedule()` | `src/Controller/AppointmentController.php`, `views/patient/reschedule.php` |
| FR13 Randevu ayrıntıları | `AppointmentService::forPatient()`, `detailsFor()` | `views/patient/appointments.php` |
| FR15 Bekleme kuyruğunu görme | `SecretaryController::waitlist()` → `WaitlistService::openEntries()` | `views/secretary/waitlist.php` |
| FR16 Günlük rapor | `ReportController::daily()` → `ReportService::daily()`; `DailyReport::cancellationRate()`, `occupancyRate()`, `noShowRate()` | `src/Service/ReportService.php`, `src/Model/DailyReport.php`, `views/reports/daily.php` |
| FR17 Doktorun kendi listesi | `DoctorPanelController::index()`, `updateStatus()` → `AppointmentService::forDoctorOn()`, `markOutcome()` | `src/Controller/DoctorPanelController.php`, `views/doctor/index.php` |
| FR19 Hasta kaydı | `AuthController::register()` → `AuthService::register()` | `src/Service/AuthService.php`, `views/auth/register.php` |
| NFR1 Rol tabanlı yetki + sahiplik | `RoleMiddleware::process()`; servislerde sahiplik kontrolü (`AppointmentService::cancel()` vb.) | `src/Middleware/*`, `src/Service/AppointmentService.php` |
| NFR2 Parola güvenliği | `password_hash` / `password_verify` (`AuthService::register()`, `attempt()`); girişte `Session::regenerate()` (`session_regenerate_id`) | `src/Service/AuthService.php`, `src/Core/Session/Session.php` |
| NFR3 Doğru HTTP kodları | `HttpException::notFound()`, `methodNotAllowed()`, `forbidden()`; `Router::dispatch()` | `src/Core/Exception/HttpException.php`, `src/Core/Router/Router.php` |
| Güvenli dosya yükleme | `ProfileController::uploadPhoto()` → `ProfilePhotoService::upload()` (`finfo`, `getimagesize`, 2 MB, `random_bytes`) | `src/Service/ProfilePhotoService.php`, `src/Core/Http/UploadedFile.php` |
| CSRF | `CsrfMiddleware::process()`, `Csrf::token()`, `isValid()` | `src/Core/Middleware/CsrfMiddleware.php`, `src/Core/Session/Csrf.php`, `views/partials/csrf.php` |
| PRG + flash + eski girdi | `AbstractController::redirect()`, `back()`, `failed()`; `Session::flash()`, `flashInput()`, `flashErrors()` | `src/Controller/AbstractController.php`, `src/Core/Session/Session.php` |
| Çıktı kaçışı | `e()` (`htmlspecialchars`), `View::render()` | `src/Core/helpers.php`, `src/Core/View/View.php` |
| Hata yönetimi / log | `Application::handle()` (kullanıcıya sade mesaj, ayrıntı `storage/logs/app.log`) | `src/Core/Application.php`, `views/errors/error.php` |
| Seed verisi (5 poliklinik, 7 doktor) | `bin/seed.php` | `bin/seed.php` → `storage/data/*.json` |

### 3.3 Dosya ve fonksiyon rehberi (katman katman)

**Router / Core (framework yerine kendi altyapımız)**

| Dosya | Sınıf | Public fonksiyonlar | Görev |
| --- | --- | --- | --- |
| `public/index.php` | — | — | Tek giriş noktası (Front Controller) |
| `src/Core/Application.php` | `Application` | `container()`, `handle()` | Bootstrap, middleware zinciri, hata → 404/403/500 sayfası veya JSON |
| `src/Core/Router/Router.php` | `Router` | `get()`, `post()`, `addGlobalMiddleware()`, `routes()`, `dispatch()` | Yol + yönteme göre controller bulur, 404/405 |
| `src/Core/Router/Route.php` | `Route` | `middleware()`, `match()`, `method()`, `pattern()`, `handler()`, `middlewareList()` | `{id}` parametreli yol eşleştirme |
| `src/Core/Container.php` | `Container` | `bind()`, `singleton()`, `instance()`, `has()`, `get()` | DI: arayüz → uygulama, autowiring, döngü tespiti |
| `src/Core/Config.php` | `Config` | `get()`, `string()`, `int()`, `array()`, `bool()` | `config/app.php` değerleri |
| `src/Core/Http/Request.php` | `Request` | `fromGlobals()`, `method()`, `path()`, `query()`, `input()`, `all()`, `file()`, `uploadedFile()`, `header()`, `param()`, `params()`, `withRouteParams()`, `wantsJson()` | HTTP isteği (JSON gövde dahil) |
| `src/Core/Http/Response.php` | `Response` | `html()`, `json()`, `redirect()`, `withHeader()`, `send()` … | HTML / JSON / 302 yanıt |
| `src/Core/Http/UploadedFile.php` | `UploadedFile` | `fromArray()`, `moveTo()` | Yüklenen dosya |
| `src/Core/Session/Session.php` | `Session` | `start()`, `get()`, `set()`, `regenerate()`, `destroy()`, `flash()`, `pullFlashes()`, `flashInput()`, `flashErrors()`, `pullErrors()`, `pullOldInput()` … | Oturum, flash mesaj, eski form girdisi |
| `src/Core/Session/Csrf.php` | `Csrf` | `token()`, `isValid()` | CSRF jetonu (`hash_equals`) |
| `src/Core/Middleware/CsrfMiddleware.php` | `CsrfMiddleware` | `process()` | GET dışı isteklerde jeton kontrolü → 403 |
| `src/Core/View/View.php` | `View` | `share()`, `render()`, `partial()` | Şablon + layout, yol geçişi engeli |
| `src/Core/helpers.php` | — | `e()` | `htmlspecialchars` kısayolu |
| `src/Core/Clock/*` | `SystemClock`, `FrozenClock` | `now()`; `FrozenClock::at()`, `set()`, `advance()` | Test edilebilir zaman |
| `src/Core/Event/EventDispatcher.php` | `EventDispatcher` | `listen()`, `dispatch()`, `listenersFor()` | Observer öznesi |
| `src/Core/Exception/HttpException.php` | `HttpException` | `notFound()`, `methodNotAllowed()`, `forbidden()`, `csrfMismatch()` | HTTP hata kodları |
| `config/routes.php`, `config/services.php`, `config/events.php`, `config/app.php` | — | — | Rotalar, DI bağlamaları, olay → dinleyici, ayarlar |

**Middleware (rol yetkilendirme)**

| Dosya | Görev |
| --- | --- |
| `src/Middleware/RoleMiddleware.php` → `process()` | Ortak rol kontrolü: ziyaretçi → `/login` (API'de 401), yanlış rol → 403; süper yönetici her rolü geçer |
| `RequireAuthenticated`, `RequirePatient`, `RequireDoctor`, `RequireSecretary`, `RequirePatientOrSecretary`, `RequireSuperAdmin` → `roles()` | Her rotada hangi rollerin geçebileceğini söyler |
| `src/Middleware/ShareCurrentUser.php` → `process()` | Giriş yapan kullanıcıyı görünümlere paylaştırır |

**Controller** (iş mantığı yok; girdiyi alır → servisi çağırır → görünüm/yönlendirme)

| Dosya | Fonksiyonlar | Çağırdığı servisler |
| --- | --- | --- |
| `AbstractController.php` | `render()`, `redirect()`, `back()`, `user()`, `success()`, `failed()`, `toInt()`, `toDate()`, `requireInt()`, `requireDate()` | Ortak yardımcılar (PRG, flash, girdi dönüştürme) |
| `AuthController.php` | `showLogin()`, `login()`, `showRegister()`, `register()`, `logout()` | `AuthService` |
| `HomeController.php` | `index()` | `ClinicDirectoryService` |
| `ApiController.php` | `doctors()`, `slots()` (JSON) | `ClinicDirectoryService`, `SlotService`, `WaitlistService::expireStale()` |
| `AppointmentController.php` | `create()`, `store()`, `index()`, `edit()`, `reschedule()`, `cancel()` | `AppointmentService`, `WaitlistService`, `ClinicDirectoryService` |
| `WaitlistController.php` | `join()`, `accept()`, `decline()`, `leave()` | `WaitlistService` |
| `DoctorPanelController.php` | `index()`, `updateStatus()` | `AppointmentService::forDoctorOn()`, `markOutcome()` |
| `SecretaryController.php` | `index()` (arama/filtre), `create()`, `store()`, `waitlist()` | `AppointmentService::search()`, `book()`, `WaitlistService::openEntries()` |
| `ScheduleController.php` | `index()`, `show()`, `addRow()`, `deleteRow()`, `addTimeOff()`, `deleteTimeOff()` | `ScheduleService` |
| `ReportController.php` | `daily()` | `ReportService` |
| `ProfileController.php` | `show()`, `uploadPhoto()`, `photo()` | `ProfilePhotoService` |
| `AdminController.php` | `index()`, `departments()`, `storeDepartment()`, `updateDepartment()`, `storeDoctor()`, `storeSecretary()` | `ClinicAdminService` |
| `HealthController.php` | `show()` | Sağlık kontrolü (`/api/health`) |

**Service** (iş kuralları ve çekirdek algoritmalar)

| Dosya | Public fonksiyonlar | Görev / kurallar |
| --- | --- | --- |
| `SlotGenerator.php` | `generate()` | **Saf** slot üretimi: `DatePeriod`/`DateInterval` ile gün gün, satır satır; öğle arası (K2), satır sınırı (K2), izin (K4), geçmiş (K5), dolu (K6) |
| `SlotService.php` | `weekStart()`, `currentWeekStart()`, `weekGrid()`, `weekNavigation()`, `bookableSlot()`, `findSlot()`, `dayCapacity()` | Repository'den veriyi toplayıp `SlotGenerator`'a verir; seçilen saatin gerçekten alınabilir olduğunu sunucuda doğrular |
| `AppointmentService.php` | `book()`, `cancel()`, `reschedule()`, `markOutcome()`, `cancellationNoticeFor()`, `detailsFor()`, `forPatient()`, `forDoctorOn()`, `search()` | Transaction içinde çakışma kontrolü (K6, K7), sahiplik (K8), iptal süresi (K9), commit sonrası `AppointmentCancelled` yayını |
| `WaitlistService.php` | `join()`, `offerFreedSlot()`, `accept()`, `decline()`, `leave()`, `expireStale()`, `forPatient()`, `openEntries()` | FIFO kuyruk (K10, K11), 30 dk teklif (K12), kabulde slotu yeniden kontrol |
| `WaitlistListener.php` | `handle()` | Observer gözlemcisi: iptal olayında `offerFreedSlot()` çağırır |
| `ScheduleService.php` | `schedules()`, `upcomingTimeOffs()`, `addRow()`, `deleteRow()`, `addTimeOff()`, `deleteTimeOff()` | Takvim satırı çakışması (K3), izinle çakışan randevuların raporlanması |
| `AuthService.php` | `register()`, `attempt()`, `logout()`, `user()`, `refresh()` | Kayıt doğrulama, `password_hash`/`password_verify`, `session_regenerate_id` |
| `ReportService.php` | `daily()` | Günlük liste (JOIN'li arama) + durum toplamları ve oranlar |
| `ProfilePhotoService.php` | `upload()`, `photoFor()` | Güvenli yükleme, fotoğrafı görme yetkisi |
| `ClinicAdminService.php` | `createDepartment()`, `updateDepartment()`, `createDoctor()`, `createSecretary()` | Süper yönetici işlemleri |
| `ClinicDirectoryService.php` | `departments()`, `doctors()`, `doctor()`, `patient()`, `searchPatients()` | Okuma amaçlı dizin |
| `Exception/*` | `ValidationException::errors()` | `AccessDenied`, `NotFound`, `DomainRule`, `SlotAlreadyTaken`, `Validation` → kullanıcıya anlaşılır mesaj |

**Model**

| Dosya | Public fonksiyonlar | Görev |
| --- | --- | --- |
| `Appointment.php` | `book()`, `canBeCancelled()`, `cancel()`, `markOutcome()`, `overlaps()` | Randevu varlığı ve durum geçişleri |
| `WaitlistEntry.php` | `accepts()`, `offer()`, `isOfferExpired()`, `accept()`, `decline()`, `expire()`, `returnToQueue()`, `leave()` | Bekleme kaydının durum makinesi |
| `Schedule.php` | `startOn()`, `endOn()`, `overlaps()`, `dayName()` | Haftalık çalışma satırı |
| `TimeOff.php` | `overlaps()` | İzin / istisna aralığı |
| `Slot.php` | `isAvailable()`, `toArray()` | Değer nesnesi (JSON'a dönüşür) |
| `DailyReport.php` | `count()`, `total()`, `occupied()`, `cancellationRate()`, `occupancyRate()`, `noShowRate()` | Rapor hesapları |
| `User.php`, `Doctor.php`, `Patient.php`, `Department.php` | `User::is()`, `hasFullAccess()`; `Doctor::displayName()` | Varlıklar |
| `AppointmentDetails.php`, `WaitlistDetails.php`, `AppointmentFilter.php` | — | JOIN sonucu ve filtre nesneleri |
| `Enum/AppointmentStatus.php` | `isActive()`, `label()`, `color()` | **PHP 8.1 enum:** `Booked`, `Completed`, `Cancelled`, `NoShow` |
| `Enum/WaitlistStatus.php` | `isOpen()`, `label()`, `color()` | `Waiting`, `Offered`, `Accepted`, `Declined`, `Expired` |
| `Enum/UserRole.php` | `label()`, `homePath()` | `Patient`, `Doctor`, `Secretary`, `SuperAdmin` |
| `Enum/SlotStatus.php` | — | `Available`, `Booked`, `Past` |
| `Event/AppointmentCancelled.php` | — | Observer olayı |

**Repository** — arayüzler §4.1'de; JSON uygulamaları:

| Dosya | Görev |
| --- | --- |
| `Json/JsonStore.php` → `rows()`, `find()`, `insert()`, `update()`, `delete()`, `truncate()`, `beginTransaction()`, `commit()`, `rollBack()` | JSON dosyalarını okur/yazar; geçici dosyaya yazıp `rename` ile atomik değiştirir; hata olursa geri alır |
| `Json/FileLockTransactionManager.php` → `run()` | `storage/data/.lock` üzerinde `flock(LOCK_EX)`; iç içe çağrıları destekler |
| `Json/Json*Repository.php` (8 sınıf) | Arayüzlerin JSON uygulamaları (JOIN'ler bellekte yapılır) |
| `Json/Row.php` | Satır ↔ nesne dönüşümleri (`toUser()`, `toDoctor()`, `date()` …) |
| `BuiltIn/SuperAdminUserRepository.php` | **Decorator:** herhangi bir `UserRepositoryInterface`'i sarıp yerleşik `admin` hesabını ekler |

**View** (`views/`, yalnızca gösterim, tüm çıktılar `e()` ile kaçışlı): `layouts/main.php`, `partials/{csrf,field_error,slot_picker}.php`,
`auth/{login,register}.php`, `home/index.php`, `patient/{book,appointments,reschedule}.php`, `doctor/index.php`,
`secretary/{appointments,book,doctors,doctor,waitlist}.php`, `reports/daily.php`, `profile/index.php`,
`admin/index.php`, `errors/error.php`. **JavaScript:** `public/assets/js/slots.js` (AJAX ızgara), `app.js` (`fetchJson`, onay, hata mesajı).

---

## 4. Arayüzler

### 4.1 PHP arayüzleri (interface) ve uygulamaları

Servisler somut sınıflara değil **bu arayüzlere** bağımlıdır; bağlama `config/services.php`'de yapılır. Aşama 3'te
yalnızca sağ sütundaki uygulamalar değişecektir.

| Arayüz (`src/…`) | Metotlar | Aşama 2 uygulaması | Test uygulaması | Aşama 3 (plan) |
| --- | --- | --- | --- | --- |
| `Repository/Contract/AppointmentRepositoryInterface` | `find`, `save`, `activeForDoctorBetween`, `activeForPatientBetween`, `search` | `JsonAppointmentRepository` | `InMemoryAppointmentRepository`, `SlowSaveAppointmentRepository` | `PdoAppointmentRepository` |
| `…/DepartmentRepositoryInterface` | `all`, `find`, `findByName`, `create`, `update` | `JsonDepartmentRepository` | `InMemoryDepartmentRepository` | `Pdo…` |
| `…/DoctorRepositoryInterface` | `find`, `all`, `create` | `JsonDoctorRepository` | `InMemoryDoctorRepository` | `Pdo…` |
| `…/PatientRepositoryInterface` | `find`, `create`, `search` | `JsonPatientRepository` | `InMemoryPatientRepository` | `Pdo…` |
| `…/ScheduleRepositoryInterface` | `forDoctor`, `find`, `add`, `delete` | `JsonScheduleRepository` | `InMemoryScheduleRepository` | `Pdo…` |
| `…/TimeOffRepositoryInterface` | `forDoctor`, `find`, `add`, `delete` | `JsonTimeOffRepository` | `InMemoryTimeOffRepository` | `Pdo…` |
| `…/UserRepositoryInterface` | `find`, `findByEmail`, `create`, `updatePhoto` | `JsonUserRepository` (+ `SuperAdminUserRepository` decorator) | `InMemoryUserRepository` | `Pdo…` |
| `…/WaitlistRepositoryInterface` | `find`, `save`, `openForPatientAndDoctor`, `waitingQueue`, `offeredForSlot`, `expiredOffers`, `details` | `JsonWaitlistRepository` | `InMemoryWaitlistRepository` | `Pdo…` |
| `…/TransactionManagerInterface` | `run(Closure)` | `FileLockTransactionManager` | `InMemoryTransactionManager` | `PdoTransactionManager` (`BEGIN/COMMIT` + `FOR UPDATE`) |
| `Core/Clock/ClockInterface` | `now()` | `SystemClock` | `FrozenClock` | aynı |
| `Core/Event/EventListenerInterface` | `handle(object)` | `WaitlistListener` | `RecordingListener` (stub) | aynı |
| `Core/Middleware/MiddlewareInterface` | `process(Request, Closure)` | `CsrfMiddleware`, `RoleMiddleware`, `ShareCurrentUser` | `BlockingMiddleware`, `Inner/OuterMiddleware` | aynı |

### 4.2 Kullanıcı arayüzü (ekranlar)

Taslaklar `docs/UI/` altındadır; ekranlar Bootstrap 5 ile 375 px genişlikte yatay kaydırmasız çalışır.

| Ekran | Rol | View dosyası | Taslak | Önemli öğeler |
| --- | --- | --- | --- | --- |
| Ana sayfa | Herkes | `views/home/index.php` | `docs/UI/anasayfa.jpeg` | Poliklinik ve doktor listesi |
| Giriş / Kayıt | Herkes | `views/auth/login.php`, `register.php` | `girisyap.jpeg`, `kayitol.jpeg` | CSRF, alan bazlı hata, girilen değerler korunur |
| Randevu al | Hasta | `views/patient/book.php` + `partials/slot_picker.php` | `randevupanel.jpeg` | **AJAX haftalık slot ızgarası**, hafta ileri/geri, not, bekleme listesine gir |
| Randevularım | Hasta | `views/patient/appointments.php` | — | Aktif/geçmiş, iptal (onaylı), değiştir, bekleyen teklif (kalan süre, kabul/ret) |
| Tarih değiştir | Hasta / Sekreter | `views/patient/reschedule.php` | — | Aynı slot ızgarası |
| Doktor paneli | Doktor | `views/doctor/index.php` | — | Günlük liste, Completed / NoShow |
| Sekreter paneli | Sekreter | `views/secretary/appointments.php` | `sekreterpanel.png` | Arama (hasta adı/e-posta) + filtre (poliklinik, doktor, durum, tarih) |
| Hasta adına randevu | Sekreter | `views/secretary/book.php` | — | Hasta arama + slot ızgarası |
| Takvim ve izin | Sekreter | `views/secretary/doctors.php`, `doctor.php` | — | Çalışma satırı ve izin ekle/sil |
| Bekleme listesi | Sekreter | `views/secretary/waitlist.php` | — | Sıra numaraları, durum |
| Günlük rapor | Sekreter | `views/reports/daily.php` | — | Liste + toplamlar, iptal/doluluk/gelmeme oranı |
| Profil | Giriş yapan | `views/profile/index.php` | — | Profil fotoğrafı yükleme |
| Yönetim | Süper yönetici | `views/admin/index.php` | — | Poliklinik, doktor, sekreter oluşturma |
| Hata | Herkes | `views/errors/error.php` | — | Sade mesaj (yığın izi yok) |

### 4.3 HTTP / JSON uç noktaları (`config/routes.php`)

| Yöntem | Yol | Controller::metot | Rol |
| --- | --- | --- | --- |
| GET/POST | `/login`, `/register`; POST `/logout` | `AuthController` | Herkes |
| GET | `/api/doctors?department_id=` → **JSON** | `ApiController::doctors` | Hasta, Sekreter |
| GET | `/api/slots?doctor_id=&week=` → **JSON** | `ApiController::slots` | Hasta, Sekreter |
| GET / POST | `/appointments`, `/appointments/book` / `/appointments` | `AppointmentController::index/create/store` | Hasta |
| GET/POST | `/appointments/{id}/reschedule`, POST `/appointments/{id}/cancel` | `AppointmentController::edit/reschedule/cancel` | Hasta (kendi), Sekreter |
| POST | `/waitlist`, `/waitlist/{id}/accept|decline|leave` | `WaitlistController` | Hasta |
| GET / POST | `/doctor`, `/doctor/appointments/{id}/status` | `DoctorPanelController` | Doktor |
| GET / POST | `/secretary`, `/secretary/appointments/new`, `/secretary/appointments`, `/secretary/waitlist` | `SecretaryController` | Sekreter |
| GET / POST | `/secretary/doctors[/{id}]`, `…/schedules`, `…/time-off`, `…/delete` | `ScheduleController` | Sekreter |
| GET | `/reports/daily?date=&department_id=` | `ReportController::daily` | Sekreter |
| GET / POST | `/profile`, `/profile/photo`, `/users/{id}/photo` | `ProfileController` | Giriş yapan |
| GET / POST | `/admin`, `/api/departments` (JSON), `/admin/departments[/{id}]`, `/admin/doctors`, `/admin/secretaries` | `AdminController` | Süper yönetici |
| GET | `/api/health` | `HealthController::show` | Herkes |

---

## 5. Use case'ler

Aktörler: **Hasta**, **Doktor**, **Sekreter**, **Süper Yönetici**, **Sistem** (zamanlayıcı/olay). Use case
diyagramları SRS §2.2.1–2.2.3'tedir. Aşağıda her use case'in akışı ve kodda/testte nerede karşılandığı verilmiştir.

| UC | Aktör | Ad | Ana akış (özet) | Alternatif / hata akışı | FR | Controller → Service | View | Test |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UC-01 | Ziyaretçi | Kayıt ol | Ad, e-posta, telefon, parola girilir → hasta oluşturulur, oturum açılır | Eksik alan / aynı e-posta → alan bazlı hata, değerler korunur | FR19 | `AuthController::register` → `AuthService::register` | `auth/register.php` | `AuthServiceTest` |
| UC-02 | Tüm roller | Giriş / çıkış | E-posta + parola → `session_regenerate_id` → rolün ana sayfası | Yanlış parola → genel mesaj | NFR2 | `AuthController::login/logout` → `AuthService::attempt/logout` | `auth/login.php` | `AuthServiceTest`, `SuperAdminTest` |
| UC-03 | Hasta, Sekreter | Boş slotları görüntüle | Poliklinik → doktor seçilir; JS `/api/slots`'tan JSON alır, haftalık ızgarayı çizer | Sunucu hatası → ızgarada hata mesajı | FR1 | `ApiController::slots` → `SlotService::weekGrid` → `SlotGenerator::generate` | `partials/slot_picker.php`, `slots.js` | `SlotGeneratorTest`, `AccessControlTest` |
| UC-04 | Hasta | Randevu al | Boş slot seçilir, not yazılır → transaction içinde kontrol + kayıt → PRG | Slot doldu / geçmiş / öğle arası / izin / hastanın çakışan randevusu → mesaj | FR2, FR10 | `AppointmentController::store` → `AppointmentService::book` | `patient/book.php` | `AppointmentServiceTest`, `ConcurrentBookingTest` |
| UC-05 | Hasta | Randevularımı gör | Yalnızca kendi randevuları ve teklifleri listelenir | Başkasının id'si → 403/404 | FR13, K8 | `AppointmentController::index` → `forPatient` | `patient/appointments.php` | `AccessControlTest::testPatientSeesOnlyOwnAppointments` |
| UC-06 | Hasta, Sekreter | Randevu iptal et | Onay → durum `Cancelled` → commit sonrası `AppointmentCancelled` olayı → UC-09 tetiklenir | Hasta için <60 dk kala red; başkasının randevusu red | FR4, FR5 | `AppointmentController::cancel` → `AppointmentService::cancel` | `patient/appointments.php` | `AppointmentServiceTest` |
| UC-07 | Hasta, Sekreter | Randevu değiştir | Yeni slot seçilir → tek transaction'da yeni alınır, eski iptal edilir | Yeni slot doluysa eski randevu korunur | FR12 | `AppointmentController::reschedule` → `AppointmentService::reschedule` | `patient/reschedule.php` | `AppointmentServiceTest::testRescheduleBooksNewSlotAndCancelsOldOne`, `testFailedRescheduleKeepsOriginalAppointment` |
| UC-08 | Hasta | Bekleme listesine gir | Doktor (+ isteğe bağlı tarih) için kuyruğa eklenir | Aynı doktora ikinci açık kayıt → red | FR11 | `WaitlistController::join` → `WaitlistService::join` | `patient/book.php` | `WaitlistServiceTest::testPatientCannotJoinSameDoctorsQueueTwice` |
| UC-09 | Sistem | Boşalan slotu teklif et | İptal olayı → `WaitlistListener` → FIFO'da uygun ilk hastaya 30 dk teklif | Kuyruk boş → teklif yok | FR9 | `WaitlistListener::handle` → `WaitlistService::offerFreedSlot` | — | `WaitlistServiceTest` |
| UC-10 | Hasta | Teklifi kabul / ret et | Kabul → slot hâlâ boşsa randevu oluşur; ret → sıradaki hastaya teklif | Slot başkasınca alınmışsa hasta sırasını kaybetmeden kuyruğa döner | FR9 | `WaitlistController::accept/decline` → `WaitlistService::accept/decline` | `patient/appointments.php` | `WaitlistServiceTest` |
| UC-11 | Sistem | Süresi dolan teklifi işle | Sayfa/AJAX açılışında `expireStale()` → `Expired` → sıradakine teklif | — | FR9 | `WaitlistService::expireStale`, `bin/process-waitlist.php` | — | `WaitlistServiceTest::testExpiredOfferMovesToNextPatientAfter30Minutes` |
| UC-12 | Doktor | Günlük listemi gör, sonuç işaretle | Tarih seçilir; geçmiş randevu `Completed` / `NoShow` yapılır | Başlamamış randevu veya başka doktorun randevusu → red | FR17 | `DoctorPanelController::index/updateStatus` → `forDoctorOn/markOutcome` | `doctor/index.php` | `AppointmentServiceTest::testDoctorCan…`, `testDoctorCannot…` |
| UC-13 | Sekreter | Randevu ara / filtrele | Hasta adı/e-posta + poliklinik, doktor, durum, tarih filtresi | — | FR8 | `SecretaryController::index` → `AppointmentService::search` | `secretary/appointments.php` | `RepositoryContractTest::testSearchJoinsNamesAndAppliesFilters` |
| UC-14 | Sekreter | Hasta adına randevu al | Hasta aranır → slot seçilir → UC-04 ile aynı kurallar | Aynı | FR2 | `SecretaryController::create/store` → `AppointmentService::book` | `secretary/book.php` | `AppointmentServiceTest` |
| UC-15 | Sekreter | Takvim ve izin yönet | Çalışma satırı (gün, başlangıç, bitiş) ve izin/istisna ekle/sil | Çakışan satır / geçersiz saat → hata; izinle çakışan randevular bildirilir | FR6, FR14 | `ScheduleController::*` → `ScheduleService::*` | `secretary/doctor.php` | `ScheduleServiceTest`, `SlotGeneratorTest` |
| UC-16 | Sekreter | Bekleme kuyruğunu gör | Doktor bazında sıra numaralı liste | — | FR15 | `SecretaryController::waitlist` → `WaitlistService::openEntries` | `secretary/waitlist.php` | `RepositoryContractTest::testWaitlistQueueIsFifoWithIdAsTieBreaker` |
| UC-17 | Sekreter | Günlük rapor al | Tarih (+ poliklinik) seçilir → liste, toplamlar, oranlar | — | FR16 | `ReportController::daily` → `ReportService::daily` | `reports/daily.php` | `ReportServiceTest` |
| UC-18 | Giriş yapan | Profil fotoğrafı yükle | Dosya seçilir → tür/boyut kontrolü → rastgele adla kaydedilir | PHP içerikli sahte PNG, >2 MB → red | Ortak şart | `ProfileController::uploadPhoto` → `ProfilePhotoService::upload` | `profile/index.php` | `ProfilePhotoServiceTest` |
| UC-19 | Süper yönetici | Poliklinik / doktor / sekreter oluştur | Form veya JSON POST → 201 | Aynı ad / geçersiz alan → 422 + alan hataları | FR3, FR7 | `AdminController::*` → `ClinicAdminService::*` | `admin/index.php` | `SuperAdminTest` |

---

## 6. Testler: hangi dosya neyi test ediyor

**Çalıştırma:** `docker compose exec app vendor/bin/phpunit` (okunabilir liste: `--testdox`).
**Son sonuç:** 93 test / 250 doğrulama, hepsi başarılı; PHPStan seviye 8 hatasız.

**Tekrarlanabilirlik:** Zaman `FrozenClock` ile sabitlenir (16.11.2026 Pzt 08:00); servis testleri gerçek JSON
yerine `tests/Support/InMemory/` altındaki bellek içi sahte repository'lerle çalışır; sahte repository'nin JSON ile
aynı davrandığı `RepositoryContractTest` ile güvenceye alınır. Yalnızca eşzamanlılık testi gerçek JSON + `flock`
kullanır (geçici klasörde).

### 6.1 Çekirdek problem testleri

**`tests/Unit/Service/SlotGeneratorTest.php`** — slot üretim algoritması (`SlotGenerator::generate`)

| Test | Neyi kanıtlar |
| --- | --- |
| `testLunchBreakIsSkipped` | 09:00–12:00 ve 13:00–17:00 satırları arasında (öğle arası) slot üretilmez |
| `testSlotNeverCrossesTheEndOfAWorkingBlock` | **Sınır:** 40 dk slotta 11:00 son slottur; 12:20'ye taşacak 11:40 üretilmez |
| `testNoSlotsOnAFullDayTimeOff` | Tam gün izinde hiç slot yoktur |
| `testPartialDayExceptionRemovesOnlyOverlappingSlots` | 14:00–15:30 istisnası: 13:30 var, 14:00 ve 15:00 yok, 15:30 var |
| `testDaysWithoutScheduleProduceNoSlots` | Takvimi olmayan günde slot yoktur |
| `testBookedAndPastSlotsAreMarked` | Dolu slot `Booked`, başlamış slot `Past` işaretlenir |
| `testSlotsAreSortedChronologicallyEvenIfScheduleRowsAreNot` | Satırlar karışık sırada girilse de slotlar kronolojiktir |

**`tests/Unit/Service/AppointmentServiceTest.php`** — randevu alma, iptal, değiştirme, sonuç işaretleme

| Test | Neyi kanıtlar |
| --- | --- |
| `testPatientCanBookAnAvailableSlot` | Boş slota randevu alınır, durum `Booked` |
| `testSameSlotCannotBeBookedTwice` | Aynı doktor + aynı slota ikinci randevu `SlotAlreadyTakenException` |
| `testCannotBookDuringLunchBreakOrOutsideSchedule` | Öğle arası veya mesai dışı saat elle gönderilse de reddedilir (sunucu doğrulaması) |
| `testCannotBookOnDoctorsDayOff` | İzin gününe randevu alınamaz |
| `testCannotBookPastSlot` | Geçmiş saate randevu alınamaz |
| `testPatientCannotHaveTwoAppointmentsAtTheSameTime` | Hastanın aynı saatte iki doktordan randevusu olamaz (K7) |
| `testPatientCannotCancelSomeoneElsesAppointment` | Başkasının randevusunu iptal → `AccessDeniedException` |
| `testCancelFreesTheSlotAndSecretaryCanCancel` | İptalde slot boşalır; sekreter iptal edebilir |
| `testPatientMustCancelAtLeastOneHourBeforeStart` | **Sınır:** 60 dk kala iptal kabul, 59 dk kala red |
| `testPatientCannotRescheduleWithinOneHourButSecretaryCanCancel` | 1 saat kuralı hasta değişikliğinde de geçerli; sekreter için değil |
| `testRescheduleBooksNewSlotAndCancelsOldOne` | Değiştirme yeni slotu alır, eskisini iptal eder |
| `testFailedRescheduleKeepsOriginalAppointment` | Yeni slot doluysa eski randevu bozulmaz (atomiklik) |
| `testDoctorCanMarkOutcomeOnlyAfterAppointmentStarts` | Doktor yalnızca başlamış randevuyu `Completed`/`NoShow` yapar |
| `testDoctorCannotMarkAnotherDoctorsAppointment` | Doktor başka doktorun randevusunu işaretleyemez |

**`tests/Unit/Service/WaitlistServiceTest.php`** — bekleme listesi ve Observer

| Test | Neyi kanıtlar |
| --- | --- |
| `testCancellationOffersTheSlotToFirstPatientInQueue` | İptal olayı → FIFO'daki ilk hastaya 30 dk teklif |
| `testOfferRespectsDesiredDate` | Tercih ettiği tarih farklı olan hasta atlanır |
| `testDeclinedOfferMovesToNextPatient` | Ret → aynı slot sıradaki hastaya |
| `testExpiredOfferMovesToNextPatientAfter30Minutes` | **Sınır:** 29. dk teklif geçerli, 31. dk düşer ve sıradakine geçer |
| `testAcceptingOfferCreatesAppointment` | Kabul → randevu oluşur, kayıt `Accepted` |
| `testAcceptWhenSlotWasTakenMeanwhileKeepsPatientInQueue` | Slot bu arada alındıysa hasta sırasını kaybetmeden kuyruğa döner |
| `testPatientCannotJoinSameDoctorsQueueTwice` | Aynı doktor için ikinci açık kayıt reddedilir (K10) |
| `testNoOfferWhenQueueIsEmpty` | Kuyruk boşsa teklif oluşmaz |

**`tests/Integration/ConcurrentBookingTest.php`** — eşzamanlılık (iki ayrı PHP süreci, `tests/Support/book-slot.php`)

| Test | Neyi kanıtlar |
| --- | --- |
| `testOnlyOneOfTwoSimultaneousRequestsIsAccepted` | Aynı anda gelen iki istekten biri `BOOKED`, diğeri `TAKEN`; dosyada tek randevu |
| `testWithoutLockingBothRequestsAreWronglyAccepted` | Kontrol deneyi: kilit kaldırılınca ikisine de "alındı" denir → kilidin gerekli olduğunu gösterir |

### 6.2 Diğer servis testleri

| Dosya | Test | Neyi kanıtlar |
| --- | --- | --- |
| `tests/Unit/Service/ScheduleServiceTest.php` | `testOverlappingRowOnTheSameDayIsRejected` | Aynı gün çakışan çalışma satırı reddedilir (K3) |
| | `testInvalidTimesAreReportedPerField` | Geçersiz saatler alan bazında hata verir |
| | `testAddingTimeOffReportsConflictingAppointments` | İzin eklenince çakışan randevular raporlanır |
| `tests/Unit/Service/AuthServiceTest.php` | `testRegisterHashesPasswordAndLogsPatientIn` | Parola hash'li saklanır, kayıt sonrası giriş yapılır |
| | `testInvalidRegistrationReportsEachField` | Her geçersiz alan ayrı hata mesajı alır |
| | `testEmailMustBeUnique` | Aynı e-posta ile ikinci kayıt reddedilir |
| | `testLoginRejectsWrongPasswordWithGenericMessage` | Yanlış parolada genel mesaj (hangi alanın yanlış olduğu sızmaz) |
| `tests/Unit/Service/ReportServiceTest.php` | `testDailyReportCountsStatusesAndRates` | Günlük raporda durum sayıları, iptal/doluluk/gelmeme oranları |
| `tests/Unit/Service/ProfilePhotoServiceTest.php` | `testValidImageIsStoredWithRandomName` | Gerçek görsel rastgele adla kaydedilir |
| | `testPhpFileDisguisedAsPngIsRejected` | `.png` uzantılı PHP dosyası reddedilir (içerik kontrolü) |
| | `testOversizedFileIsRejected` | 2 MB üstü reddedilir |
| | `testOtherPatientsCannotSeeAPatientsPhoto` | Başka hasta fotoğrafı göremez |

### 6.3 HTTP / yetki testleri (uygulamayı uçtan uca çalıştırır)

| Dosya | Test | Neyi kanıtlar |
| --- | --- | --- |
| `tests/Unit/Http/AccessControlTest.php` | `testGuestIsRedirectedToLoginAndApiReturns401` | Ziyaretçi → `/login`; API → 401 JSON |
| | `testEachRoleIsLimitedToItsOwnPanels` | Her rol yalnızca kendi panellerine girer (403) |
| | `testPatientSeesOnlyOwnAppointments` | Hasta yalnızca kendi randevularını görür |
| | `testPatientCannotCancelAnotherPatientsAppointmentViaHttp` | URL'deki id değiştirilerek başkasının randevusu iptal edilemez |
| | `testPostWithoutCsrfTokenIsRejected` | CSRF jetonsuz POST → 403 |
| | `testSlotApiReturnsJsonGridForPatient` | `/api/slots` JSON haftalık ızgara döner |
| `tests/Unit/Http/SuperAdminTest.php` | `testAdminCanLogInWithUsernameEvenWhenDataStoreIsEmpty` | Yerleşik `admin` veri boşken de giriş yapar |
| | `testWrongAdminPasswordIsRejected` | Yanlış yönetici parolası reddedilir |
| | `testAdminPassesEveryRoleRestriction` | Yönetici tüm panellere girer |
| | `testOtherRolesCannotUseAdminEndpoints` | Diğer roller yönetici uçlarına giremez |
| | `testAdminBypassesOwnershipRules` | Yönetici sahiplik kısıtına tabi değil |
| | `testAdminCreatesDepartmentViaFormPost` | Form ile poliklinik oluşturma (PRG) |
| | `testAdminCreatesDepartmentViaJsonAndDuplicateIsRejected` | JSON ile oluşturma 201, aynı ad 422 |
| | `testAdminCreatesDoctorThatCanBeBookedAfterScheduleIsAdded` | Yeni doktora takvim eklenince randevu alınabilir |
| | `testAdminUsernameIsReservedInTheDecoratedRepository` | `admin` adı başka hesapça alınamaz (Decorator) |
| | `testStaffAccountValidation` | Personel hesabı alan doğrulaması |

### 6.4 Repository testleri

| Dosya | Test | Neyi kanıtlar |
| --- | --- | --- |
| `tests/Unit/Repository/JsonStoreTest.php` | `testInsertAssignsIncrementingIdsAndUpdateMerges` | Artan id, güncellemede alan birleştirme |
| | `testFailedTransactionRollsBackAllWrittenTables` | Hata olursa yazılan tüm JSON dosyaları geri alınır |
| `tests/Unit/Repository/RepositoryContractTest.php` (her test JSON **ve** bellek içi uygulamada çalışır) | `testAppointmentRoundTripAndActiveOverlapQuery` | Kaydet/oku ve aktif çakışma sorgusu |
| | `testSearchJoinsNamesAndAppliesFilters` | Arama, isimleri birleştirir (JOIN) ve filtreleri uygular |
| | `testWaitlistQueueIsFifoWithIdAsTieBreaker` | Kuyruk FIFO; eşit zamanda id belirleyici |
| | `testDepartmentLookupIsCaseInsensitiveAndDoctorCarriesDepartmentName` | Büyük/küçük harf duyarsız arama; doktor poliklinik adını taşır |

### 6.5 Altyapı (Core) testleri

| Dosya | Testler | Neyi kanıtlar |
| --- | --- | --- |
| `tests/Unit/Core/RouterTest.php` | `testDispatchesToControllerWithRouteParameter`, `testTrailingSlashIsIgnored`, `testUnknownPathThrows404WithoutEchoingThePath`, `testWrongMethodThrows405WithAllowHeader`, `testGlobalMiddlewareRunsBeforeRouteMiddleware`, `testMiddlewareCanStopTheRequest`, `testHandlerMustReturnResponse` | `{id}` parametresi, 404 (yol yansıtılmaz → XSS yok), 405 + `Allow`, middleware sırası ve durdurma |
| `tests/Unit/Core/ContainerTest.php` | `testAutowiresConstructorDependenciesThroughInterfaceBinding`, `testSingletonReturnsSameInstanceButBindReturnsNewOne`, `testUnboundInterfaceThrowsHelpfulException`, `testCircularDependencyIsDetected` | DI: arayüz bağlama, singleton, anlaşılır hata, döngü tespiti |
| `tests/Unit/Core/CsrfMiddlewareTest.php` | `testGetRequestPassesWithoutToken`, `testPostWithValidFormTokenPasses`, `testPostWithValidHeaderTokenPasses`, `testPostWithMissingOrWrongTokenIsRejected` | CSRF formda (`_csrf`) ve AJAX başlığında (`X-CSRF-Token`) |
| `tests/Unit/Core/ViewTest.php` | `testOutputIsEscapedAndWrappedInLayout`, `testTemplateScopeIsIsolatedFromViewObject`, `testPathTraversalIsRejected` | Çıktı kaçışı, yalıtılmış şablon kapsamı, `../` engeli |
| `tests/Unit/Core/RequestTest.php` | `testPathIsNormalized`, `testNonScalarInputFallsBackToDefault`, `testWantsJsonForApiPathsAndAjaxHeaders` | Yol normalleştirme, dizi enjeksiyonuna karşı varsayılan, JSON isteği algılama |
| `tests/Unit/Core/EventDispatcherTest.php` | `testEveryListenerOfTheEventIsNotified`, `testListenersOfOtherEventsAreNotNotified` | Observer: doğru dinleyiciler çağrılır |
| `tests/Unit/Core/ClockTest.php` | `testFrozenClockOnlyMovesWhenAdvanced` | Test saati yalnızca elle ilerler |

### 6.6 Test yardımcı dosyaları

| Dosya | Görev |
| --- | --- |
| `tests/Support/AppTestCase.php` | Uygulamayı bellek içi repository'ler ve `FrozenClock` ile kurar, giriş yapmış istek gönderir |
| `tests/Support/InMemory/*` | Repository arayüzlerinin bellek içi uygulamaları + `InMemoryTransactionManager` |
| `tests/Support/SlowSaveAppointmentRepository.php` | Kaydı 300 ms geciktirir (eşzamanlılık penceresini açar) |
| `tests/Support/book-slot.php` | Eşzamanlılık testinde ayrı PHP süreci olarak çalışan randevu alma betiği |
| `tests/Support/Stub/*`, `tests/Fixtures/*` | Router, Container, Event ve View testleri için sahte sınıflar ve şablonlar |

### 6.7 Testlerin gerçekten ölçtüğünün kanıtı

Üretim kodunda 10 kural bilerek bozuldu (M1–M10: satır sınırı, izin, geçmiş slot, dolu slot, hasta çakışması,
sahiplik, FIFO, 30 dk, Observer, rol kontrolü); **her biri en az bir testi kırdı.** Tablo: `docs/izlenebilirlik.md` §5.
Gereksinim (FR/NFR) ve iş kuralı (K1–K12) ↔ test eşlemesinin tamamı da aynı dosyadadır.

---

## 7. Tasarım desenleri ve gerekçeleri

| Desen | Dosya(lar) | Gerekçe |
| --- | --- | --- |
| **Repository** | `src/Repository/Contract/*`, `Json/*` | JSON → MySQL geçişinde servisler ve testler değişmez |
| **Observer** | `EventDispatcher`, `EventListenerInterface`, `AppointmentCancelled`, `WaitlistListener`, `config/events.php` | "İptal bekleme listesini tetikler"; `AppointmentService` bekleme listesini tanımaz |
| **Strategy** | `TransactionManagerInterface` (FileLock / Pdo), `ClockInterface` (System / Frozen) | Aynı servis kodu farklı ortamda farklı stratejiyle çalışır |
| **Decorator** | `SuperAdminUserRepository` | Yerleşik yönetici, mevcut repository'lere dokunmadan eklenir |
| **Chain of Responsibility** | `MiddlewareInterface` zinciri (CSRF → Rol) | Yetki kontrolü controller'dan ayrılır |
| **Front Controller / DI** | `public/index.php`, `src/Core/Container.php` | Tek giriş; bağımlılıklar yapıcıdan, testte sahte nesne verilebilir |

Ayrıntı: `docs/tasarim.md` §5.

---

## 8. Yapay zekâ düzeltmeleri

Yapay zekâ (Claude Code, oturum #004) tasarımın netleştirilmesi ve Aşama 2 kodunun yazılmasında kullanıldı; diğer
oturumlarda diyagramlar için kullanıldı. Her öneri testle, HTTP denemesiyle veya kod gözden geçirmesiyle kontrol
edildi. Tam kayıt: `docs/ai-gunlugu.md` ve `docs/ai-gunlugu/*.md`.

### 8.1 Yapay zekânın yanıldığı ve bizim yakalayıp düzelttiğimiz durumlar

| # | Oturum | Yapay zekânın hatası | Nasıl fark edildi | Düzeltme | Düzeltilen dosya |
| --- | --- | --- | --- | --- | --- |
| H1 | #002 | Sınıf diyagramında Controller, somut Repository ve Observer katmanı yoktu | Katman kurallarına göre gözden geçirme | Diyagram 22 bileşenli tam mimariye genişletildi | `docs/tasarim.md` §2 |
| H2 | #003 | Sıralama diyagramında slot listeleme adımında Repository atlanmıştı | Mimari sorgulama | Servis → Repository adımı eklendi | `docs/tasarim.md` §3 |
| H3 | #004 | CSRF hatası için standart dışı **419** kodu seçildi; Apache bunu 500'e çevirdi | HTTP ile uçtan uca deneme | **403** yapıldı | `src/Core/Exception/HttpException.php` (`csrfMismatch()`) |
| H4 | #004 | `AuthService` oturumdaki kullanıcı değişse de önbellekteki eski kullanıcıyı döndürüyordu | Yeni yazılan rol erişim testi kırıldı | Önbellek oturum kimliğine bağlandı | `src/Service/AuthService.php` (`user()`) |
| H5 | #004 | "Slot satır sınırını aşamaz" testi kuralı sınamıyordu: 45 dk slot 3 saate tam bölündüğü için taşma hiç oluşmuyordu | Kural bilerek bozuldu (M1), hiçbir test kırılmadı | Test 40 dk slotla yeniden yazıldı; artık kural bozulunca kırılıyor | `tests/Unit/Service/SlotGeneratorTest.php` |
| H6 | #004 | Kilitsiz karşılaştırma testi "2 randevu oluşur" varsayıyordu; ara sıra 1 çıktı (kayıp güncelleme) | Testin tekrar tekrar çalıştırılması | İddia doğru davranışa göre düzeltildi ("ikisine de alındı denir") | `tests/Integration/ConcurrentBookingTest.php` |
| H7 | #004 | JSON isteklerinde PHP'nin `Content-Type` başlığını `HTTP_` öneki olmadan verdiği gözden kaçtı | HTTP denemesinde JSON yerine yönlendirme geldi | Başlık okuma düzeltildi | `src/Core/Http/Request.php` |
| H8 | #004 | Doktor ekranına enum'u dizi anahtarı yapan işlevsiz bir satır yazıldı (çalışsa fatal error verirdi) | Kod gözden geçirme | Satır silindi | `views/doctor/index.php` |

### 8.2 Yapay zekânın önerdiği ama ekibin reddettiği / değiştirdiği kararlar

| Konu | Yapay zekânın önerisi | Ekibin kararı | Gerekçe |
| --- | --- | --- | --- |
| Teklif süresince slot | Slot teklif alan hastaya ayrılsın | Slot herkese açık kalır, kabulde yeniden kontrol edilir | Akış basit kalır, slot boşta beklemez |
| Öğle arası modeli | Tek satır + `lunch_start/lunch_end` | Gün iki takvim satırına bölünür, ara = boşluk | ER tasarımıyla uyumlu, 3NF, bir günde birden fazla ara mümkün |
| Dosya yükleme | Doktor profil fotoğrafı | Her kullanıcının kendi profil fotoğrafı | Tüm rollerde kullanılabilir |

### 8.3 Yapay zekânın tespit ettiği, bizim onaylayıp düzelttiğimiz sorunlar (Aşama 1'den kalan)

| Sorun | Düzeltme | Dosya |
| --- | --- | --- |
| Klasör yapısı feature-first; hocanın yapısı katman bazlı | Katman bazlı yapıya geçildi | `src/` |
| Tasarımda "AJAX'ta JSON kullanılmaz" yazıyordu; isterler JSON uç noktası istiyor | `/api/slots` JSON döndürür | `docs/tasarim.md`, `src/Controller/ApiController.php` |
| Rol, enum ve tablo adları bölümler arasında farklıydı | Tek isimlendirme | `docs/tasarim.md`, `src/Model/Enum/*` |
| `Router` 404 sayfasında kaçışsız `$path` (XSS) | Yol yanıta yansıtılmıyor | `src/Core/Router/Router.php` (`RouterTest::testUnknownPathThrows404WithoutEchoingThePath`) |
| `Container` tekil örnek tutmuyordu | `singleton()` eklendi | `src/Core/Container.php` (`ContainerTest`) |
| `php -S` nedeniyle `.htaccess` çalışmıyordu | `php:8.2-apache` + `mod_rewrite` | `Dockerfile`, `public/.htaccess` |

---

## 9. Planlanıp yapılmayanlar ve Aşama 3

| Konu | Durum | Gerekçe / plan |
| --- | --- | --- |
| FR3 / FR7 Doktor ve sekreterin kendini kaydetmesi | Gerekçeli sapma | Herkesin kendini doktor yapabilmesi güvenlik açığı; hesabı süper yönetici açar |
| FR13 Ayrı "Detay" sayfası | Kısmen | Ayrıntılar randevu kartında gösteriliyor |
| FR18 Doktorun kendi takvimini düzenlemesi | Yok | Takvimi sekreter/yönetici yönetiyor; Aşama 3 adayı |
| NFR4 Yük testi, NFR5 Parola süresi | Yok | Aşama 2 kapsamı dışında bırakıldı |
| **Aşama 3: MySQL** | Planlandı | `database/` altında migration + seed SQL; `src/Repository/Mysql/Pdo*Repository` ve `PdoTransactionManager` (`BEGIN/COMMIT`, `SELECT … FOR UPDATE`, UNIQUE `active_slot_key`). Değişecek tek yer `config/services.php` bağlamaları; servisler, controller'lar ve testler aynı kalır. `RepositoryContractTest` PDO uygulamasına da uygulanacak. |
