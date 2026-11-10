# Aşama 2 Süreç Raporu — Klinik Randevu Sistemi

> **Taslak.** `[EKİP DOLDURACAK]` ile işaretli bölümler, git geçmişinden çıkarılamayan bilgiler gerektirir:
> haftalık iş dağılımı, eleştirmen grup geri bildirimi, issue/PR bağlantıları. Teslimden önce doldurulmalıdır.

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

`[EKİP DOLDURACAK]` Haftalara göre tablo. Git geçmişinden çıkarılabilenler:

| Tarih | Kişi | İş (commit / PR) |
| --- | --- | --- |
| 07.10.2026 | Deniz Uğur Genç | Depo, `.gitignore`, proje isterleri, `CLAUDE.md` |
| 08–15.10.2026 | Hatice Ayvaz | README, risk analizi (#8), ER diyagramı ve normalizasyon, SRS (#4), sunum dağılımı, plan (#9); PR #1, #28, #32–#34 |
| 11–14.10.2026 | Mehmet Efe Kaya | Sınıf diyagramı (#3), sıralama diyagramı (#6); PR #29, #30 |
| 12–14.10.2026 | Eren Çit | Klasör iskeleti, ilk `tasarim.md`, UI taslakları; PR #31 |
| 10.11.2026 | Eren Çit (+ Claude Code) | Tasarımın netleştirilmesi, altyapı, model, slot algoritması, JSON veri katmanı, servisler, ekranlar, testler, süper yönetici, Phase-2 uyumu; dal `asama-2/prototip` |

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

`[EKİP DOLDURACAK]` Eleştirmen grubun önerileri, kabul edilip uygulananlar (issue/PR bağlantılarıyla),
katılmadığımız öneriler ve gerekçeleri.

## 8. Grup üyelerinin katkıları

`[EKİP DOLDURACAK]` §2'deki git geçmişine ve PR'lara dayanarak. Aşama 2 kodunun büyük bölümü
`asama-2/prototip` dalında tek kişi tarafından (AI desteğiyle) yazıldı. Her üyenin kendi bölümünü savunabilmesi
ve sonraki işlerin üyelere dağıtılması gerekir.

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
