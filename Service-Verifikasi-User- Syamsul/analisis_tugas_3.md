## Gambaran proses bisnis service saya : 
User akan nge POST data untuk verifikasi berupa NIK dan nomor bank ke endpoint /verifications service saya. Admin akan memverifikasi data tersebut. Jika data user sudah terverifikasi maka admin akan nge PUT data tersebut dengan status VERIFIED. User yang sudah berstatus VERIFIED baru bisa untuk melanjutkan ke service selanjutnya yaitu service bidding, tetapi akun yang masih berstatus NOT_VERIFIED tidak bisa melanjutkan ke service selanjutnya.

## Alasan kenapa endpoint PUT /verifications/{id} ini ditembakkan ke SOAP :
Karena dalam proses bisnis lelang ini, persetujuan verifikasi akun (perubahan status dari NOT_VERIFIED menjadi VERIFIED) adalah sebuah event krusial yang harus memiliki bukti rekam jejak. Dengan mengirimkan log ini ke layanan SOAP pusat, saya bisa memastikan bahwa setiap perubahan status akun tercatat secara permanen, sah, dan service saya akan menerima bukti nomor resi (receipt_number) dari layanan dosen (SOAP pusat).

## Alasan kenapa endpoint PUT /verifications/{id} ini disiarkan ke RabbitMQ :
Karena konsep antar aplikasinya kan saling terhubung. Saat admin nge PUT data menjadi VERIFIED, service saya harus langsung ngasih pengumuman (publish message) ke broker pusat karena status VERIFIED adalah indikator apakah user sudah bisa lanjut ke service selanjutnya yaitu bidding atau tidak. Tujuannya supaya service lain (seperti Service Bidding) bisa langsung tahu secara real-time bahwa user tersebut sudah sah dan bisa langsung diberi akses lelang, tanpa perlu melakukan query/nembak API terus-menerus ke service saya.
