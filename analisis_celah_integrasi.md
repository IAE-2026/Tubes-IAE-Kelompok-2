# 🔍 Analisis Celah Logika Bisnis & Keamanan SSO — Tugas Besar Integrasi

> **Tanggal Analisis:** 18 Juni 2026  
> **Dianalisis oleh:** AI Assistant  
> **Cakupan:** 4 Microservices (Syamsul, Bani, Syifa, Pais)

---

## Daftar Isi

1. [Ringkasan Temuan](#ringkasan-temuan)
2. [Celah Logika Bisnis](#a-celah-logika-bisnis)
3. [Celah Keamanan SSO](#b-celah-keamanan-sso)
4. [Yang Sudah Benar](#c-yang-sudah-benar)

---

## Ringkasan Temuan

| # | Kategori | Tingkat | Service | Ringkasan |
|---|----------|---------|---------|-----------|
| 1 | Logika Bisnis | 🔴 Kritis | Syifa | Bidding tetap lolos meski validasi antar-service gagal |
| 2 | Logika Bisnis | 🔴 Kritis | Syifa | Tidak mengecek status verifikasi user (`VERIFIED` / `NOT_VERIFIED`) |
| 3 | Logika Bisnis | 🟡 Sedang | Syifa | Tidak mengecek status barang lelang (`OPEN` / `CLOSED`) |
| 4 | Logika Bisnis | 🟡 Sedang | Syifa | Tidak ada validasi harga bid vs harga dasar barang |
| 5 | Logika Bisnis | 🟡 Sedang | Syifa | Data pemenang (nama, email) di endpoint `highest` adalah hardcode |
| 6 | Logika Bisnis | 🟡 Sedang | Syifa | `auction_status` selalu dikembalikan `"ended"` (hardcode) |
| 7 | Keamanan SSO | 🔴 Kritis | Semua | Setiap service punya mekanisme autentikasi yang berbeda-beda |
| 8 | Keamanan SSO | 🔴 Kritis | Syamsul | Pintu belakang `X-IAE-KEY` tanpa identitas user |
| 9 | Keamanan SSO | 🔴 Kritis | Syamsul | Siapapun bisa meng-verify dirinya sendiri (tidak ada role check) |
| 10 | Keamanan SSO | 🔴 Kritis | Bani | Sistem autentikasi terpisah dari SSO (pakai tabel `api_keys` sendiri) |
| 11 | Keamanan SSO | 🟡 Sedang | Syifa, Pais, Syamsul | Role assignment inkonsisten antar service |
| 12 | Keamanan SSO | 🟡 Sedang | Syifa | JWKS tidak di-cache (fetch ke SSO server setiap request) |

---

## A. Celah Logika Bisnis

### 🔴 Celah #1: Bidding Tetap Lolos Meski Validasi Antar-Service Gagal

- **Service:** Syifa (Penawaran)
- **File:** `Service-Penawaran-Syifa/app/Http/Controllers/API/BidController.php`
- **Baris:** 120–141

**Kode bermasalah:**
```php
try {
    $userCheck = Http::withHeaders([...])->get($verifikasiUrl . $request->bidder_id);
    $itemCheck = Http::get($katalogUrl . $request->item_id);

    if (!$userCheck->successful() || !$itemCheck->successful()) {
        return response()->json([...], 403); // Ditolak jika gagal
    }
} catch (\Exception $e) {
    // ⚠️ MASALAH: Jika koneksi timeout/error, bid TETAP DILANJUTKAN!
    Log::warning('Koneksi ke microservice verifikasi/item gagal, melanjutkan pemrosesan: ' . $e->getMessage());
}

// Bid tetap dibuat meskipun validasi tidak pernah jalan!
$bid = Bid::create([...]);
```

**Dampak di dunia nyata:**  
Jika Service Verifikasi (Syamsul) atau Service Katalog (Bani) sedang *down*, semua validasi di-*bypass* secara diam-diam. User yang belum terverifikasi atau barang yang sudah ditutup tetap bisa di-bid.

**Solusi:**  
Ubah blok `catch` agar mengembalikan error `503 Service Unavailable`, bukan melanjutkan proses.

---

### 🔴 Celah #2: Tidak Mengecek Status Verifikasi User

- **Service:** Syifa (Penawaran)
- **File:** `Service-Penawaran-Syifa/app/Http/Controllers/API/BidController.php`
- **Baris:** 124

**Kode bermasalah:**
```php
$userCheck = Http::withHeaders(['X-IAE-KEY' => '102022400117'])
    ->get($verifikasiUrl . $request->bidder_id);

// Hanya mengecek: apakah HTTP response sukses (200)?
if (!$userCheck->successful()) { ... }
```

**Dampak di dunia nyata:**  
Endpoint `GET /verifications/{id}` milik Syamsul mengembalikan **200 OK** selama data verifikasi ada, **terlepas dari statusnya** (`VERIFIED` atau `NOT_VERIFIED`). Seorang user yang baru mendaftar dan masih berstatus `NOT_VERIFIED` tetap bisa melakukan bidding.

**Solusi:**  
Tambahkan pengecekan isi response body:
```php
$userData = $userCheck->json();
if ($userData['data']['verification_status'] !== 'VERIFIED') {
    return response()->json(['message' => 'User belum terverifikasi'], 403);
}
```

---

### 🟡 Celah #3: Tidak Mengecek Status Barang Lelang

- **Service:** Syifa (Penawaran)
- **File:** `Service-Penawaran-Syifa/app/Http/Controllers/API/BidController.php`
- **Baris:** 125

**Kode bermasalah:**
```php
$itemCheck = Http::get($katalogUrl . $request->item_id);

// Hanya mengecek: apakah barang ada?
if (!$itemCheck->successful()) { ... }
```

**Dampak di dunia nyata:**  
Barang dengan status `CLOSED`, `CANCELLED`, atau `DRAFT` tetap bisa di-bid, karena endpoint `GET /items/{id}` milik Bani mengembalikan 200 OK untuk semua status.

**Solusi:**  
Cek field `status` di response body:
```php
$itemData = $itemCheck->json();
if (($itemData['data']['status'] ?? '') !== 'OPEN') {
    return response()->json(['message' => 'Barang tidak sedang dilelang'], 403);
}
```

---

### 🟡 Celah #4: Tidak Ada Validasi Harga Bid vs Harga Dasar

- **Service:** Syifa (Penawaran)
- **File:** `Service-Penawaran-Syifa/app/Http/Controllers/API/BidController.php`
- **Baris:** 97–103

**Kode saat ini:**
```php
$request->validate([
    'bidder_id' => 'required',
    'item_id'   => 'required',
    'bid_amount' => 'required|numeric', // Hanya cek: apakah angka?
]);
```

**Dampak di dunia nyata:**  
User bisa menawar Rp 1.000 untuk Laptop ASUS ROG seharga Rp 15.000.000 — dan tawaran diterima! Tidak ada pengecekan apakah `bid_amount` ≥ `base_price` atau ≥ `current_price`.

**Solusi:**  
Setelah mengambil data item dari Bani, bandingkan harganya:
```php
if ($request->bid_amount < $itemData['data']['base_price']) {
    return response()->json(['message' => 'Bid harus lebih tinggi dari harga dasar'], 422);
}
```

---

### 🟡 Celah #5: Data Pemenang pada Endpoint `highest` Adalah Hardcode

- **Service:** Syifa (Penawaran)
- **File:** `Service-Penawaran-Syifa/app/Http/Controllers/API/BidController.php`
- **Baris:** 209–212

**Kode bermasalah:**
```php
$data = [
    "bidder_name"  => "Bidder " . $highestBid->bidder_id,    // ⚠️ Bukan nama asli
    "bidder_email" => $highestBid->bidder_id . "@ktp.iae.id", // ⚠️ Bukan email asli
    "item_name"    => "Barang " . $highestBid->item_id,       // ⚠️ Bukan nama barang asli
];
```

**Dampak di dunia nyata:**  
Invoice yang diterbitkan oleh Pais menampilkan `"bidder_name": "Bidder 2"` dan `"bidder_email": "2@ktp.iae.id"` — bukan data sebenarnya. Jika ini adalah invoice resmi, data ini tidak sah secara hukum.

**Solusi:**  
Tarik data nama/email dari Service Verifikasi (Syamsul) dan nama barang dari Service Katalog (Bani) secara real-time, atau simpan data tersebut di tabel `bids` saat bidding pertama kali dibuat.

---

### 🟡 Celah #6: `auction_status` Selalu `"ended"` (Hardcode)

- **Service:** Syifa (Penawaran)
- **File:** `Service-Penawaran-Syifa/app/Http/Controllers/API/BidController.php`
- **Baris:** 215–216

**Kode bermasalah:**
```php
"auction_status"   => "ended",           // ⚠️ Selalu "ended"
"auction_ended_at" => now()->toIso8601String() // ⚠️ Selalu waktu sekarang
```

**Dampak di dunia nyata:**  
Service Pais mengecek apakah `auction_status === 'ended'` sebelum membuat invoice. Karena endpoint ini selalu mengembalikan `"ended"`, invoice bisa dibuat **kapan saja** — bahkan saat lelang masih berlangsung (belum melewati `auction_end_at`).

**Solusi:**  
Tarik `auction_end_at` dari Service Katalog (Bani) dan bandingkan dengan waktu sekarang:
```php
"auction_status" => now()->gt($item->auction_end_at) ? "ended" : "ongoing"
```

---

## B. Celah Keamanan SSO

### 🔴 Celah #7: Setiap Service Punya Mekanisme Autentikasi yang Berbeda

- **Service:** Semua (Syamsul, Bani, Syifa, Pais)

**Perbandingan mekanisme:**

| Service | Middleware | Cara Autentikasi |
|---------|-----------|-----------------|
| **Syamsul** | `VerifyIaeKey` | Bearer Token SSO **ATAU** header `X-IAE-KEY: 102022400117` |
| **Bani** | `ApiKeyMiddleware` | Bearer Token / `X-API-Key` → dicek hash SHA-256 ke tabel `api_keys` lokal |
| **Syifa** | `SSOAuthMiddleware` | JWT SSO (untuk POST bidding) |
| **Syifa** | `ApiKeyMiddleware` | Header `X-IAE-KEY` (untuk GET bids) |
| **Pais** | `SsoJwtAuth` | Bearer Token SSO saja |

**Dampak di dunia nyata:**  
Seorang user yang membawa 1 identitas (1 token SSO) harus mengetahui mekanisme berbeda di setiap service. Ini membuat integrasi antar-service menjadi rapuh dan membingungkan. Contoh nyata: token `warga27` berlaku di Syamsul, Syifa, dan Pais — tapi **tidak berlaku** di Bani karena Bani pakai sistem sendiri.

**Solusi ideal:**  
Standarisasi seluruh service agar menggunakan **satu mekanisme** — yaitu SSO JWT Bearer Token dengan JWKS verification. API Gateway sebaiknya yang menangani validasi token terpusat.

---

### 🔴 Celah #8: Pintu Belakang `X-IAE-KEY` Tanpa Identitas User

- **Service:** Syamsul (Verifikasi)
- **File:** `Service-Verifikasi-User- Syamsul/app/Http/Middleware/VerifyIaeKey.php`
- **Baris:** 25–28

**Kode bermasalah:**
```php
$headerkey = $request->header('X-IAE-KEY');
if ($headerkey === '102022400117') {
    return $next($request); // ⚠️ LANGSUNG LOLOS! Tanpa tahu siapa yang mengakses.
}
```

**Dampak di dunia nyata:**
- Siapapun yang mengetahui key `102022400117` bisa mengakses **SEMUA endpoint** Service Verifikasi tanpa identitas
- Bisa membuat data verifikasi atas nama orang lain
- Bisa mengubah status `NOT_VERIFIED` → `VERIFIED` secara ilegal
- Tidak ada **audit trail** (jejak) tentang siapa yang melakukan aksi tersebut
- Key ini bahkan tertulis secara *hardcode* di source code — jika repo di-publish ke GitHub, key ini bocor

**Solusi:**  
Hapus fallback `X-IAE-KEY` untuk endpoint publik. Gunakan SSO token sebagai satu-satunya cara autentikasi. Jika perlu M2M (machine-to-machine) access untuk komunikasi antar-service, gunakan token M2M dari SSO server.

---

### 🔴 Celah #9: Siapapun Bisa Meng-VERIFY Dirinya Sendiri

- **Service:** Syamsul (Verifikasi)
- **File:** `Service-Verifikasi-User- Syamsul/app/Http/Controllers/VerificationController.php`
- **Baris:** 81–131

**Kode bermasalah:**
```php
public function update(Request $request, $id)
{
    $verification = Verification::find($id);
    // ... validasi input ...

    $verification->update([
        'verification_status' => $status // ⚠️ Tidak ada cek: siapa yang mengubah?
    ]);
}
```

**Dampak di dunia nyata:**  
Endpoint `PUT /verifications/{id}` tidak membedakan apakah yang mengakses adalah **Admin/Petugas** atau **User biasa**. Dalam proses bisnis yang benar:
- User biasa hanya boleh **mengajukan** verifikasi (`POST`)
- Hanya Admin/Petugas yang boleh **menyetujui/menolak** verifikasi (`PUT`)

Saat ini, user biasa bisa mendaftarkan diri (Langkah 1) lalu langsung meng-approve dirinya sendiri (Langkah 2) tanpa hambatan.

**Solusi:**  
Tambahkan pengecekan role di dalam fungsi `update`:
```php
$email = $request->attributes->get('user_email');
$role = Role::where('email', $email)->first();
if (!$role || $role->role_name !== 'admin') {
    return $this->errorResponse('Forbidden. Hanya admin yang boleh mengubah status verifikasi.', 403);
}
```

---

### 🔴 Celah #10: Service Bani Terpisah Total dari Ekosistem SSO

- **Service:** Bani (Katalog)
- **File:** `Service-managed items-bani/app/Http/Middleware/ApiKeyMiddleware.php`
- **Baris:** 21–23

**Kode bermasalah:**
```php
$apiKey = ApiKey::query()
    ->where('key_hash', hash('sha256', $plainKey))
    ->first();
```

**Dampak di dunia nyata:**
- Service Bani **tidak mengenal SSO** sama sekali. Dia punya database `api_keys` sendiri
- Token SSO `warga27@ktp.iae.id` yang valid di 3 service lain **ditolak** oleh Bani
- Harus menggunakan token/key milik `warga41` yang kebetulan terdaftar di tabel `api_keys` Bani
- Jika warga41 di-*revoke* dari SSO, dia **tetap bisa** mengakses Bani
- Jika warga41 keluar dari kelompok, tidak ada yang bisa membuat barang baru kecuali seseorang menambahkan API key baru langsung ke database Bani

**Solusi:**  
Integrasikan Bani ke SSO yang sama. Gunakan JWT SSO untuk autentikasi admin, lalu cek role dari payload token (bukan dari tabel lokal).

---

### 🟡 Celah #11: Role Assignment Inkonsisten Antar Service

- **Service:** Syamsul, Syifa, Pais

**Bagaimana setiap service menentukan role dari token SSO yang sama:**

| Service | Cara Menentukan Role | Contoh untuk `warga27@ktp.iae.id` |
|---------|---------------------|----------------------------------|
| **Syamsul** | Langsung ambil `$decoded->role` dari JWT payload | Tergantung isi token (misal: `user`) |
| **Syifa** | Cek apakah email mengandung `warga` atau `ktp.iae.id` → `bidder`, sisanya → `viewer` | `bidder` |
| **Pais** | Semua user SSO biasa → `Warga`, token M2M → `Admin` | `Warga` |

**Dampak di dunia nyata:**  
Satu orang yang sama (`warga27@ktp.iae.id`) memiliki **3 identitas role berbeda** di 3 service berbeda. Jika suatu hari dosen menambahkan field `role: admin` di token SSO-nya:
- Di **Syamsul**: dia menjadi `admin` (karena langsung baca dari token)
- Di **Syifa**: dia tetap `bidder` (karena Syifa override role berdasarkan email)
- Di **Pais**: dia tetap `Warga` (karena Pais assign semua user token sebagai Warga)

**Solusi:**  
Standarisasi role mapping. Semua service harus membaca role dari **satu sumber yang sama** — idealnya dari field `role` di JWT payload SSO.

---

### 🟡 Celah #12: JWKS Tidak Di-Cache di Service Syifa

- **Service:** Syifa (Penawaran)
- **File:** `Service-Penawaran-Syifa/app/Services/SSOService.php`
- **Baris:** 22

**Kode bermasalah:**
```php
public function verifyToken(string $token): object
{
    $jwksUrl = $this->getBaseUrl() . '/api/v1/auth/jwks';
    $response = Http::get($jwksUrl); // ⚠️ Fetch baru SETIAP request!
    // ...
}
```

**Perbandingan caching JWKS antar service:**

| Service | Cache JWKS? | Durasi Cache |
|---------|------------|-------------|
| **Syamsul** | ✅ Ya | 1 jam (3600 detik) |
| **Syifa** | ❌ Tidak | - (fetch setiap request) |
| **Pais** | ✅ Ya | 24 jam (86400 detik) |

**Dampak di dunia nyata:**
- Setiap kali ada user melakukan bidding, Syifa menembak server SSO dosen untuk mengambil public key
- Dengan ribuan user, ini menyebabkan **latency tinggi** dan berpotensi kena **rate-limiting** dari server SSO
- Jika server SSO dosen sedang down, **seluruh fitur bidding mati total**

**Solusi:**  
Tambahkan caching seperti service lain:
```php
$jwks = Cache::remember('sso_jwks', 3600, function () use ($jwksUrl) {
    return Http::get($jwksUrl)->json();
});
```

---

## C. Yang Sudah Benar ✅

Meskipun ada celah-celah di atas, berikut adalah aspek-aspek yang **sudah diimplementasikan dengan baik**:

| # | Aspek | Service | Keterangan |
|---|-------|---------|------------|
| 1 | Duplikasi invoice dicegah | Pais | Cek `auction_id` unique sebelum buat invoice baru |
| 2 | Perhitungan pajak & admin fee akurat | Pais | PPN 11% + Admin 2% terhitung presisi |
| 3 | SOAP Audit tercatat | Semua | Setiap operasi penting di-log ke sistem audit |
| 4 | RabbitMQ event terpublikasi | Semua | Event dikirim ke message broker setelah setiap aksi |
| 5 | JWT token di-decode dengan JWKS | Syamsul, Syifa, Pais | Verifikasi kriptografis, bukan sekadar decode |
| 6 | Bidder tidak bisa bid atas nama orang lain | Syifa | `bidder_id` dicocokkan dengan `sso_id` dari token |
| 7 | Database transaction dengan rollback | Pais | Jika gagal di tengah jalan, semua perubahan di-*rollback* |
| 8 | Pagination di endpoint list | Bani, Pais | Mencegah overload jika data sangat banyak |
| 9 | NIK dan user_id unique di verifikasi | Syamsul | Mencegah duplikasi pendaftaran |
| 10 | `firstOrCreate` untuk Winner | Pais | Mencegah data pemenang ganda untuk lelang yang sama |

---

> **Catatan:** Celah-celah di atas adalah hal yang **wajar** dalam konteks Tugas Besar kelompok, karena masing-masing anggota membangun service secara independen. Dokumen ini bertujuan sebagai bahan **evaluasi dan diskusi** untuk menunjukkan pemahaman mendalam terhadap arsitektur integrasi yang telah dibangun.
