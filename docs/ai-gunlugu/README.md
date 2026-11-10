# Yapay Zekâ Günlüğü

Bu klasör, projede yapay zekâdan alınan desteği kayıt altına alır.

## Kurallar

- **Her dosya tek bir AI oturumunu temsil eder.** Oturum, bir kişinin bir AI aracıyla tek bir amaç için yaptığı çalışmadır (ör. "sınıf diyagramı taslağı", "slot üretimi testleri").
- **Dosya adı:** `NNN-gg.aa.yyyy.md` (ör. `004-10.11.2026.md`). `NNN` üç haneli, artan ve tekil oturum numarasıdır; aynı gün birden fazla oturum olabilir.
- **Başlık:** `# #NNN - gg.aa.yyyy`
- Oturum içindeki her önemli istem/yanıt bir **etkileşim** olarak yazılır (`### Etkileşim 1`, `### Etkileşim 2` …).
- AI'ın **yanıldığı ve bizim yakaladığımız** durumlar mutlaka yazılır; boşsa "Yok" yazılır.
- Yeni oturum eklendiğinde aşağıdaki **Oturum Dizini** tablosu da güncellenir.

## Oturum Dizini

| ID | Tarih | Oturum sahibi | Konu | Bağlantılı iş |
| --- | --- | --- | --- | --- |
| [#001](001-11.10.2026.md) | 11.10.2026 | Belirtilmemiş | SRS: fonksiyonel gereksinimlerin gözden geçirilmesi | FR-002, FR-004 |
| [#002](002-14.10.2026.md) | 14.10.2026 | Efe | Sınıf diyagramı | #3 |
| [#003](003-14.10.2026.md) | 14.10.2026 | Efe | Sıralama diyagramı | #6 |
| [#004](004-10.11.2026.md) | 10.11.2026 | Eren | Proje analizi, tasarım tutarsızlıkları, AI günlüğü yapısı | — |

> **Not:** Eski tek dosyalı günlükte (`docs/ai_gunlugu.md`) kayıtlar 3, 5, 6, 7, 8 ve 9 numaralıydı; 1, 2 ve 4 numaralı kayıtlar dosyada yer almıyordu. Mevcut kayıtlar tarih ve konuya göre oturumlara gruplanarak bu yapıya taşındı; içerikleri değiştirilmedi. Eski numaralar her etkileşimde "(eski Kayıt N)" olarak belirtildi.

## Şablon

Yeni oturum için aşağıdaki şablonu kopyalayın:

```markdown
# #NNN - gg.aa.yyyy

| Alan | Değer |
| --- | --- |
| **Oturum sahibi** | Ad Soyad |
| **AI aracı / model** | ör. Claude Code (Opus 5.5), ChatGPT |
| **Amaç** | Oturumun tek cümlelik amacı |
| **Bağlantılı iş** | Gereksinim no / issue / branch / PR |

## Etkileşimler

### Etkileşim 1 — Kısa başlık

- **Kullanılan istem (özet):**
- **Sonuç:**
- **Kabul edilen / değiştirilen / reddedilen kısım:**
- **Neden:**
- **Yapay zekânın yanıldığı / yakalanan durum:** Yok

## Oturum Sonucu

Oturum sonunda ne üretildi, neye karar verildi, açık kalan ne var.
```
