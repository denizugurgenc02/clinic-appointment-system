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

## 2. Sınıf Diyagramı (Class Diagram)

```mermaid
classDiagram
    %% ==========================================
    %% 1. ENUM'LAR (PHP 8.1 Standartı)
    %% ==========================================
    class UserRole {
        <<enumeration>>
        PATIENT
        DOCTOR
        SECRETARY
    }

    class AppointmentStatus {
        <<enumeration>>
        SCHEDULED
        COMPLETED
        CANCELLED
    }

    class WaitlistStatus {
        <<enumeration>>
        PENDING
        OFFERED
        ACCEPTED
        EXPIRED
    }

    %% ==========================================
    %% 2. MODEL / VARLIK KATMANI VE KALITIM
    %% ==========================================
    class User {
        +int id
        +string name
        +string email
        +string passwordHash
        +UserRole role
        +login(string email, string password) bool
        +changePassword(string newPassword) bool
    }

    class Patient {
        +string phone
    }

    class Doctor {
        +int departmentId
        +string title
    }

    class Secretary {
        +string employeeNumber
    }

    %% Kalıtım (Inheritance)
    User <|-- Patient
    User <|-- Doctor
    User <|-- Secretary

    class Department {
        +int id
        +string name
    }

    class Schedule {
        +int id
        +int doctorId
        +int dayOfWeek
        +string startTime
        +string endTime
        +string lunchBreakStart
        +string lunchBreakEnd
        +int slotDurationMinutes
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
        +AppointmentStatus status
        +cancel() void
    }

    class Waitlist {
        +int id
        +int patientId
        +int doctorId
        +string preferredDate
        +string offerExpiresAt
        +WaitlistStatus status
        +isExpired() bool
    }

    %% ==========================================
    %% 3. OBSERVER KALIBI (İptal & Bekleme Listesi)
    %% ==========================================
    class AppointmentCancelObserverInterface {
        <<interface>>
        +onAppointmentCancelled(Appointment appointment) void
    }

    class WaitlistObserver {
        -WaitlistService waitlistService
        +onAppointmentCancelled(Appointment appointment) void
    }

    WaitlistObserver ..|> AppointmentCancelObserverInterface

    %% ==========================================
    %% 4. REPOSITORY KATMANI (Arayüz & Gerçekleme)
    %% ==========================================
    class AppointmentRepositoryInterface {
        <<interface>>
        +save(Appointment a) bool
        +findById(int id) Appointment
        +findOverlapping(int doctorId, string date, string time) Appointment
        +updateStatus(int id, AppointmentStatus status) bool
        +getDailyList(int doctorId, string date) array
    }

    class MysqlAppointmentRepository {
        -PDO pdo
        +save(Appointment a) bool
        +findById(int id) Appointment
        +findOverlapping(int doctorId, string date, string time) Appointment
        +updateStatus(int id, AppointmentStatus status) bool
        +getDailyList(int doctorId, string date) array
    }

    class WaitlistRepositoryInterface {
        <<interface>>
        +getNextInQueue(int doctorId, string date) Waitlist
        +updateOfferStatus(int id, WaitlistStatus status, string expiresAt) bool
    }

    class MysqlWaitlistRepository {
        -PDO pdo
        +getNextInQueue(int doctorId, string date) Waitlist
        +updateOfferStatus(int id, WaitlistStatus status, string expiresAt) bool
    }

    class ScheduleRepositoryInterface {
        <<interface>>
        +findByDoctor(int doctorId) array
        +findTimeOffs(int doctorId, string date) array
    }

    class MysqlScheduleRepository {
        -PDO pdo
        +findByDoctor(int doctorId) array
        +findTimeOffs(int doctorId, string date) array
    }

    MysqlAppointmentRepository ..|> AppointmentRepositoryInterface
    MysqlWaitlistRepository ..|> WaitlistRepositoryInterface
    MysqlScheduleRepository ..|> ScheduleRepositoryInterface

    %% ==========================================
    %% 5. SERVİS KATMANI (İş Mantığı & Transaction)
    %% ==========================================
    class SlotService {
        -ScheduleRepositoryInterface scheduleRepo
        -AppointmentRepositoryInterface appointmentRepo
        +generateSlots(int doctorId, string date) array
        -isLunchBreak(Schedule s, string time) bool
    }

    class WaitlistService {
        -WaitlistRepositoryInterface waitlistRepo
        +offerNextPatient(int doctorId, string date) void
        +handleOfferResponse(int waitlistId, bool isAccepted) bool
    }

    class AppointmentService {
        -AppointmentRepositoryInterface appointmentRepo
        -array observers
        +bookAppointment(int patientId, int doctorId, string date, string time) Appointment
        +cancelAppointment(int appointmentId) bool
        +attachObserver(AppointmentCancelObserverInterface obs) void
        -notifyObservers(Appointment a) void
    }

    AppointmentService --> AppointmentRepositoryInterface : kullanır
    AppointmentService --> AppointmentCancelObserverInterface : bildirir
    WaitlistService --> WaitlistRepositoryInterface : kullanır
    WaitlistObserver --> WaitlistService : tetikler
    SlotService --> ScheduleRepositoryInterface : kullanır
    SlotService --> AppointmentRepositoryInterface : kullanır

    %% ==========================================
    %% 6. CONTROLLER KATMANI (İstek Karşılama)
    %% ==========================================
    class AppointmentController {
        -AppointmentService appointmentService
        -SlotService slotService
        +getAvailableSlots() void
        +store() void
        +cancel() void
    }

    class SecretaryController {
        -AppointmentService appointmentService
        -AppointmentRepositoryInterface appointmentRepo
        +getDailyReport() void
        +manageWaitlist() void
    }

    AppointmentController ..> AppointmentService : bağımlıdır
    AppointmentController ..> SlotService : bağımlıdır
    SecretaryController ..> AppointmentService : bağımlıdır

    %% ==========================================
    %% 7. KARDİNALİTE VE DOMAİN İLİŞKİLERİ (1-N vb.)
    %% ==========================================
    Department "1" --> "0..*" Doctor : barındırır
    Doctor "1" *-- "1..*" Schedule : mesai_takvimi
    Doctor "1" *-- "0..*" TimeOff : izinleri
    Doctor "1" --> "0..*" Slot : üretir
    
    Patient "1" --> "0..*" Appointment : randevusu_vardır
    Doctor "1" --> "0..*" Appointment : hastası_vardır
    
    Doctor "1" --> "0..*" Waitlist : bekleyenler
    Patient "1" --> "0..*" Waitlist : sıradadır
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