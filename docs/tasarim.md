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

_Bu bölüm yazılacak: Mermaid ile en az 12 sınıf._

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