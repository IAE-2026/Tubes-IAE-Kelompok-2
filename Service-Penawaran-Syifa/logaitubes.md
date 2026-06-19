service penawaran
nama : Syifa Ummayah 
nim : 102022400161
kelas: SI4809
kelompok : 2


 Log Pengerjaan (17-19 Juni 2026)

 [17/06/2026 - 15:00] Baru mulai setup koneksi Docker, tolong ajarin cara bikin container biar service aku bisa nembak ke service-verifikasi.

[17/06/2026 - 16:30] Step selanjutnya, buatin fungsi parsing JSON di BidController biar respon dari verifikasi gak error pas dimasukin ke database.

[17/06/2026 - 18:00] Tolong cek validasi status barang di BidController, barang yang statusnya CLOSED masih bisa ke-bid, step pengecekannya gimana ya?

[17/06/2026 - 20:30] Udah berhasil cek status, sekarang buatin logika buat bandingin bid user sama harga dasar biar gak ada yang bid asal-asalan.

[18/06/2026 - 14:00] Step buat error handling pake try-catch udah di-apply di BidController, tolong cek response 503-nya udah bener belum.

[18/06/2026 - 16:00] Sekarang masuk ke step integrasi SSO, tolong modif di SSOService biar ada nim sama api_key.

[18/06/2026 - 17:30] Payload M2M di SSOService masih ditolak, tolong cek formatnya udah sesuai standar dosen belum.

[18/06/2026 - 20:00] Tolong rapihin middleware, biar step akses ke bid cuma bisa dilakuin sama bidder yang role-nya bener.

[19/06/2026 - 09:00] Docker lagi-lagi bermasalah, step benerin docker-compose.yml gimana ya?

[19/06/2026 - 11:30] Step buat nampilin data pemenang, tolong benerin endpoint highest() biar datanya ambil dari service lain, bukan hardcode.

[19/06/2026 - 14:00] Buatin header X-IAE-KEY biar request ke Service Verifikasi 

[19/06/2026 - 16:00] tolong tambahin Cache::remember di SSOService biar gak bolak-balik fetch JWKS.

[19/06/2026 - 19:00] Benerin logika bidding, step buat mastiin lelang yang udah tutup nggak bisa di-bid lagi gimana?

[19/06/2026 - 21:00] Tolong bersihin file BidController sama SSOService dari import yang gak kepake biar rapi.

[19/06/2026 - 22:30] Step terakhir, final check.