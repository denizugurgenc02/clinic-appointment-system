# Aşama 2 Süreç Raporu — Klinik Randevu Sistemi

> Bu rapordaki iş dağılımı ve katkı bilgileri GitHub deposundaki commit, pull request ve issue kayıtlarından
> (10.11.2026 itibarıyla) derlenmiştir.

**Grup:** Hatice AYVAZ (2307231001), Mehmet Efe KAYA (2307231054), Eren ÇİT (2307231029), Deniz Uğur GENÇ (2307231030)
**Depo:** https://github.com/denizugurgenc02/clinic-appointment-system · **Pano:** https://github.com/users/denizugurgenc02/projects/2

## 1. Proje ve çekirdek problem

Klinik Randevu Sistemi'nde hasta poliklinik ve doktor seçer, doktorun haftalık takviminden üretilen boş saatlerden
randevu alır. Sekreter randevuları, doktor takvimlerini ve izinlerini yönetir; doktor günlük listesini görür.

Projeyi CRUD'dan ayıran üç çekirdek problem vardır:

1. **Slot üretimi:** Takvim satırlarından (`DatePeriod` / `DateInterval`) randevu aralıkları üretilir. Öğle arası,
   izin ve gün içi istisnalar, geçmiş saatler ve satır bitişini aşan slotlar dışarıda bırakılır.
2. **Çakışma önleme:** Aynı doktorun aynı slotuna, eşzamanlı isteklerde bile, tek randevu verilir.
3. **Bekleme listesi:** İptal edilen slot, Observer deseniyle FIFO kuyruktaki uygun ilk hastaya 30 dakikalık
   teklif olarak gider. Teklif reddedilir veya süresi dolarsa sıradakine geçilir.

Aşama 2'de veritabanı yoktur. Veriler `storage/data/*.json` dosyalarındadır ve Repository arayüzlerinin JSON
uygulamalarıyla okunur.

## 2. Aşama 2 boyunca yapılan işler

**Planlama.** Hatice Ayvaz, SRS'teki her fonksiyonel gereksinim için kabul kriterli bir GitHub issue'su açtı
(#10–#27) ve işleri üyelere dağıttı:

| Üye | Atanan gereksinimler (issue) |
| --- | --- |
| Hatice Ayvaz | FR1–FR5 (#10–#14) |
| Deniz Uğur Genç | FR6–FR10 (#15–#19) |
| Eren Çit | FR11–FR14 (#20–#23) |
| Mehmet Efe Kaya | FR15–FR18 (#24–#27) |

**Haftalara göre yapılan işler** (commit ve PR tarihlerine göre):

| Hafta | Üye | Yapılan iş | Kayıt |
| --- | --- | --- | --- |
| 07–11.10.2026 | Deniz Uğur Genç | Depo, `.gitignore`, proje isterleri, `CLAUDE.md` | ilk commit'ler |
| 07–11.10.2026 | Hatice Ayvaz | README; Aşama 1 görev issue'ları (#2–#9) | PR #1 |
| 07–11.10.2026 | Mehmet Efe Kaya | README'ye ekip bilgisi | commit |
| 12–15.10.2026 | Hatice Ayvaz | Risk analizi (#8), ER diyagramı ve normalizasyon (#4), SRS, sunum dağılımı, plan (#9); FR issue'ları (#10–#27) | PR #28, #32, #33, #34 |
| 12–15.10.2026 | Mehmet Efe Kaya | Sınıf diyagramı (#3), sıralama diyagramı (#6) | PR #29, #30 |
| 12–15.10.2026 | Eren Çit | Klasör iskeleti, ilk `tasarim.md`, UI taslakları (#7) | PR #31 |
| 15.10.2026 | — | **Sunum 1** | |
| 16.10–09.11.2026 | — | Depoda kayıtlı commit yok | |
| 10.11.2026 | Eren Çit (Claude Code desteğiyle) | Tasarımın netleştirilmesi; altyapı, model, slot algoritması, JSON veri katmanı, servisler, ekranlar, 93 test; süper yönetici; Phase-2 uyumu; `docker compose up` ile tek adımda çalışma | 18 commit, PR #35 |

Aşama 2 kodunun tamamı tek bir dalda (`asama-2/prototip`) ve tek bir PR'da (#35) toplandı. Diğer üyelere atanmış
FR issue'larının (#10–#27) işlevleri bu PR'da karşılandı; issue'lar PR birleştiğinde kapatılacaktır.
Karşılanmayanlar: #12 (FR3), #16 (FR7), #22 (FR13), #27 (FR18) ve #15'in (FR6) doktorun kendi takvimi kısmı
(gerekçeler: `docs/izlenebilirlik.md`).

## 3. Tasarım ve uygulama kararları

| # | Karar | Değerlendirilen alternatifler | Gerekçe |
| --- | --- | --- | --- |
| 1 | **JSON'da tek kilit dosyasıyla transaction** (`FileLockTransactionManager`: `flock(LOCK_EX)` + geçici dosyaya yazıp `rename` + hata olursa geri alma) | (a) Her dosyaya ayrı kilit, (b) Kilitsiz yazma | Randevu alma birden fazla tabloyu okuyup yazar (randevu, hasta çakışması, bekleme listesi). Tek kilit kilitlenme sırası sorununu (deadlock) ortadan kaldırır. Servis yalnızca `TransactionManagerInterface::run()` çağırdığı için Aşama 3'te aynı kod `BEGIN/COMMIT` ile çalışır. Kilitsiz seçeneğin çift randevuya veya kayıp güncellemeye yol açtığı testle gösterildi. |
| 2 | **Teklif süresince slot herkese açık kalır**; kabulde kilit altında yeniden kontrol edilir, doluysa hasta sırasını korur | Slotu teklif alan hastaya ayırmak | Akış basit kalır ve iptal edilen slot boşta beklemez. Bedeli kabul anındaki ek kontroldür (`testAcceptWhenSlotWasTakenMeanwhileKeepsPatientInQueue`). SRS FR9 "slot hâlâ müsaitse" ifadesiyle uyumludur. |
| 3 | **Öğle arası = aynı günün iki takvim satırı arasındaki boşluk** | Tek satır + `lunch_start` / `lunch_end` sütunları | ER tasarımıyla uyumlu ve 3NF'e uygun; bir günde birden fazla ara tanımlanabilir. Asıl sınanan kural, slotun satır bitişini aşmamasıdır. |
| 4 | **Süre dolumu istek anında işlenir** (`expireStale()`) + `bin/process-waitlist.php` | Cron | Docker'da cron gerekmez. İlgili sayfa ve AJAX uçları açılırken süresi dolan teklifler işlenir. |
| 5 | **Yerleşik süper yönetici bir Decorator ile eklenir** (`SuperAdminUserRepository`) | Veritabanına kayıt; koda gömülü `if` kontrolleri | Veri kaynağından bağımsızdır (JSON / MySQL); mevcut repository'ler değişmeden genişletilir (open-closed). Şifre koda yalnızca hash olarak yazılır. |
| 6 | **Testlerde bellek içi sahte repository + sözleşme testleri** | Testlerde gerçek JSON dosyaları | Hızlı ve izole testler. Sahte repository'nin JSON ile aynı davrandığı `RepositoryContractTest` ile güvenceye alınır. |

## 4. Yapay zekâ günlüğünden örnekler

Ayrıntılar: [`docs/ai-gunlugu.md`](ai-gunlugu.md).

1. **Tasarım tutarsızlıklarının tespiti (#004, Etkileşim 1):** AI, Aşama 1 belgesindeki bölümler arası çelişkileri
   listeledi. Örnekler: klasör yapısı, AJAX'ta "JSON kullanılmaz" ifadesi, farklı enum ve tablo adları. Ekip her
   maddede ayrı karar verdi; önerilerin dördü kabul edildi, üçünde farklı seçenek tercih edildi.
2. **AI'ın yanıldığı örnek, test kuralı sınamıyordu (#004, Etkileşim 7):** AI'ın yazdığı "slot satır sınırını
   aşamaz" testi 45 dakikalık slot kullanıyordu ve 3 saat 45'e tam bölündüğü için taşma hiç oluşmuyordu. Kural
   kodda bilerek bozulduğunda hiçbir test kırılmadı; böylece fark edildi. Test 40 dakikalık slotla düzeltildi.
3. **AI'ın yanıldığı örnek, oturum önbelleği (#004, Etkileşim 5):** `AuthService` oturumdaki kullanıcıyı önbelleğe
   alıyor, kimlik değişince yenilemiyordu. Rol erişim testi yanlış kullanıcının panele girdiğini gösterdi;
   önbellek oturum kimliğine bağlandı.

## 5. Testler

- **93 PHPUnit testi, 250 doğrulama**; tümü başarılı. **PHPStan seviye 8:** hata yok. **PSR-12 (phpcs):** 0 hata.
- **İzlenebilirlik tablosu:** [`docs/izlenebilirlik.md`](izlenebilirlik.md). SRS'teki FR1–FR19 ve NFR1–NFR5 →
  testler; ayrıca iş kuralları ve kural bozma deneyi.
- **Önemli sınır durumu, FR4:** Hasta randevusunu başlangıçtan en az 60 dakika önce iptal edebilir. Tam 60 dakika
  kala kabul edilir, 59 dakika kala reddedilir (`testPatientMustCancelAtLeastOneHourBeforeStart`). Diğerleri:
  teklif süresinin 29. dakikası (geçerli) ve 31. dakikası (düşer); 11:40'ta başlayıp öğle arasına taşan slot.
- **Kural bozma deneyi:** 10 kural tek tek bozuldu, 10'u da testlerce yakalandı (izlenebilirlik §5).

## 6. Karşılaşılan sorunlar ve çözümleri

1. **Apache standart dışı HTTP kodunu 500'e çeviriyordu:** CSRF hatası için 419 kullanılmıştı; tarayıcı 500 aldı.
   HTTP ile uçtan uca denemede fark edildi, 403'e çevrildi (NFR3).
2. **Eşzamanlılık testinin güvenilirliği:** Kilitsiz karşılaştırma testi bazen 2, bazen 1 kayıt gösterdi (kayıp
   güncelleme). Testin iddiası "ikisine de randevu alındı denir" olarak düzeltildi. Gerçek testte kontrol ile kayıt
   arasına 300 ms gecikme konarak yarış penceresi garanti altına alındı.
3. **Hata ayrıntılarının kullanıcıya sızma riski:** `APP_DEBUG` varsayılanı açıktı. Varsayılan kapatıldı;
   kullanıcı sade bir mesaj görür, yığın izi `storage/logs/app.log`'a yazılır. AJAX'ta JSON olmayan yanıtlar ve
   oturum süresi dolması için de sade mesaj gösteriliyor.

## 7. Eleştirmen grup geri bildirimleri

Bu rapor yazıldığında (10.11.2026) eleştirmen gruptan GitHub'da kayıtlı bir geri bildirim (issue, PR yorumu veya
inceleme) bulunmamaktadır; depodaki 35 issue/PR'ın hiçbirinde yorum yoktur. Geri bildirim geldiğinde aşağıdaki
tabloya işlenecek, her öneri için bir issue açılıp yanıt ve düzeltme ilgili PR'a bağlanacaktır.

| # | Öneri (issue / yorum bağlantısı) | Kararımız | Gerekçe / düzeltme (PR bağlantısı) |
| --- | --- | --- | --- |
| — | Henüz geri bildirim alınmadı | — | — |

**Grup içi inceleme:** Aşama 2 kodu PR #35 ile `master`'a, en az bir grup üyesinin incelemesi ve onayıyla
birleştirilecektir. Aşama 1 PR'ları (#1, #28–#34) yorumsuz birleştirilmişti. Hocanın beklentisine uygun olarak incelemede
bulunan sorunların PR yorumu olarak somut biçimde yazılması gerekir.

## 8. Grup üyelerinin katkıları

Commit sayıları birleştirme (merge) commit'leri hariçtir.

| Üye | Commit | Açtığı PR | Açtığı issue | Katkı |
| --- | --- | --- | --- | --- |
| Hatice Ayvaz | 6 (+5 birleştirme) | #1, #28, #32, #33, #34 | #2–#27 (26 issue) | Proje planı ve FR issue'ları; SRS; ER diyagramı ve 3NF; risk analizi; plan ve pano; sunum dağılımı |
| Mehmet Efe Kaya | 6 (+1 birleştirme) | #29, #30 | — | Sınıf diyagramı, sıralama diyagramı (AI günlüğü #002, #003) |
| Deniz Uğur Genç | 6 | — | — | Deponun kurulumu, `.gitignore`, ödev isterleri, `CLAUDE.md` (AI çalışma kuralları) |
| Eren Çit | 22 (+2 birleştirme) | #31, #35 | — | Aşama 1: iskelet ve UI taslakları. Aşama 2: prototipin tamamı (18 commit, PR #35), AI günlüğü #004 |

**Değerlendirme:** Aşama 2'de kod katkısı tek üyede yoğunlaşmıştır; §2'deki planlanan iş dağılımı uygulamaya
geçmemiştir. Sunumda her üyenin kodu savunabilmesi için, issue atamalarına (§2) yakın şu dağılım **önerilmektedir**
(ekip tarafından onaylanmalıdır):

| Üye | İnceleyip savunacağı bölüm (öneri) |
| --- | --- |
| Hatice Ayvaz | Randevu alma/iptal ve FR4 kuralı (`AppointmentService`), izlenebilirlik tablosu |
| Deniz Uğur Genç | Slot üretimi (`SlotGenerator`), eşzamanlılık ve `FileLockTransactionManager` |
| Eren Çit | Altyapı (Router, Container, middleware), JSON veri katmanı, süper yönetici |
| Mehmet Efe Kaya | Bekleme listesi ve Observer (`WaitlistService`, `WaitlistListener`), sekreter/doktor ekranları, rapor |

Aşama 3'teki MySQL repository'lerinin de bu dağılıma göre üyeler tarafından kendi dallarında yazılması önerilir.

## 9. Aşama 3: MySQL'e geçiş

**Değişecek sınıflar** (yalnızca veri katmanı ve `config/services.php`):

| Aşama 2 (JSON) | Aşama 3 (MySQL) |
| --- | --- |
| `JsonUserRepository`, `JsonPatientRepository`, `JsonDoctorRepository`, `JsonDepartmentRepository`, `JsonScheduleRepository`, `JsonTimeOffRepository`, `JsonAppointmentRepository`, `JsonWaitlistRepository` | Aynı arayüzleri uygulayan `Pdo*Repository` sınıfları (hazırlıklı ifadeler, JOIN) |
| `FileLockTransactionManager` + `JsonStore` | `PdoTransactionManager` (`BEGIN` / `COMMIT` / `ROLLBACK`) |
| `bin/seed.php` (JSON) | `database/migrations/*.sql` + seed |

**Değişmeyecekler:** Tüm servisler, controller'lar, görünümler, `SuperAdminUserRepository` (yalnızca içteki
repository değişir) ve servis testleri. Servis testleri zaten bellek içi repository'lerle çalışıyor; aynı kodun iki
farklı veri katmanıyla çalıştığı bugünden kanıtlı. `RepositoryContractTest`'e MySQL uygulaması eklenecek.

**Riskler:**
- **Çift randevu:** `SELECT ... FOR UPDATE` doğru satırları kilitlemezse çift randevu oluşabilir. Son savunma
  olarak `active_slot_key` üretilmiş sütunu üzerinde UNIQUE kısıt kullanılacak (`tasarim.md` §4.3); eşzamanlılık
  testi MySQL'e karşı tekrar çalıştırılacak.
- **Kilit beklemeleri:** Transaction yalıtım düzeyi ve kilit bekleme süreleri.
- **Tarih/saat:** `DATETIME` ve saat dilimi dönüşümleri.
- **Arama performansı:** JSON'da PHP ile yapılan birleştirmelerin SQL `JOIN`'e taşınması; arama ve filtrelerde indeksler.
