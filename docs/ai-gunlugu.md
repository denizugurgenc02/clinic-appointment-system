# Yapay Zekâ Günlüğü

Bu dosya projede yapay zekâdan alınan desteğin dizinidir. Her AI oturumu `docs/ai-gunlugu/` klasöründe ayrı bir
dosyada, etkileşim etkileşim kayıt altındadır. Her etkileşimde şunlar yazılır:

- istem özeti
- sonuç
- önerinin nasıl kontrol edildiği
- kabul edilen, değiştirilen ve reddedilen kısım ile nedeni
- AI'ın yanıldığı ve bizim yakaladığımız durumlar

## Kurallar

- **Her dosya tek bir AI oturumudur.** Dosya adı `NNN-gg.aa.yyyy.md`, başlığı `# #NNN - gg.aa.yyyy`.
- Oturum içindeki her önemli istem ve yanıt bir **etkileşim** olarak yazılır.
- AI'ın **yanıldığı ve yakalanan** durumlar mutlaka yazılır; yoksa "Yok" yazılır.
- Yeni oturum açıldığında aşağıdaki dizin güncellenir. Şablon: [`ai-gunlugu/README.md`](ai-gunlugu/README.md).

## Oturum Dizini

| ID | Tarih | Oturum sahibi | AI aracı | Konu | Bağlantılı iş |
| --- | --- | --- | --- | --- | --- |
| [#001](ai-gunlugu/001-11.10.2026.md) | 11.10.2026 | Belirtilmemiş | Belirtilmemiş | SRS: fonksiyonel gereksinimlerin gözden geçirilmesi | FR-002, FR-004 |
| [#002](ai-gunlugu/002-14.10.2026.md) | 14.10.2026 | Efe | Belirtilmemiş | Sınıf diyagramı | #3 |
| [#003](ai-gunlugu/003-14.10.2026.md) | 14.10.2026 | Efe | Belirtilmemiş | Sıralama diyagramı | #6 |
| [#004](ai-gunlugu/004-10.11.2026.md) | 10.11.2026 | Eren | Claude Code (Opus 5.5) | Tasarımın netleştirilmesi, Aşama 2 prototipinin geliştirilmesi, Phase-2 isterlerine uyum | `asama-2/prototip` |

> Eski tek dosyalı günlükteki kayıtlar 3, 5, 6, 7, 8 ve 9 numaralıydı; 1, 2 ve 4 numaralı kayıtlar o dosyada da yoktu.
> Kayıtlar içerikleri değiştirilmeden oturumlara taşındı.

## AI'ın yanıldığı ve bizim yakaladığımız örnekler (özet)

| Oturum | Hata | Nasıl fark edildi | Düzeltme |
| --- | --- | --- | --- |
| #002 | Sınıf diyagramında Controller, somut Repository ve Observer katmanı yoktu | Katman kurallarına göre gözden geçirme | Diyagram 22 bileşenli tam mimariye genişletildi |
| #003 | Sıralama diyagramında slot listeleme adımında Repository atlanmıştı | Mimari sorgulama | Servis → Repository adımı eklendi |
| #004 | CSRF hatası için standart dışı 419 kodu seçildi; Apache bunu 500'e çevirdi | HTTP ile uçtan uca deneme | 403 yapıldı |
| #004 | `AuthService`, oturumdaki kullanıcı değişse de önbellekteki eski kullanıcıyı döndürüyordu | Yeni yazılan erişim testi kırıldı | Önbellek oturum kimliğine bağlandı |
| #004 | "Slot satır sınırını aşamaz" testi kuralı sınamıyordu: 45 dk'lık slot 3 saate tam bölündüğü için taşma hiç oluşmuyordu | Kuralı bilerek bozma deneyi (M1): kural kaldırıldığında hiçbir test kırılmadı | Test 40 dk'lık slota çevrildi; artık kural bozulunca kırılıyor |
| #004 | Kilitsiz karşılaştırma testi "2 randevu oluşur" varsayıyordu; ara sıra 1 çıktı (kayıp güncelleme) | Testin tekrar tekrar çalıştırılması | Testin iddiası doğru davranışa göre düzeltildi |
| #004 | JSON isteklerinde PHP'nin `Content-Type` başlığını `HTTP_` öneki olmadan verdiği gözden kaçtı | HTTP denemesinde JSON yerine yönlendirme yanıtı geldi | `Request` düzeltildi |
| #004 | Doktor ekranına enum'u dizi anahtarı yapan işlevsiz bir satır yazıldı (çalışınca fatal error verirdi) | Kod gözden geçirme | Satır silindi |

Ayrıntılar ve diğer kayıtlar ilgili oturum dosyalarındadır.
