# Resume Kontribusi Individu — Tugas Besar IAE

**Nama:** Muhammad Syamsul Arifin
**NIM:** 102022400117
**Kelompok:** 2
**Service:** Service Verifikasi User 


### Detail Kontribusi 

* **Setup Repository Kelompok** (Commit: `364bb87`, `6420633`)
  Saya memulai dengan mengunggah source code Service Verifikasi User milik saya ke repository kelompok. Saya juga merapikan struktur direktori agar semua folder service siap diintegrasikan.

* **Pembuatan Docker dan API Gateway** (Commit: `9a84968`, `08d86a4`)
  Saya membuat file `docker-compose.yml` untuk menggabungkan seluruh container service dari anggota kelompok. Saya juga merancang dan mengonfigurasi `nginx.conf` sebagai API Gateway untuk me-routing setiap endpoint ke microservice yang tepat.

* **Review dan Merge Pekerjaan Anggota Tim** (Commit: `2505fc8`, `bbebbf8`, `1a0c8d5`, `02fc953`, `8860955`)
  Saya melakukan review dan merge pull request dari teman-teman kelompok ke branch master, sekaligus memastikan tidak ada konflik kode pada branch utama.

* **Penyesuaian URL dan Routing Antar-Service** (Commit: `b886d8e`, `a379eba`)
  Saya memperbaiki URL routing pada BidController milik Service Penawaran agar sesuai dengan kontrak API Gateway sehingga service dapat berkomunikasi dengan lancar.

* **Integrasi Service Pemenang/invoice dengan Penawaran** (Commit: `5e4d2c8`, `d913631`)
  Saya menyesuaikan kode agar Service Pemenang dan Invoice bisa terhubung langsung dan menarik data dari endpoint `highest()` milik Service Penawaran.

* **Menyelesaikan Integrasi RabbitMQ** (Commit: `79e0d7f`, `4b88916`)
  Saya melakukan debugging dan memperbaiki struktur pengiriman pesan service saya ke RabbitMQ agar notifikasinya berhasil masuk dan muncul di papan pengumuman dosen.

* **Troubleshooting dan Perbaikan Alur Proses Bisnis** (Commit: `317a0a9`)
  Melakukan perbaikan supaya alur proses bisnis berjalan dengan lancar, beberapa diantaranya adalah:
  - mensinkronkan format `bidder_id` agar sesuai antara service penawaran dan service verifikasi user
  - menambah validasi `VERIFIED` sebelum bidding pada service penawaran 
  - menangani error database SQLite (read-only) pada Docker

* **Penyesuaian Request Token SSO** (Commit: `3bc6778`, `c526ef6`)
   saya memodifikasi function permintaan Token M2M ke SSO Dosen dengan menambahkan field `nim`.

* **Pembuatan Bukti AI** (Commit: `01876f7`)
 saya membuat dokumen `AI-LOG-Tubes.md` sebagai bukti penggunaan AI selama untuk eksplorasi dan teknis dalam menyelesaikan integrasi tugas besar ini.
