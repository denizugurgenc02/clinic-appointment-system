## 🎨 Tasarım Desenleri (Design Patterns) ve Tercih Gerekçeleri

---

### 1. Repository Pattern (Veri Erişim Deseni)

#### 📍 Projedeki Kullanım Alanı
Veritabanı erişim mantığı ile iş mantığını tamamen ayırmak için uygulanmıştır. SQL sorguları (`SELECT`, `INSERT`, `JOIN` vb.) servis sınıflarına yazılmaz; her modülün kendi Repository sınıfında (`AppointmentRepository`, `ScheduleRepository`, `WaitlistRepository`, `AuditLogRepository` vb.) toplanır.

#### Akış Şeması
+-----------------------+Servis Çağrısı   +-----------------------+PDO / SQL   
+-----------------+ |  SlotGeneratorService 
| ──────────────────►| ScheduleRepository.php| 
─────────────►|  MySQL Database |
+-----------------------+                    +-----------------------+               +-----------------+


#### 💡 Neden ve Tercih Gerekçesi
* **Kod Karmaşasını ve Tekrarını Önleme:** Karmaşık `JOIN` sorgularının ve veritabanı işlemlerinin Controller veya Service katmanını kirletmesi engellenir.
* **Test Edilebilirlik (Testability):** Dokümanda belirtildiği gibi `SlotGeneratorService` sınıfı saf bir iş mantığı sınıfıdır. Unit testler yazılırken, gerçek bir veritabanına bağlanmak yerine sahte (Mock) bir Repository verilerek testler veritabanından bağımsız ve milisaniyeler içinde çalıştırılabilir.
* **Pessimistic Locking (`FOR UPDATE`) İzolasyonu:** Eşzamanlı randevu isteklerinde çakışmayı önleyen veritabanı kilit sorguları (`SELECT ... FOR UPDATE`) servislerde karmaşa yaratmadan yalnızca Repository katmanında güvenle yönetilir.

---

### 2. Dependency Injection (DI) Container (Bağımlılıkların Enjeksiyonu Deseni)

#### 📍 Projedeki Kullanım Alanı
Sınıf bağımlılıklarının (`Service` ve `Repository` nesneleri) elle `new` anahtar kelimesiyle oluşturulması yerine, merkezi bir container (`src/Core/Container.php`) ve konfigürasyon dosyası (`config/services.php`) üzerinden yönetilmesi esasına dayanır.

#### Kod Mantığı Örneği
`config/services.php` üzerinden otomatik bağımlılık çözme:
```php
$container->bind(AppointmentService::class, function($c) {
    return new AppointmentService(
        $c->get(AppointmentRepository::class),
        $c->get(EventDispatcher::class)
    );
});