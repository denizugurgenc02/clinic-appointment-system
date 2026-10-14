# Yapay Zekâ Günlüğü

Bu dosya,yapay zekâdan alınan desteği kayıt altına alır.
Her kayıtta tarih, amaç, istem (özet), sonuç, değiştirilen/reddedilen kısım ve nedeni yer alır.


---

### Kayıt 3 — FR-002 sonrası: Randevu güncelleme önerisi

- **Tarih:** [11.10.2026]
- **Amaç:** FR-002 yazıldıktan sonra eksik işlevlerin olup olmadığını kontrol etmek.
- **Kullanılan istem (özet):** Yazılan FR-002'ye bakılarak ek bir gereksinim gerekip gerekmediği soruldu.
- **Sonuç:** Yapay zekâ, hastanın randevusuna ait **tarih ve saati güncelleyebilmesini** içeren yeni bir fonksiyonel gereksinim önerdi.
- **Kabul edilen / reddedilen kısım:** Kabul edildi
- **Neden:** Hastanın değişen müsaitlik durumunu da göz önünde bulundurmalıyız.
- **Yapay zekânın yanıldığı / yakalanan durum:** Yok

---

### Kayıt 5 — FR-004: Açıklamanın netleştirilmesi

- **Tarih:** [11.10.2026]
- **Amaç:** FR-004 açıklamasını daha düzgün ve anlaşılır hale getirmek.
- **Kullanılan istem (özet):** Mevcut açıklama verilerek daha anlaşılır ve düzgün bir ifade istendi.
- **Sonuç:** Yapay zekâ açıklama için yeniden yazım önerisi sundu.
- **Değiştirilen kısım:** FR-004 açıklaması, önerilen ifadeden yararlanılarak yeniden yazıldı.
- **Neden:** İlk ifade dil ve anlatım açısından açık değildi.
- **Yapay zekânın yanıldığı / yakalanan durum:** Yok

---

### Kayıt 6 — Sınıf Diyagramı: Kalıtım ve kardinalite yapısının kurulması

- **Tarih:** [14.10.2026]
- **Amaç:** GitHub Issue kriterlerine uygun olarak en az 12 sınıf içeren, kalıtım ve çokluk (1-N) ilişkilerini barındıran ilk sınıf diyagramı taslağını oluşturmak.
- **Kullanılan istem (özet):** Issue'daki sınıf listesi (User, Patient, Doctor, Secretary vb.) ve kabul kriterleri verilerek Mermaid formatında sınıf diyagramı istendi.
- **Sonuç:** Yapay zekâ, `User` tabanından türeyen aktör sınıfları (`User <|-- Patient`, `User <|-- Doctor`, `User <|-- Secretary`), temel varlıkları ve kardinalite bağlantılarını (`1` - `0..*`) içeren 14 sınıflı bir taslak üretti.
- **Kabul edilen / reddedilen kısım:** Kabul edildi
- **Neden:** Issue'da belirtilen en az 12 sınıf şartı ile kalıtım ve 1-N ilişki zorunlulukları başarıyla sağlandı.
- **Yapay zekânın yanıldığı / yakalanan durum:** Yok.

---

### Kayıt 7 — Sınıf Diyagramı: Katman ihlali ve mimari eksikliklerin tespiti

- **Tarih:** [14.10.2026]
- **Amaç:** Üretilen ilk sınıf diyagramının katmanlı mimari kurallarına, şartnamedeki tasarım desenlerine ve sunum kriterlerine uygunluğunu denetlemek.
- **Kullanılan istem (özet):** Hazırlanan diyagramda Controller, Service ve Repository katmanlarının eksiksiz olup olmadığı ve katman ihlali bulunup bulunmadığı sorgulandı.
- **Sonuç:** İlk taslakta Controller katmanının, somut Repository sınıflarının (`MysqlAppointmentRepository`) ve şartnamede açıkça istenen Observer kalıbının yer almadığı tespit edildi.
- **Değiştirilen kısım:** Diyagram yalnızca varlık modelleriyle sınırlandırılmadı; `AppointmentController`, `SlotService`, `AppointmentCancelObserverInterface` ve MySQL arayüz gerçeklemeleri eklenerek 22 bileşenli tam mimariye genişletildi.
- **Neden:** "Controller sadece Service'i çağırır", "Repository arayüzleri somut sınıflarca uygulanır" ve "Observer iptal olayını dinler" mimari kurallarını sağlamak, sunumda katman atlama hatası almamak için yapıldı.
- **Yapay zekânın yanıldığı / yakalanan durum:** Yapay zekâ ilk etapta yalnızca Issue'daki aktör ve entity sınıflarına odaklanarak mimarinin Controller ve somut Repository parçalarını dışarıda bıraktı. Yapılan sorgulama ile bu eksiklik yakalandı ve mimari katmanlar tamamlandı.


---

### Kayıt 8 — Sıralama Diyagramı: Çekirdek akış ve katmanlı mimarinin kurulması

- **Tarih:** [14.10.2026]
- **Amaç:** Çekirdek problem olan eşzamanlı çakışmayı (double-booking) ve randevu alma akışını katmanlı mimariye uygun olarak sıralama diyagramına aktarmak.
- **Kullanılan istem (özet):** Soldan sağa katmanları (Tarayıcı, Controller, Service, Repository) içeren, SQL barındırmayan ve hata dalı içeren bir Mermaid sequence diyagramı istendi.
- **Sonuç:** Yapay zekâ, `POST /randevu/al` çağrısı üzerinden transaction başlatan ve çakışma kontrolü yapan bir sıralama diyagramı oluşturdu.
- **Kabul edilen / reddedilen kısım:** Kısmen kabul edildi; geliştirilmek üzere revize istendi.
- **Neden:** Issue'da yer alan "hasta filtreler -> slot seçer" adımı eksikti; doğrudan randevu kaydı yerine öncesinde slotların listelenmesi gerektiği tespit edildi.
- **Yapay zekânın yanıldığı / yakalanan durum:** İlk taslakta akış doğrudan kayıt işleminden başlıyordu, issue kriterindeki filtreleme ve slot listeleme adımı atlanmıştı.

---

### Kayıt 9 — Sıralama Diyagramı: Uçtan uca veri akışı ve karar bloklarının düzenlenmesi

- **Tarih:** [14.10.2026]
- **Amaç:** Slot listeleme adımında Repository katmanına erişilmesini sağlamak ve `alt...else` karar bloğunda pozitif akışı başa almak.
- **Kullanılan istem (özet):** Slotların Service tarafından doğrudan üretilemeyeceği, takvim verisi için Repository'ye gidilmesi gerektiği belirtildi. Ayrıca `alt` bloğunda ilk durumun "slot müsait", `else` durumunun "slot müsait değil" olması istendi.
- **Sonuç:** Diyagram uçtan uca güncellendi; `GET /slotlar` isteği sonrasında Service'in Repository'den doktor takvimi ve mevcut randevuları çekip slot hesapladığı gösterildi. `alt` bloğu ilk durumu başarılı kayıt, `else` durumu ise çakışma/reddedilme (409 Conflict) olacak şekilde düzenlendi.
- **Kabul edilen / reddedilen kısım:** Kabul edildi.
- **Neden:** Katman atlama ve sahte veri üretme hatası giderildi, issue kriterindeki "uçtan uca akış" ve karar dalı kuralları tam sağlandı.
- **Yapay zekânın yanıldığı / yakalanan durum:** Yapay zekâ slot listeleme isteğinde Repository adımını atlayıp Service üzerinden doğrudan veri dönmüştü; mimari sorgulama ile düzeltildi.



