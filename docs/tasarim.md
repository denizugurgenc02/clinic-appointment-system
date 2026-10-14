# Klinik Randevu Sistemi Tasarım Belgesi

## Sunum 1 anlatım dağılımı

| Bölüm | Anlatan |
| --- | --- |
| Proje, çekirdek problem, roller | (isim) |
| 1. Mimari, 2. Sınıf diyagramı, 5. Tasarım desenleri | (isim) |
| 3. Sıralama diyagramı, 4. ER diyagramı | (isim) |
| 6. Arayüz, 7. Riskler, 8. Plan | (isim) |

## 1. Mimari

_Bu bölüm yazılacak: katman şeması ve klasör yapısı._

## 2. Sınıf diyagramı

```mermaid
classDiagram
    %% --- Kalıtım Hiyerarşisi (Kullanıcılar) ---
    class User {
        +int id
        +string name
        +string email
        +string passwordHash
        +string role
        +login(string email, string pass) bool
        +changePassword(string newPass) bool
    }

    class Patient {
        +string phone
        +viewMyAppointments() array
        +bookAppointment(Slot slot) bool
    }

    class Doctor {
        +int polyclinicId
        +string title
        +setSchedule(Schedule s) void
        +addTimeOff(TimeOff t) void
    }

    class Secretary {
        +manageAppointments() void
        +generateDailyReport(string date) array
    }

    %% Kalıtım (Inheritance)
    User <|-- Patient
    User <|-- Doctor
    User <|-- Secretary

    %% --- Alan Varlıkları (Domain Entities) ---
    class Polyclinic {
        +int id
        +string name
        +string description
    }

    class Schedule {
        +int id
        +int doctorId
        +int dayOfWeek
        +string startTime
        +string endTime
        +int slotDuration
    }

    class TimeOff {
        +int id
        +int doctorId
        +string startDate
        +string endDate
        +string reason
    }

    class Slot {
        +string date
        +string startTime
        +string endTime
        +bool isAvailable
    }

    class Appointment {
        +int id
        +int patientId
        +int doctorId
        +string date
        +string startTime
        +string status
        +cancel() void
    }

    class WaitingListEntry {
        +int id
        +int patientId
        +int doctorId
        +string preferredDate
        +string offerExpiresAt
        +string status
        +isExpired() bool
    }

    %% --- Repository Arayüzü ---
    class AppointmentRepositoryInterface {
        <<interface>>
        +save(Appointment a) bool
        +findById(int id) Appointment
        +findOverlapping(int doctorId, string date, string time) Appointment
        +updateStatus(int id, string status) bool
    }

    %% --- Servis Katmanı (İş Mantığı) ---
    class SlotService {
        +generateSlots(int doctorId, string date) array
        -isTimeOff(int doctorId, string date) bool
    }

    class WaitlistService {
        +offerNextPatient(int doctorId, string date) void
        +handleOfferResponse(int waitlistId, bool accepted) bool
    }

    class AppointmentService {
        -AppointmentRepositoryInterface repo
        +bookAppointment(int patientId, int doctorId, string date, string time) Appointment
        +cancelAppointment(int appointmentId) bool
    }

    %% --- İlişkiler ve Kardinaliteler ---
    Polyclinic "1" --> "*" Doctor : calistirir
    Doctor "1" *-- "1..*" Schedule : icerir
    Doctor "1" *-- "*" TimeOff : izin_alir
    Doctor "1" --> "*" Slot : uretir
    
    Patient "1" --> "*" Appointment : randevu_alir
    Doctor "1" --> "*" Appointment : muayene_eder
    
    Doctor "1" --> "*" WaitingListEntry : bekleyenler
    Patient "1" --> "*" WaitingListEntry : siraya_girer

    AppointmentService --> AppointmentRepositoryInterface : kullanir
    AppointmentService ..> Appointment : olusturur
    AppointmentService ..> WaitlistService : tetikler
    SlotService ..> Schedule : okur
```

## 3. Sıralama diyagramı

_Bu bölüm yazılacak: randevu alma akışı ve hata dalı._

## 4. ER diyagramı

_Bu bölüm yazılacak: tablolar, anahtarlar, ilişkiler ve 3NF gerekçesi._

## 5. Tasarım desenleri

_Bu bölüm yazılacak: en az 2 desen, her biri için gerekçe._

## 6. Arayüz

_Bu bölüm yazılacak: sayfa haritası ve en az 5 ekran taslağı._

## 7. Riskler

| Risk | Olasılık | Etki | Önlem |
| --- | --- | --- | --- |
| Aynı doktora aynı saat diliminde birden fazla randevu alınması | Orta | Yüksek | Eş zamanlı istekler için veritabanı düzeyinde tutarlılık kontrolleri yapılmalı. |
| Doktorun izin veya istisna günlerinin takvime yanlış yansıtılması | Orta | Yüksek | Takvim ve slot üretimi için farklı izin, öğle arası ve istisna günü senaryolarını kapsayan birim ve entegrasyon testleri hazırlanmalı. |
| Bekleme listesi teklifinin yanlış hastaya gitmesi veya sıra düzeninin bozulması | Orta | Yüksek | Bekleme listesinin sıralama kurallarının açıkça tanımlanması ve teklif gönderme akışının farklı senaryolarla test edilmesi. |
| Teklif süresi dolduğu halde slotun başka hastaya açılmaması veya birden fazla hastaya sunulması | Orta | Yüksek | Teklif süresi dolumunun otomatik kontrol edilmesi, teklif durumlarının takip edilmesi. |
| Hastanın başka bir hastaya ait randevu bilgilerine ulaşması | Düşük | Yüksek | Her randevu görüntüleme ve değiştirme işleminde kullanıcı kimliği ile yetki kontrolü yapılması ve erişim testleri. |
| Randevu iptal veya değiştirme sırasında verilerin tutarsız hale gelmesi | Orta | Yüksek | İptal ve değişiklik işlemlerinin atomik yürütülmesi ve işlem sonrası veri tutarlılığının test edilmesi. |
| Günlük randevu raporunda eksik veya hatalı kayıtların gösterilmesi | Orta | Orta | Rapor sorgularının sonuçlarının veritabanındaki kayıtlarla karşılaştırılması. |
| Proje kapsamının geliştirme sırasında genişlemesi | Yüksek | Orta | SRS dokümanına sadık kalınması ve yeni özellik taleplerinin proje takvimini etkileyip etkilemeyeceğinin değerlendirilmesi. |
| Doktor panelinde randevu bilgilerinin hatalı veya güncel olmayan şekilde görüntülenmesi | Orta | Yüksek | Günlük randevu listesi, tarih filtreleri ve randevu durumları için entegrasyon testleri yapılmalıdır. |

## 8. Plan

- Proje panosu (backlog): _pano bağlantısı eklenecek_
- Kilometre taşları: Sunum 1 - 15.10.2026, Sunum 2 - 22.10.2026, Sunum 3 - 29.10.2026