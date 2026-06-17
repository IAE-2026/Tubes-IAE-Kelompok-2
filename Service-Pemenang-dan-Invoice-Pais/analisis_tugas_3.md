Nama: Fariz Shadiq
NIM: 102022430010
Tim: TEAM-02

1. Justifikasi Transaksi Kritis dan Distribusi Aktivitas Bisnis

Pada sistem lelang yang menggunakan beberapa layanan, proses pembuatan invoice merupakan salah satu transaksi yang paling penting karena berhubungan langsung dengan data pembayaran dan perubahan status pemenang lelang. Oleh karena itu, proses ini perlu dicatat melalui layanan audit dan informasinya perlu disebarkan ke layanan lain yang membutuhkan.

A. Transaksi Penting yang Diaudit Menggunakan SOAP

Pembuatan invoice mengubah status data pemenang dari pending menjadi invoiced serta menghasilkan tagihan yang harus dibayarkan oleh pemenang lelang. Beberapa alasan mengapa proses ini termasuk transaksi penting adalah sebagai berikut.

a. Invoice berisi informasi pembayaran yang harus sesuai dengan data transaksi. Jika terjadi kesalahan pada perhitungan subtotal, pajak, maupun biaya administrasi, maka dapat menimbulkan masalah pada proses pembayaran.

b. Setiap invoice yang berhasil dibuat harus tercatat pada sistem audit pusat. Penggunaan SOAP/XML memastikan data audit dikirim dengan format yang sudah ditentukan sehingga sistem dapat menghasilkan ReceiptNumber sebagai bukti bahwa transaksi telah berhasil dicatat.

c. Pada proses audit juga disertakan TeamID yaitu TEAM-02 sebagai identitas kelompok yang melakukan transaksi sehingga data yang diterima oleh sistem pusat dapat dikenali dengan jelas.

B. Distribusi Aktivitas Bisnis Menggunakan RabbitMQ

Setelah invoice berhasil dibuat, informasi tersebut perlu diketahui oleh layanan lain tanpa harus membuat pengguna menunggu seluruh proses selesai. Untuk itu digunakan RabbitMQ sebagai message broker.

a. Layanan notifikasi dapat menggunakan informasi invoice yang diterima untuk mengirim email atau pemberitahuan kepada pemenang lelang.

b. Layanan pembayaran dapat mulai melakukan pemantauan terhadap pembayaran yang masuk berdasarkan nomor invoice yang telah dibuat.

c. Penggunaan RabbitMQ membuat layanan Invoice-Winner tidak perlu menjalankan seluruh proses lanjutan secara langsung. Sistem hanya perlu mengirim event InvoiceCreated ke exchange iae.central.exchange dengan routing key invoice.created, kemudian layanan lain dapat mengambil pesan tersebut sesuai kebutuhannya.

2. Sequence Diagram Aliran Interaksi Internal dan Terpusat

Sequence diagram berikut menggambarkan alur proses pembuatan invoice mulai dari pengguna mengirimkan permintaan ke sistem hingga invoice berhasil dibuat dan didistribusikan ke layanan lain. Proses diawali dengan validasi token JWT melalui middleware yang terhubung ke layanan SSO. Setelah token dinyatakan valid, sistem melakukan pemetaan pengguna ke database lokal dan melanjutkan proses pembuatan invoice.

Selanjutnya controller melakukan perhitungan data invoice berdasarkan informasi pemenang lelang dan menyimpan data tersebut ke database. Setelah data berhasil tersimpan, sistem mengirimkan informasi audit ke layanan SOAP untuk mendapatkan ReceiptNumber sebagai bukti bahwa transaksi telah tercatat pada sistem pusat. ReceiptNumber yang diterima kemudian disimpan kembali ke database invoice.

Tahap terakhir adalah publikasi event ke RabbitMQ menggunakan routing key invoice.created. Event ini memungkinkan layanan lain seperti layanan notifikasi dan layanan pembayaran untuk menerima informasi invoice tanpa harus terhubung langsung ke proses utama. Dengan pendekatan ini, proses integrasi menjadi lebih fleksibel dan tidak saling bergantung secara langsung.

![Sequence Diagram](sequence_diagram.png)

3. Detail Implementasi Teknis

A. Federated SSO

Sistem menggunakan mekanisme Federated SSO. Token JWT yang diperoleh dari portal SSO diverifikasi menggunakan public key RS256 yang diambil dari endpoint JWKS. Jika pengguna belum tersedia pada database lokal, sistem akan membuat data pengguna baru dan memberikan role Warga sehingga dapat mengakses endpoint yang tersedia.

B. Integrasi SOAP Audit

Integrasi SOAP dilakukan dengan menyusun XML sesuai format yang telah ditentukan. Data audit yang dikirim memuat informasi transaksi invoice beserta identitas mahasiswa yang terdapat pada payload JSON. Setelah proses audit berhasil dilakukan, sistem menerima ReceiptNumber yang kemudian disimpan ke database sebagai bukti bahwa transaksi telah tercatat pada layanan audit pusat.

C. Integrasi RabbitMQ

RabbitMQ digunakan sebagai media distribusi event antar layanan. Setelah invoice berhasil dibuat, sistem mengirim event invoice.created ke exchange iae.central.exchange. Event tersebut berisi informasi invoice dan data pengguna yang melakukan transaksi sehingga layanan lain dapat memproses informasi tersebut sesuai kebutuhan tanpa harus mengakses layanan invoice secara langsung.
