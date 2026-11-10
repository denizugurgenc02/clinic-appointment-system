# Test Raporu ve Gereksinim-Test İzlenebilirlik Tablosu

> **Durum (10.11.2026, Aşama 2 — JSON veri katmanı):** 83 test, 212 doğrulama, **hepsi başarılı**.
> PHPStan seviye 8: **hata yok**.
>
> ```bash
> docker compose exec app composer test                 # tüm testler
> docker compose exec app vendor/bin/phpunit --testdox  # okunabilir liste
> docker compose exec app composer analyse              # statik analiz
> ```

Test sınıfları `tests/` altındadır. Servis testleri (`tests/Support/AppTestCase.php`), gerçek uygulama kurulumunu
(`config/services.php` + `config/events.php`) her test için **boş bir geçici JSON veri klasörü** ve
**sabitlenmiş saat** (`FrozenClock`, 16.11.2026 Pazartesi 08:00) ile kullanır.

## 1. Sunumda kanıtlanacaklar

| Kanıt | Test | Nasıl kanıtlıyor? |
| --- | --- | --- |
| İzin gününde slot üretilmemesi | `SlotGeneratorTest::testNoSlotsOnAFullDayTimeOff` | Tam gün izinli pazartesi için üretilen slot listesi boş |
| | `SlotGeneratorTest::testPartialDayExceptionRemovesOnlyOverlappingSlots` | 14:00-15:30 istisnası yalnızca kesişen slotları siler |
| | `AppointmentServiceTest::testCannotBookOnDoctorsDayOff` | İzinli güne randevu isteği reddedilir |
| Öğle arasının atlanması | `SlotGeneratorTest::testLunchBreakIsSkipped` | 09-12 ve 13-17 satırlarında 12:00 ve 12:30 slotu yok, 11:30 ve 13:00 var |
| | `SlotGeneratorTest::testSlotNeverCrossesTheEndOfAWorkingBlock` | 45 dk'lık slotlarda 11:15'ten sonra 12:00'ye taşan slot üretilmez |
| | `AppointmentServiceTest::testCannotBookDuringLunchBreakOrOutsideSchedule` | 12:00, 10:15 ve cumartesi istekleri reddedilir |
| İptalde sıradaki hastaya teklifin gitmesi | `WaitlistServiceTest::testCancellationOffersTheSlotToFirstPatientInQueue` | İptal → `AppointmentCancelled` olayı → `WaitlistListener` → FIFO'da ilk hasta `Offered`, teklif 30 dk |
| | `WaitlistServiceTest::testDeclinedOfferMovesToNextPatient` | Ret → sıradaki hasta |
| | `WaitlistServiceTest::testExpiredOfferMovesToNextPatientAfter30Minutes` | 29. dk geçerli, 31. dk `Expired` → sıradaki hasta |
| İki eşzamanlı istekten yalnızca birinin kabulü | `ConcurrentBookingTest::testOnlyOneOfTwoSimultaneousRequestsIsAccepted` | **İki ayrı PHP süreci** aynı anda aynı slotu ister; sonuç `BOOKED` + `TAKEN`, slotta tek aktif randevu |
| | `ConcurrentBookingTest::testWithoutLockingBothRequestsAreWronglyAccepted` | Kontrol testi: kilit kaldırılınca ikisine de "alındı" denir. Yukarıdaki testin kilidi gerçekten sınadığını gösterir |

## 2. İş kuralları (docs/tasarim.md §1.6)

| Kural | Açıklama | Testler |
| --- | --- | --- |
| K1 | Slot süresi doktora özgü | `AppointmentServiceTest::testPatientCanBookAnAvailableSlot` (bitiş = başlangıç + 30 dk), `SlotGeneratorTest::testSlotNeverCrossesTheEndOfAWorkingBlock` (45 dk) |
| K2 | Öğle arası = satırlar arası boşluk; slot satır sınırını aşmaz | `SlotGeneratorTest::testLunchBreakIsSkipped`, `testSlotNeverCrossesTheEndOfAWorkingBlock`, `testSlotsAreSortedChronologicallyEvenIfScheduleRowsAreNot` |
| K3 | Aynı gündeki takvim satırları çakışamaz | `ScheduleServiceTest::testOverlappingRowOnTheSameDayIsRejected`, `testInvalidTimesAreReportedPerField` |
| K4 | İzin/istisna aralığıyla kesişen slot üretilmez | `SlotGeneratorTest::testNoSlotsOnAFullDayTimeOff`, `testPartialDayExceptionRemovesOnlyOverlappingSlots`, `AppointmentServiceTest::testCannotBookOnDoctorsDayOff` |
| K5 | Geçmiş slot alınamaz | `SlotGeneratorTest::testBookedAndPastSlotsAreMarked`, `AppointmentServiceTest::testCannotBookPastSlot` |
| K6 | Bir slotta tek aktif randevu | `AppointmentServiceTest::testSameSlotCannotBeBookedTwice`, `ConcurrentBookingTest` (2 test), `AppointmentServiceTest::testCancelFreesTheSlotAndSecretaryCanCancel` |
| K7 | Hastanın aynı saatte iki aktif randevusu olamaz | `AppointmentServiceTest::testPatientCannotHaveTwoAppointmentsAtTheSameTime` |
| K8 | Hasta yalnızca kendi randevularını görür/değiştirir; doktor yalnızca kendi listesini | `AccessControlTest::testPatientSeesOnlyOwnAppointments`, `testPatientCannotCancelAnotherPatientsAppointmentViaHttp`, `AppointmentServiceTest::testPatientCannotCancelSomeoneElsesAppointment`, `testDoctorCannotMarkAnotherDoctorsAppointment` |
| K9 | İptal yalnızca aktif ve başlamamış randevuda | `AppointmentServiceTest::testDoctorCanMarkOutcomeOnlyAfterAppointmentStarts` (durum geçişleri), `Appointment::cancel()` |
| K10 | Doktor başına tek açık bekleme kaydı | `WaitlistServiceTest::testPatientCannotJoinSameDoctorsQueueTwice` |
| K11 | FIFO, tercih edilen gün, slot başına tek teklif | `WaitlistServiceTest::testCancellationOffersTheSlotToFirstPatientInQueue`, `testOfferRespectsDesiredDate`, `testNoOfferWhenQueueIsEmpty` |
| K12 | Teklif süresi 30 dk | `WaitlistServiceTest::testExpiredOfferMovesToNextPatientAfter30Minutes` |
| — | Teklif süresince slot açık; kabulde dolu ise hasta sırasını korur | `WaitlistServiceTest::testAcceptWhenSlotWasTakenMeanwhileKeepsPatientInQueue`, `testAcceptingOfferCreatesAppointment` |
| — | Tarih değiştirme atomik | `AppointmentServiceTest::testRescheduleBooksNewSlotAndCancelsOldOne`, `testFailedRescheduleKeepsOriginalAppointment`, `JsonStoreTest::testFailedTransactionRollsBackAllWrittenTables` |

## 3. Ortak asgari şartlar

| Şart | Testler / doğrulama |
| --- | --- |
| Rol yetkilendirmesi (her işlemde) | `AccessControlTest::testGuestIsRedirectedToLoginAndApiReturns401`, `testEachRoleIsLimitedToItsOwnPanels`; `RouterTest::testMiddlewareCanStopTheRequest` |
| `password_hash` / `password_verify` | `AuthServiceTest::testRegisterHashesPasswordAndLogsPatientIn`, `testLoginRejectsWrongPasswordWithGenericMessage` |
| Kayıt doğrulaması, benzersiz e-posta | `AuthServiceTest::testInvalidRegistrationReportsEachField`, `testEmailMustBeUnique` |
| CSRF | `CsrfMiddlewareTest` (4 test), `AccessControlTest::testPostWithoutCsrfTokenIsRejected` |
| Çıktı kaçışı (XSS) | `ViewTest::testOutputIsEscapedAndWrappedInLayout`, `RouterTest::testUnknownPathThrows404WithoutEchoingThePath` |
| Güvenli dosya yükleme | `ProfilePhotoServiceTest` (4 test: içerik kontrolü, boyut, rastgele ad, yetki) |
| AJAX / JSON uç nokta | `AccessControlTest::testSlotApiReturnsJsonGridForPatient` |
| Rapor (toplamlar ve oranlar) | `ReportServiceTest::testDailyReportCountsStatusesAndRates` |
| İzinle çakışan randevu uyarısı | `ScheduleServiceTest::testAddingTimeOffReportsConflictingAppointments` |
| Transaction / rollback | `JsonStoreTest::testFailedTransactionRollsBackAllWrittenTables` |
| Observer | `EventDispatcherTest` (2 test) + `WaitlistServiceTest` (iptal olayıyla uçtan uca) |

## 4. Süper yönetici ve yönetim işlemleri

| Gereksinim | Testler (`SuperAdminTest`) |
| --- | --- |
| Veri kaynağı boşken de `admin` / `asdf1234` ile giriş; veri kaynağına yazılmaz | `testAdminCanLogInWithUsernameEvenWhenDataStoreIsEmpty`, `testWrongAdminPasswordIsRejected` |
| Hiçbir rol kısıtına tabi değil | `testAdminPassesEveryRoleRestriction` (9 panel) |
| Sahiplik kurallarını atlar (başkasının randevusunu iptal, doktor adına sonuç) | `testAdminBypassesOwnershipRules` |
| Yönetim uçları diğer rollere kapalı | `testOtherRolesCannotUseAdminEndpoints` |
| Poliklinik oluşturma: form POST ve JSON POST (201 / 422) | `testAdminCreatesDepartmentViaFormPost`, `testAdminCreatesDepartmentViaJsonAndDuplicateIsRejected` |
| Doktor hesabı açma → takvim → randevu alınabilir | `testAdminCreatesDoctorThatCanBeBookedAfterScheduleIsAdded` |
| `admin` kullanıcı adı ayrılmış; personel doğrulaması | `testAdminUsernameIsReservedInTheDecoratedRepository`, `testStaffAccountValidation` |

## 5. Altyapı testleri

`ContainerTest` (4), `RouterTest` (7), `RequestTest` (3), `ViewTest` (3), `ClockTest` (1), `EventDispatcherTest` (2),
`CsrfMiddlewareTest` (4), `JsonStoreTest` (2).

## 6. Elle (HTTP) yapılan uçtan uca kontroller — 10.11.2026

Docker'daki Apache üzerinde `curl` ile, seed verisiyle:

- Üç rolle giriş → rolün ana sayfasına yönlendirme; tüm paneller 200, yetkisiz paneller 403, ziyaretçi → `/login`, ziyaretçi API → 401.
- Hatalı kayıt formu: alan hataları gösterildi, girilen ad korundu; geçerli kayıt → randevu sayfası.
- Randevu alma, aynı slotu ikinci hastanın alamaması ("Seçtiğiniz saat az önce doldu"), başkasının randevusunu iptal edememe.
- Sekreter iptali → bekleme listesindeki ilk hastaya teklif → hasta kabulü → randevu oluştu.
- Takvim satırı ekleme, çakışan satırın reddi, izin ekleme (çakışan randevu uyarısı), doktorun "Tamamlandı" işaretlemesi.
- Profil fotoğrafı: PHP içerikli `.png` reddedildi; gerçek PNG rastgele adla `storage/uploads`'a kaydedildi; başka hasta 403, sekreter 200; `storage/` doğrudan erişilemiyor.
- CSRF jetonsuz POST → 403.
- `admin` / `asdf1234` girişi → `/admin`; tüm paneller 200. JSON ile poliklinik oluşturma 201, aynı ad 422, jetonsuz 403; formla doktor oluşturma → takvim sayfasına yönlendirme.

**Yapılmayanlar:** 375 px genişlikte görsel kontrol bir tarayıcıda henüz yapılmadı (düzen Bootstrap grid ile
mobil öncelikli yazıldı; tablolar `table-responsive` içinde).
