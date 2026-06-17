# 📊 Laporan Kesiapan Integrasi Antar-Service Kelompok 2

Berdasarkan hasil pemindaian kode pada masing-masing repository mini-service, berikut adalah laporan status kesiapan integrasi untuk Tugas Besar.

---

## 1. Service Verifikasi User (Syamsul) — ✅ SIAP 100%
Semua rute krusial telah tersedia dan sesuai standar integrasi.

- `POST /v1/verifications` : ✅ Ada
- `PUT /v1/verifications/{id}` : ✅ Ada + SOAP + RabbitMQ
- `GET /v1/verifications` : ✅ Ada
- `GET /v1/verifications/{id}` : ✅ Ada (Pencarian by user_id untuk integrasi internal)
- `Dockerfile` : ✅ Ada

**Status:** Tidak ada perbaikan yang diperlukan. Service sudah siap digabungkan..

---

## 2. Service Penawaran (Syifa) — ⚠️ PERLU PERHATIAN KECIL
Kode logika integrasi (REST ke service lain) sudah ditulis, namun URL-nya masih mengarah ke *localhost* statis.

- `POST /v1/bids` : ✅ Ada + SOAP + RabbitMQ
- `GET /v1/bids` : ✅ Ada
- `GET /v1/bids/{id}` : ✅ Ada
- `Dockerfile` : ✅ Ada

> [!WARNING]
> **Masalah:** Pada `BidController.php` baris 121-122, URL HTTP GET masih menggunakan `localhost:8004` dan `localhost:8001`. Di dalam ekosistem Docker Compose, ini tidak akan terbaca.
> **Solusi:** Ganti `localhost:port` menjadi nama container. Contoh: `http://service-verifikasi/api/v1/verifications/`

---

## 3. Service Katalog Barang (Bani) — ⚠️ PERLU PERHATIAN SEDANG
Logika katalog sudah berjalan dengan baik dan memiliki fitur *caching*, namun ada sedikit ketidaksesuaian dengan kontrak URL.

- `GET /items` : ✅ Ada (dilengkapi filter, search, cache)
- `GET /items/{id}` : ✅ Ada
- `POST /admin/items` : ✅ Ada
- `Dockerfile` : ⚠️ Belum ada di *root* folder (kemungkinan berada di dalam folder `docker/`). Harus disesuaikan agar mudah di-*build* oleh master compose.

> [!WARNING]
> **Masalah:** URL di `routes/api.php` tidak menggunakan awalan `v1`. Saat ini rutenya adalah `/api/items`, padahal di kontrak integrasi tertulis `/api/v1/items`.
> **Solusi:** Bungkus rute dengan `Route::prefix('v1')` seperti service lainnya.

---

## 4. Service Pemenang & Invoice (Pais) — 🚨 KRITIS
Service ini masih banyak yang berupa kerangka (placeholder) dan belum siap di-*build* ke dalam infrastruktur Docker.

- `GET /v1/winners` : ✅ Ada
- `GET /v1/winners/{id}` : ✅ Ada
- `POST /v1/invoices` : ✅ Ada + SOAP + RabbitMQ
- `GET /v1/invoices` : ⚠️ Masih *placeholder* (Hanya return teks statis, belum *query* DB)
- `GET /v1/invoices/{id}` : ⚠️ Masih *placeholder* (Hanya return teks statis)
- `Dockerfile` : ❌ **TIDAK ADA!**

> [!CAUTION]
> **Masalah Utama:** Service ini tidak memiliki `Dockerfile`. Tanpa file ini, service Pais tidak bisa diikutkan ke dalam `docker-compose.yml` gabungan kelompok. Selain itu, fungsi baca data (GET) masih kosong.
> **Solusi:** Segera buat `Dockerfile` standar Laravel dan lengkapi logika `index()` serta `show()` pada `InvoiceController.php`.

---

## 🎯 Daftar Tindakan Lanjutan (Action Items) Kelompok

1. **[Semua Anggota]** Menyetujui normalisasi (perubahan) nama folder agar tidak mengandung spasi (contoh: diubah menjadi `service-verifikasi`, `service-penawaran`, dst). Spasi akan memicu *error volume mounting* di Docker.
2. **[Syifa]** Mengubah URL `localhost` di fungsi *bidding* menjadi nama container.
3. **[Bani]** Menambahkan prefix `v1` pada `routes/api.php` dan menyiapkan `Dockerfile` di *root* service-nya.
4. **[Pais]** Segera membuat `Dockerfile` dan melengkapi fungsi GET invoice.
5. **[DevOps/Syamsul]** Membuat file master `docker-compose.yml` dan mengonfigurasi *API Gateway* (Nginx) di *root repository* kelompok.
