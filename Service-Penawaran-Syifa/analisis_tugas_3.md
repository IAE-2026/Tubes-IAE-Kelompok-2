Analisis Tugas 3 — Layanan Penawaran (Bid Service)

NAMA    :SYIFA UMMMAYAH
KELAS   :SI4809
NIM     :102022400161

1. Identifikasi Transaksi Kritis
    Endpoint yang Dikelola:
    POST `/api/v1/bids`  — Membuat penawaran baru
    GET `/api/v1/bids`— Mengambil semua data penawaran
    GET `/api/v1/bids/{id}` — Mengambil detail penawaran tertentu

Transaksi Kritis: POST `/api/v1/bids`
 transaksi ini di anggap kritis karena secara langsung mengubah data pada sistem dengan menambahkan data penawaran baru ke dalam database. dan setiap penawaran yang di buat itu berkaitan dengan nilai penawaran yang di ajukan ke pihak lain. jadi, jika terjadi kesalahan di proses ini akan mengakibatkan kerugian pada yang terlibat. 

 transaksi ini juga perlu dicatat pada sistem audit agar riwayat setiap penawaran dapat diliat kembali jika di perlukan. setelah penawaran di buat, informasi juga perlu di kirim ke layanan lain. 

 dan endpoint GET tidak termasuk transaksi kritis karena hanya di gunakan untuk data yang sudah ada dan tidak mengubah kondisi di dalam sistem. 

2. peran dan hak akses pengguna
Admin memiliki hak akses penuh untuk mengelola data penawaran. Bidder dapat membuat penawaran baru serta melihat penawaran yang dimilikinya sendiri. dan viewer dapat melihat daftar dan detail penawaran tanpa dapat melakukan perubahan pada data. Penentuan hak akses dilakukan berdasarkan peran pengguna yang dikirimkan oleh layanan SSO, kemudian disesuaikan dengan data peran yang tersedia pada aplikasi

3. justifikasi Penggunaan SOAP dan RabbitMQ
SOAP (Sistem Audit Legacy)
SOAP digunakan karena sistem audit legacy hanya mendukung komunikasi berbasis XML. Oleh karena itu, Bid Service mengirimkan data penawaran dalam format XML agar setiap transaksi dapat tercatat pada sistem audit.

RabbitMQ (Message Broker)
RabbitMQ digunakan untuk mengirim event penawaran.created secara asynchronous. Dengan cara ini, Bid Service tidak perlu menunggu proses pada layanan lain sehingga respons API tetap cepat dan efisien.