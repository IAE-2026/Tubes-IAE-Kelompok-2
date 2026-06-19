# TUGAS BESAR IAE
## kontribusi dalam kelompok 2

## Nama: Muhammad Zuhdi Robbani
## NIM: 102022400278
## Service: Service Penawaran

*Membantu menganalisis pemahaman tentang proses bisnis dan dependency antar service*

Sebelum proses integrasi dilakukan, saya mempelajari alur bisnis sistem pelelangan dan hubungan antar service yang digunakan dalam aplikasi. 
Dari proses ini saya mengidentifikasi service mana saja yang saling bergantung, data apa yang dibutuhkan, serta endpoint yang digunakan untuk mendukung proses bidding, 
penentuan pemenang, dan pembuatan invoice.

*Penyesuaian Docker pada Service Kelola Item* (3a9c673)

Saya melakukan penyesuaian konfigurasi Docker pada Service Kelola Item agar service dapat dijalankan dengan baik dalam container. 
Perubahan yang dilakukan agar penyesuaian konfigurasi service Kelola Item dapat terhubung dengan komponen lain saat proses integrasi dan pengujian sistem berlangsung.

*Pengembangan REST API untuk Kebutuhan Integrasi* (1d12588)

Karena Service Katalog Items tidak memerlukan data dari service lain, tetapi justru menjadi service yang digunakan oleh beberapa service lain, saya melakukan penyesuaian dan perbaikan pada REST API yang saya buat agar dapat digunakan selama proses integrasi denga perbaikan seperti di kontrak

*Penyesuaian Integrasi SSO* (7925ef4)

Saya melakukan perubahan pada proses autentikasi SSO dengan menambahkan informasi NIM pada request yang dikirim.

*Perbaikan Bug pada Fitur Item untuk Admin* (b4d1514)

Saya memperbaiki bug pada fitur pengelolaan item yang menyebabkan status pelelangan tetap OPEN meskipun waktu berakhir pelelangan telah terlewati.