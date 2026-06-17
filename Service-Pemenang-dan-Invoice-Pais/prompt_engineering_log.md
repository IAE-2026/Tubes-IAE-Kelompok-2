# Prompt Engineering Log & Eksplorasi Teknis (Modul 4)

**Nama**: Fariz Shadiq  
**NIM**: 102022430010  
**Grup/Tim**: TEAM-02  

Log ini mencatat interaksi kolaboratif antara mahasiswa dengan AI Assistant (Antigravity) dalam merancang, men-debug, dan menyelesaikan Tugas 3 untuk integrasi layanan terdistribusi.

---

## 1. Tahap Inisiasi & Analisis Kebutuhan
**Prompt Awal Pengguna:**
> Luaran Tugas 3  
> Luaran yang harus ada pada repository individual:  
> 1. Dokumen analisis (file analisis_tugas_3.md):  
> - Penjelasan transaksi yang dinilai adalah transaksi penting (menggunakan SOAP) dan/atau transaksi yang harus disebarkan (RabbitMQ)  
> - Sequence Diagram Internal yang menggambarkan aliran interaksi dengan layanan terpusat (yang disediakan dosen)  
> 2. Capaian Teknis: Sesuai dengan komponen teknis pada slide sebelumnya  
> Akun saya itu: warga07@ktp.iae.id & API-KEY: KEY-MHS-71  

**Eksplorasi AI & Pengguna:**
- AI menganalisis repositori Laravel yang ada, menemukan bahwa repositori belum dikonfigurasi (.env belum ada, dependensi belum di-install).
- AI menginisiasi pembuatan `.env` dari `.env.example`, menjalankan `composer install`, dan menggenerasi application key.
- Mengidentifikasi bahwa kita membutuhkan library `firebase/php-jwt` untuk mengelola JWT/JWKS RS256 dari SSO Server.

---

## 2. Eksplorasi Endpoint & Uji Konektivitas
Untuk menghindari kesalahan integrasi, AI mengusulkan pembuatan file test script temporary (`test_sso.php`, `test_m2m.php`, `test_user.php`) untuk memeriksa response dari server terpusat.

**Hasil Temuan Mandiri:**
1. **JWKS**: Mengembalikan key RS256 dengan kid `iae-central-2026`.
2. **M2M Token**: Dipanggil menggunakan `KEY-MHS-71` dan mengembalikan data grup/tim (`TEAM-02` yang mewakili NIM `102022430010`).
3. **User Token**: Dipanggil menggunakan `warga07@ktp.iae.id` dan password `KtpDigital2026!` yang mengembalikan nama profil warga `Galih Mahendra`.
4. **RabbitMQ Publisher**: Berhasil dipublikasikan dengan format JSON yang memuat properti `message` dan parameter `routing_key`.

---

## 3. Penanganan Bug & Debugging
Selama penulisan test suite otomatis (`SsoIntegrationTest.php`), terjadi dua kendala utama yang berhasil diselesaikan secara mandiri bersama AI:

### Kendala A: Kegagalan OpenSSL Key Generation pada Windows
- **Gejala**: `openssl_pkey_export(): Cannot get key from parameter 1`
- **Penyebab**: Windows PHP build tidak mengetahui letak file `openssl.cnf` secara default.
- **Solusi**: AI memeriksa keberadaan file `openssl.cnf` di path standard XAMPP (`C:\xampp\php\extras\ssl\openssl.cnf`) dan Git (`C:\Program Files\Git\usr\ssl\openssl.cnf`), lalu secara eksplisit mengoper parameter `"config"` ke `openssl_pkey_new` dan `openssl_pkey_export` dalam test setup.

### Kendala B: Batasan Panjang Kunci (RSA Key Length) pada JWT
- **Gejala**: `DomainException: Provided key is too short`
- **Penyebab**: `firebase/php-jwt` memaksakan standar keamanan minimum panjang kunci RSA sebesar 2048-bit, sedangkan test awal hanya menggenerasi 1024-bit untuk kecepatan eksekusi.
- **Solusi**: Mengubah panjang bit kunci RSA di dalam test setup menjadi 2048-bit secara konsisten.

---

## 4. Penyesuaian Identitas pada Payload Virtual Board
**Permintaan Pengguna:**
> nah kalo bisa nnti pas di cek di virtual board itu ada nama nim gua Fariz Shadiq 102022430010 tetapi nama dan nim itu tidak menggangu identitas yg saya kasih di awal, seperti akun sso ya  

**Solusi Eksplorasi:**
- Autentikasi SSO menggunakan token warga dari SSO terpusat tetap dipertahankan murni tanpa modifikasi agar tidak merusak validasi signature JWT.
- Nama **Fariz Shadiq** dan NIM **102022430010** disisipkan secara aman ke dalam:
  1. Payload `LogContent` (dalam tag CDATA) pada request audit SOAP.
  2. Properti `student_name` dan `student_nim` di payload event RabbitMQ.
- Ini menjamin data mahasiswa muncul di Virtual Board dosen tanpa merusak mekanisme keamanan token.
