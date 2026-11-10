# Yapay Zekâ Günlüğü — Oturum Dosyaları

Bu klasördeki her dosya tek bir AI oturumudur. **Kurallar, oturum dizini ve AI'ın yanıldığı örneklerin özeti
[`../ai-gunlugu.md`](../ai-gunlugu.md) dosyasındadır**; yeni oturum eklendiğinde oradaki dizin güncellenir.

## Şablon

Yeni oturum için aşağıdaki şablonu `NNN-gg.aa.yyyy.md` adıyla kopyalayın:

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
- **Nasıl kontrol edildi:** (test, HTTP denemesi, kod okuma ...)
- **Kabul edilen / değiştirilen / reddedilen kısım:**
- **Neden:**
- **Yapay zekânın yanıldığı / yakalanan durum:** Yok

## Oturum Sonucu

Oturum sonunda ne üretildi, neye karar verildi, açık kalan ne var.
```
