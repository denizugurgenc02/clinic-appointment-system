# Klinik Randevu Sistemi

PHP Programlama dersi dönem projesi. Hasta poliklinik ve doktor seçip boş saatlerden randevu alır; doktorların çalışma
takvimi ve izinleri tanımlanır; iptal olunca bekleme listesindeki ilk hastaya süreli teklif gider; sekreter paneli
randevuları yönetir.

## Ekip

| Öğrenci No | Ad Soyad |
| --- | --- |
| 2307231001 | Hatice AYVAZ |
| 2307231054 | Mehmet Efe KAYA |
| 2307231029 | Eren ÇİT |
| 2307231030 | Deniz Uğur GENÇ |

## Gereksinimler

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Docker Compose dahil), açık ve çalışır durumda
- [Git](https://git-scm.com/)
- Boş `8000` portu

PHP, Apache, Composer veya veritabanı kurmanız gerekmez; hepsi Docker içinde gelir.

## Kurulum ve Çalıştırma

```bash
git clone https://github.com/denizugurgenc02/clinic-appointment-system.git
cd clinic-appointment-system
docker compose up -d --build
```

İlk açılışta bağımlılıklar kurulur ve örnek veri yüklenir; bu birkaç dakika sürebilir. Ardından uygulama
**<http://localhost:8000>** adresinde açılır.

Durdurmak için: `docker compose down`

## Giriş Bilgileri

**Süper yönetici:** kullanıcı adı **`admin`**, şifre **`asdf1234`**. Tüm işlemlere yetkilidir; uygulamaya gömülüdür,
veri olmasa da giriş yapılabilir.

Örnek veri hesapları (şifre: **`Klinik123!`**):

| Rol | E-posta |
| --- | --- |
| Hasta | `hasta@klinik.test` |
| Doktor | `ayse.yilmaz@klinik.test` |
| Sekreter | `sekreter@klinik.test` |
