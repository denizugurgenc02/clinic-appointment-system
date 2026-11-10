# Gereksinim – Test İzlenebilirlik Tablosu

> **Durum (10.11.2026, Aşama 2 — JSON veri katmanı):** 93 test / 250 doğrulama, **hepsi başarılı**.
> PHPStan seviye 8: **hata yok**. PHP_CodeSniffer PSR-12: **0 hata** (yalnızca >120 karakter satır uyarıları).
>
> ```bash
> docker compose exec app vendor/bin/phpunit            # tüm testler
> docker compose exec app vendor/bin/phpunit --testdox  # okunabilir liste
> docker compose exec app composer check                # PSR-12 + testler + PHPStan
> ```

Gereksinim numaraları `docs/Klinik Yönetim Sistemi SRS.pdf` (Aşama 1) belgesinden alınmıştır. İş kuralı numaraları
(K1–K12) `docs/tasarim.md` §1.6'dadır.

## Test altyapısı (tekrarlanabilirlik)

| Konu | Nasıl sağlandı |
| --- | --- |
| Zaman | Servisler "şimdi"yi `ClockInterface`'ten alır; testlerde `FrozenClock` (16.11.2026 Pzt 08:00) elle ilerletilir. Gerçek saate bağlı test yoktur. |
| Veri | Servis testleri **bellek içi sahte repository**'lerle çalışır (`tests/Support/InMemory`), gerçek JSON dosyalarına dokunmaz. |
| Sahte repository'nin doğruluğu | `RepositoryContractTest` aynı senaryoları hem JSON hem bellek içi uygulamalarda çalıştırır (8 test). |
| Eşzamanlılık | Yalnızca `ConcurrentBookingTest` gerçek JSON + `flock` kullanır (iki ayrı PHP süreci aynı veriyi görmeli); geçici klasörde. |
| Rastgelelik | Seed sabit tohumla (`mt_srand(2026)`) üretilir; testlerde rastgelelik yoktur (yalnızca dosya adı üretimi, sonucu kurala göre doğrulanır). |

## 1. Fonksiyonel gereksinimler (SRS §3.3.1)

| FR | Gereksinim (özet) | Durum | Testler |
| --- | --- | --- | --- |
| FR1 | Poliklinik / doktor / tarihe göre randevu arama ve filtreleme | ✅ | `AccessControlTest::testSlotApiReturnsJsonGridForPatient` (haftalık ızgara), `RepositoryContractTest::testSearchJoinsNamesAndAppliesFilters`, `SlotGeneratorTest::testDaysWithoutScheduleProduceNoSlots` |
| FR2 | Uygun saate randevu alma; dolu ise oluşturmama | ✅ | `AppointmentServiceTest::testPatientCanBookAnAvailableSlot`, `testSameSlotCannotBeBookedTwice`, `testCannotBookDuringLunchBreakOrOutsideSchedule`, `testCannotBookPastSlot` |
| FR3 | Doktorun kayıt olması | ⚠️ Gerekçeli sapma | Herkesin kendini "doktor" olarak kaydedebilmesi güvenlik açığı olduğu için doktor hesabını **süper yönetici** açar. `SuperAdminTest::testAdminCreatesDoctorThatCanBeBookedAfterScheduleIsAdded`, `testStaffAccountValidation`. *SRS güncellenecek.* |
| FR4 | Hasta randevusunu başlangıçtan **en az 1 saat önce** iptal edebilir | ✅ | `AppointmentServiceTest::testPatientMustCancelAtLeastOneHourBeforeStart` (**sınır: 60 dk kabul, 59 dk red**), `testPatientCannotRescheduleWithinOneHourButSecretaryCanCancel`, `testCancelFreesTheSlotAndSecretaryCanCancel` |
| FR5 | İptal öncesi onay | ✅ (arayüz) | İptal formlarında `confirm()` onayı; onay verilmezse istek gönderilmez. Tarayıcı davranışı olduğu için PHPUnit testi yok, elle doğrulandı. |
| FR6 | Doktor çalışma günleri, saatleri, öğle arası, randevu süresi | ⚠️ Kısmen | Takvim **sekreter / yönetici** tarafından tanımlanır (FR14); doktorun kendisi düzenleyemez, randevu süresi hesap açılırken belirlenir. Slot kuralları: `SlotGeneratorTest` (7 test), `ScheduleServiceTest::testOverlappingRowOnTheSameDayIsRejected`, `testInvalidTimesAreReportedPerField` |
| FR7 | Sekreterin kayıt olması | ⚠️ Gerekçeli sapma | FR3 ile aynı gerekçe: sekreter hesabını süper yönetici açar. `SuperAdminTest::testStaffAccountValidation`, `testAdminUsernameIsReservedInTheDecoratedRepository`. *SRS güncellenecek.* |
| FR8 | Sekreter yetkilendirme ve erişim kontrolü | ✅ | `AccessControlTest::testEachRoleIsLimitedToItsOwnPanels`, `testGuestIsRedirectedToLoginAndApiReturns401`, `SuperAdminTest::testOtherRolesCannotUseAdminEndpoints` |
| FR9 | İptalde bekleme listesindeki sıradaki hastaya teklif; kabulde slot hâlâ boşsa randevu | ✅ | `WaitlistServiceTest::testCancellationOffersTheSlotToFirstPatientInQueue`, `testAcceptingOfferCreatesAppointment`, `testAcceptWhenSlotWasTakenMeanwhileKeepsPatientInQueue`, `testDeclinedOfferMovesToNextPatient`, `testExpiredOfferMovesToNextPatientAfter30Minutes` (**sınır: 29. dk geçerli, 31. dk düşer**), `testOfferRespectsDesiredDate`, `testNoOfferWhenQueueIsEmpty` |
| FR10 | Eşzamanlı isteklerden yalnızca biri başarılı | ✅ | `ConcurrentBookingTest::testOnlyOneOfTwoSimultaneousRequestsIsAccepted` (iki ayrı PHP süreci), `testWithoutLockingBothRequestsAreWronglyAccepted` (kontrol), `AppointmentServiceTest::testSameSlotCannotBeBookedTwice` |
| FR11 | Bekleme listesine kayıt | ✅ | `WaitlistServiceTest::testPatientCannotJoinSameDoctorsQueueTwice`, `RepositoryContractTest::testWaitlistQueueIsFifoWithIdAsTieBreaker` |
| FR12 | Randevu tarih/saatini değiştirme (tutarlı güncelleme) | ✅ | `AppointmentServiceTest::testRescheduleBooksNewSlotAndCancelsOldOne`, `testFailedRescheduleKeepsOriginalAppointment`, `JsonStoreTest::testFailedTransactionRollsBackAllWrittenTables` |
| FR13 | "Detay" düğmesiyle randevu ayrıntıları | ⚠️ Kısmen | Ayrıntılar (tarih, saat, doktor, poliklinik, not, durum) "Randevularım" kartlarında doğrudan gösterilir; ayrı detay sayfası/düğmesi **yok**. `AccessControlTest::testPatientSeesOnlyOwnAppointments` |
| FR14 | Sekreterin doktor takvim/müsaitlik yönetimi | ✅ | `ScheduleServiceTest` (3 test), `SlotGeneratorTest::testNoSlotsOnAFullDayTimeOff`, `testPartialDayExceptionRemovesOnlyOverlappingSlots` |
| FR15 | Sekreterin bekleme kuyruğunu görüntülemesi | ✅ | `AccessControlTest::testEachRoleIsLimitedToItsOwnPanels` (`/secretary/waitlist`), `RepositoryContractTest::testWaitlistQueueIsFifoWithIdAsTieBreaker` (sıra numaraları) |
| FR16 | Günlük randevu raporu | ✅ | `ReportServiceTest::testDailyReportCountsStatusesAndRates` |
| FR17 | Doktorun kendi randevu listesi | ✅ | `AccessControlTest::testEachRoleIsLimitedToItsOwnPanels` (`/doctor`), `AppointmentServiceTest::testDoctorCanMarkOutcomeOnlyAfterAppointmentStarts`, `testDoctorCannotMarkAnotherDoctorsAppointment` |
| FR18 | Doktorun çalışma takvimini güncellemesi | ❌ Yok | Takvim yalnızca sekreter / yönetici tarafından güncellenir (FR14). Aşama 3 adayı. |
| FR19 | Hasta kaydı; aynı e-posta ve eksik alan reddi | ✅ | `AuthServiceTest::testRegisterHashesPasswordAndLogsPatientIn`, `testInvalidRegistrationReportsEachField`, `testEmailMustBeUnique` |

## 2. Fonksiyonel olmayan gereksinimler (SRS §3.3.2)

| NFR | Gereksinim | Durum | Testler / kanıt |
| --- | --- | --- | --- |
| NFR1 | Rol tabanlı yetkilendirme, yetkisiz erişim %100 engellenir | ✅ | `AccessControlTest` (6 test: 11 rol/sayfa kombinasyonu, URL'deki id ile başkasının randevusu, CSRF), `SuperAdminTest::testOtherRolesCannotUseAdminEndpoints`, `AppointmentServiceTest::testPatientCannotCancelSomeoneElsesAppointment` |
| NFR2 | Parolalar açık metin saklanmaz (bcrypt/Argon2id) | ✅ | `AuthServiceTest::testRegisterHashesPasswordAndLogsPatientIn` (`password_hash` / `password_verify`); süper yönetici şifresi de koda yalnızca hash olarak yazılır |
| NFR3 | Doğru HTTP durum kodları | ✅ | `RouterTest::testUnknownPathThrows404WithoutEchoingThePath` (404), `testWrongMethodThrows405WithAllowHeader` (405), `AccessControlTest` (302/401/403), `SuperAdminTest::testAdminCreatesDepartmentViaJsonAndDuplicateIsRejected` (201/422) |
| NFR4 | 100 eşzamanlı girişte ≤ 3 sn | ⏳ Ölçülmedi | Yük testi Aşama 2 kapsamında yapılmadı. |
| NFR5 | Parola 6 ayda bir süresi dolar | ❌ Yok | Parola değiştirme ve süre dolumu Aşama 2'de yapılmadı. (SRS'teki Meter/Must satırları NFR4'ten kopyalanmış görünüyor; SRS'te düzeltilecek.) |

## 3. Çekirdek problem — sunumda kanıtlanacaklar

| Kanıt | Test | Sınır / ayrıntı |
| --- | --- | --- |
| İzin gününde slot üretilmemesi | `SlotGeneratorTest::testNoSlotsOnAFullDayTimeOff`, `testPartialDayExceptionRemovesOnlyOverlappingSlots`, `AppointmentServiceTest::testCannotBookOnDoctorsDayOff` | 14:00–15:30 istisnası: 13:30 var, 14:00 ve 15:00 yok, 15:30 var |
| Öğle arasının atlanması | `SlotGeneratorTest::testLunchBreakIsSkipped`, `testSlotNeverCrossesTheEndOfAWorkingBlock` | 40 dk slot: 11:00 son slot, 11:40 (12:20'ye taşar) üretilmez |
| İptalde sıradaki hastaya teklif | `WaitlistServiceTest` (8 test) | 30 dk teklif: 29. dk geçerli, 31. dk düşer ve sıradakine geçer |
| İki eşzamanlı istekten yalnızca birinin kabulü | `ConcurrentBookingTest` (2 test) | Kayıt 300 ms geciktirilir; kilitli: `BOOKED`+`TAKEN`, kilitsiz: ikisine de "alındı" |

## 4. İş kuralları (docs/tasarim.md §1.6)

| Kural | Testler |
| --- | --- |
| K1 slot süresi doktora özgü | `AppointmentServiceTest::testPatientCanBookAnAvailableSlot`, `SuperAdminTest::testAdminCreatesDoctorThatCanBeBookedAfterScheduleIsAdded` (20 dk) |
| K2 öğle arası / satır sınırı | `SlotGeneratorTest::testLunchBreakIsSkipped`, `testSlotNeverCrossesTheEndOfAWorkingBlock`, `testSlotsAreSortedChronologicallyEvenIfScheduleRowsAreNot` |
| K3 takvim satırları çakışamaz | `ScheduleServiceTest::testOverlappingRowOnTheSameDayIsRejected` |
| K4 izin / istisna | `SlotGeneratorTest::testNoSlotsOnAFullDayTimeOff`, `testPartialDayExceptionRemovesOnlyOverlappingSlots`, `ScheduleServiceTest::testAddingTimeOffReportsConflictingAppointments` |
| K5 geçmiş slot alınamaz | `SlotGeneratorTest::testBookedAndPastSlotsAreMarked`, `AppointmentServiceTest::testCannotBookPastSlot` |
| K6 slotta tek aktif randevu | `AppointmentServiceTest::testSameSlotCannotBeBookedTwice`, `ConcurrentBookingTest` |
| K7 hastanın çakışan randevusu olamaz | `AppointmentServiceTest::testPatientCannotHaveTwoAppointmentsAtTheSameTime` |
| K8 sahiplik | `AccessControlTest::testPatientSeesOnlyOwnAppointments`, `testPatientCannotCancelAnotherPatientsAppointmentViaHttp` |
| K9 iptal koşulları (+FR4) | `AppointmentServiceTest::testPatientMustCancelAtLeastOneHourBeforeStart` |
| K10 doktor başına tek açık bekleme kaydı | `WaitlistServiceTest::testPatientCannotJoinSameDoctorsQueueTwice` |
| K11 FIFO, tercih edilen gün, slot başına tek teklif | `WaitlistServiceTest::testCancellationOffersTheSlotToFirstPatientInQueue`, `testOfferRespectsDesiredDate` |
| K12 teklif süresi 30 dk | `WaitlistServiceTest::testExpiredOfferMovesToNextPatientAfter30Minutes` |

## 5. Testlerin gerçekten ölçtüğünün kontrolü (kuralı bozma deneyi)

Her satırda üretim kodundaki tek bir kural geçici olarak bozuldu, tüm test paketi çalıştırıldı, ardından dosya geri
yüklendi (10.11.2026). Temel durumda tüm testler geçiyor; **10 bozmanın 10'u da en az bir testi kırdı**.

| # | Bozulan kural | Değişiklik | Kırılan testler |
| --- | --- | --- | --- |
| M1 | K2 slot satır sınırını aşamaz | `SlotGenerator`: `if ($end > $blockEnd)` → `if (false)` | `SlotGeneratorTest::testSlotNeverCrossesTheEndOfAWorkingBlock` |
| M2 | K4 izinli saatte slot yok | `SlotGenerator`: izin kontrolü kaldırıldı | `SlotGeneratorTest::testNoSlotsOnAFullDayTimeOff`, `testPartialDayExceptionRemovesOnlyOverlappingSlots`, `AppointmentServiceTest::testCannotBookOnDoctorsDayOff` |
| M3 | K5 geçmiş slot | `SlotGenerator`: `$start <= $now` kontrolü kaldırıldı | `SlotGeneratorTest::testBookedAndPastSlotsAreMarked`, `AppointmentServiceTest::testCannotBookPastSlot` |
| M4 | K6 dolu slot | `SlotService`: dolu slot için istisna yerine slot döndürüldü | `AppointmentServiceTest::testSameSlotCannotBeBookedTwice`, `testFailedRescheduleKeepsOriginalAppointment`, `ConcurrentBookingTest::testOnlyOneOfTwoSimultaneousRequestsIsAccepted`, `WaitlistServiceTest::testAcceptWhenSlotWasTakenMeanwhileKeepsPatientInQueue` |
| M5 | K7 hasta çakışması | `AppointmentService`: hasta çakışma kontrolü kapatıldı | `AppointmentServiceTest::testPatientCannotHaveTwoAppointmentsAtTheSameTime` |
| M6 | K8 sahiplik | `AppointmentService`: `UserRole::Patient => true` | `AccessControlTest::testPatientCannotCancelAnotherPatientsAppointmentViaHttp`, `AppointmentServiceTest::testPatientCannotCancelSomeoneElsesAppointment` |
| M7 | K11 FIFO | `WaitlistService`: kuyruk ters çevrildi | `WaitlistServiceTest::testCancellationOffersTheSlotToFirstPatientInQueue`, `testDeclinedOfferMovesToNextPatient`, `testExpiredOfferMovesToNextPatientAfter30Minutes` |
| M8 | K12 30 dk teklif | `WaitlistService`: `+60 minutes` | `WaitlistServiceTest::testCancellationOffersTheSlotToFirstPatientInQueue`, `testExpiredOfferMovesToNextPatientAfter30Minutes` |
| M9 | Observer | `WaitlistListener::handle()` boşaltıldı | `WaitlistServiceTest` içinde 6 test |
| M10 | Rol yetkilendirmesi | `RoleMiddleware`: rol kontrolü kaldırıldı | `AccessControlTest::testEachRoleIsLimitedToItsOwnPanels`, `SuperAdminTest::testOtherRolesCannotUseAdminEndpoints` |

**Bu deneyin yakaladığı test hatası:** M1'in ilk denemesinde hiçbir test kırılmadı. Satır sınırı testi 45 dakikalık
slot kullanıyordu; 3 saat 45'e tam bölündüğü için taşan slot hiç oluşmuyor, yani test kuralı sınamıyordu. Test
40 dakikalık slota çevrildi (11:40 → 12:20 taşması) ve M1 artık yakalanıyor.

Sunumda tekrarlamak için (ör. M1): `src/Service/SlotGenerator.php` içinde `if ($end > $blockEnd) {` satırını
`if (false) {` yapın → `vendor/bin/phpunit --filter SlotGenerator` kırmızı → değişikliği geri alın.

## 6. Ortak asgari şartlar ve güvenlik

| Şart | Testler / doğrulama |
| --- | --- |
| CSRF | `CsrfMiddlewareTest` (4), `AccessControlTest::testPostWithoutCsrfTokenIsRejected` |
| Çıktı kaçışı (XSS) | `ViewTest::testOutputIsEscapedAndWrappedInLayout`, `RouterTest::testUnknownPathThrows404WithoutEchoingThePath` |
| Güvenli dosya yükleme | `ProfilePhotoServiceTest` (4: içerik kontrolü, 2 MB sınırı, rastgele ad, yetki) |
| Transaction / rollback | `JsonStoreTest::testFailedTransactionRollsBackAllWrittenTables` |
| Yerleşik süper yönetici | `SuperAdminTest` (10) |
| Altyapı | `ContainerTest` (4), `RouterTest` (7), `RequestTest` (3), `ViewTest` (3), `ClockTest` (1), `EventDispatcherTest` (2) |

## 7. Elle (HTTP) yapılan uçtan uca kontroller — 10.11.2026

Docker'daki Apache üzerinde `curl` ile, seed verisiyle:

- Üç rol ve süper yönetici ile giriş → rolün ana sayfası; yetkisiz paneller 403, ziyaretçi → `/login`, ziyaretçi API → 401.
- Hatalı kayıt formu: alan hataları gösterildi, girilen ad korundu; geçerli kayıt → randevu sayfası (PRG).
- Aynı slotu ikinci hasta alamadı ("Seçtiğiniz saat az önce doldu"); başka hastanın randevusu iptal edilemedi.
- Sekreter iptali → bekleme listesindeki ilk hastaya teklif → hasta kabulü → randevu oluştu.
- Bozuk JSON veri dosyası → kullanıcıya yalnızca "Beklenmeyen bir hata oluştu" (500); yığın izi `storage/logs/app.log`'da.
- Profil fotoğrafı: PHP içerikli `.png` reddedildi; gerçek PNG rastgele adla kaydedildi; başka hasta 403.
- JSON ile poliklinik oluşturma: 201, aynı ad 422, CSRF jetonsuz 403.

**Yapılmayanlar:** 375 px genişlikte görsel kontrol bir tarayıcıda yapılmadı (Bootstrap grid ile mobil öncelikli
yazıldı; tablolar `table-responsive` içinde). NFR4 yük testi yapılmadı.
