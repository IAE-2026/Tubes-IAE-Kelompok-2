<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SoapAuditService;
use App\Services\AmqpPublishService;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class BidController extends Controller
{
    protected SoapAuditService $soapAuditService;
    protected AmqpPublishService $amqpPublishService;

    public function __construct(SoapAuditService $soapAuditService, AmqpPublishService $amqpPublishService)
    {
        $this->soapAuditService = $soapAuditService;
        $this->amqpPublishService = $amqpPublishService;
    }

    #[OA\Get(
        path: "/api/v1/bids",
        summary: "Ambil semua penawaran",
        security: [["ApiKeyAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function index()
    {
        $bids = Bid::all();

        return response()->json([
            'status' => 'success',
            'data' => $bids
        ], 200);
    }

    #[OA\Get(
        path: "/api/v1/bids/{id}",
        summary: "Ambil detail penawaran",
        security: [["ApiKeyAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 404, description: "Not Found")
        ]
    )]
    public function show($id)
    {
        $bid = Bid::find($id);

        if (!$bid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $bid
        ], 200);
    }

    #[OA\Post(
        path: "/api/v1/bids",
        summary: "Kirim penawaran baru",
        security: [["ApiKeyAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["bidder_id", "item_id", "bid_amount"],
                properties: [
                    new OA\Property(property: "bidder_id", type: "string", example: "USER123"),
                    new OA\Property(property: "item_id", type: "string", example: "ITEM001"),
                    new OA\Property(property: "bid_amount", type: "number", example: 500000)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Created"),
            new OA\Response(response: 403, description: "Forbidden")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'bidder_id' => 'required',
            'item_id' => 'required',
            'bid_amount' => 'required|numeric',
        ]);

        $user = $request->user();
        if (!$user || !in_array($user->sso_role, ['bidder', 'admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. Hanya user dengan role bidder atau admin yang boleh membuat penawaran.'
            ], 403);
        }

        if ($user->sso_role !== 'admin' && $request->bidder_id !== $user->sso_id && $request->bidder_id !== $user->email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. bidder_id tidak cocok dengan identitas SSO Anda.'
            ], 403);
        }

        try {
            $verifikasiUrl = env('VERIFIKASI_URL', 'http://service-verifikasi:80/api/v1/verifications/');
            $katalogUrl = env('KATALOG_URL', 'http://service-katalog:80/api/v1/items/');

            // Extract integer from bidder_id to match Syamsul's user_id format
            $verificationUserId = $request->bidder_id;
            if (preg_match('/(\d+)/', $verificationUserId, $matches)) {
                $verificationUserId = $matches[1];
            }

            $userCheck = Http::withHeaders(['X-IAE-KEY' => '102022400117'])->get($verifikasiUrl . $verificationUserId);
            $itemCheck = Http::get($katalogUrl . $request->item_id);

            if (!$userCheck->successful() || !$itemCheck->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi ke service lain gagal'
                ], 403);
            }

            // Celah #2: Validasi status verifikasi user harus VERIFIED
            $userData = $userCheck->json();
            if (($userData['data']['verification_status'] ?? '') !== 'VERIFIED') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User belum terverifikasi'
                ], 403);
            }

            // Celah #3: Validasi status barang lelang harus OPEN
            $itemData = $itemCheck->json();
            if (($itemData['data']['status'] ?? '') !== 'OPEN') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Barang tidak sedang dilelang'
                ], 403);
            }

            // Celah #4: Validasi harga bid vs harga dasar barang
            $basePrice = $itemData['data']['base_price'] ?? 0;
            if ($request->bid_amount < $basePrice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bid harus lebih besar atau sama dengan harga dasar barang (' . $basePrice . ').'
                ], 422);
            }

            // Tambahan: Validasi bid baru harus lebih tinggi dari penawaran tertinggi saat ini
            $highestBid = Bid::where('item_id', $request->item_id)
                ->where('status', 'valid')
                ->max('bid_amount');

            if ($highestBid && $request->bid_amount <= $highestBid) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Jumlah penawaran harus lebih tinggi dari penawaran tertinggi saat ini (' . $highestBid . ').'
                ], 422);
            }

        } catch (\Exception $e) {
            // Celah #1: Jangan biarkan bid lolos jika request eksternal gagal/error
            Log::error('Koneksi ke microservice verifikasi/item gagal: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Service eksternal tidak tersedia. Silakan coba beberapa saat lagi.'
            ], 503);
        }

        $bid = Bid::create([
            'bidder_id' => (string) $user->id, 
            'item_id' => $request->item_id,
            'bid_amount' => $request->bid_amount,
            'status' => 'valid'
        ]);

        $receiptNumber = $this->soapAuditService->logActivity('CreateBid', [
            'bid_id' => $bid->id,
            'bidder_id' => $user->sso_id, 
            'item_id' => $bid->item_id,
            'bid_amount' => (float)$bid->bid_amount,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($receiptNumber) {
            $bid->update(['receipt_number' => $receiptNumber]);
        }

        $eventData = [
            'event' => 'penawaran.created',
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'team_id' => env('TEAM_ID', 'TEAM-02'),
            'data' => [
                'bid_id' => $bid->id,
                'bidder_id' => $user->sso_id, 
                'item_id' => $bid->item_id,
                'bid_amount' => (float)$bid->bid_amount,
                'status' => $bid->status,
                'receipt_number' => $bid->receipt_number ?? $receiptNumber ?? 'N/A'
            ]
        ];

        $this->amqpPublishService->publishEvent('penawaran.created', $eventData);

        return response()->json([
            'status' => 'success',
            'data' => $bid
        ], 201);
    }

    #[OA\Get(
        path: "/api/v1/bids/highest/{auctionId}",
        summary: "Ambil penawaran tertinggi untuk suatu barang",
        security: [["ApiKeyAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "auctionId",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 404, description: "Not Found")
        ]
    )]
    public function highest($auctionId)
    {
        $highestBid = Bid::where('item_id', $auctionId)
            ->where('status', 'valid')
            ->orderBy('bid_amount', 'desc')
            ->first();

        if (!$highestBid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Belum ada penawaran untuk barang ini'
            ], 404);
        }

        // Celah #5: Ambil data bidder asli dari tabel user lokal (yang diisi oleh SSO middleware)
        $bidder = \App\Models\User::find($highestBid->bidder_id);
        $bidderName = $bidder ? $bidder->name : "Bidder " . $highestBid->bidder_id;
        $bidderEmail = $bidder ? $bidder->email : $highestBid->bidder_id . "@ktp.iae.id";

        // Celah #5: Tarik data katalog secara real-time untuk nama barang asli dan base price
        $katalogUrl = env('KATALOG_URL', 'http://service-katalog:80/api/v1/items/');
        $itemName = "Barang " . $highestBid->item_id;
        $startingPrice = (float)$highestBid->bid_amount; // fallback
        $auctionStatus = "ended";
        $auctionEndedAt = now()->toIso8601String();

        try {
            $itemCheck = Http::get($katalogUrl . $highestBid->item_id);
            if ($itemCheck->successful()) {
                $itemData = $itemCheck->json();
                $itemName = $itemData['data']['name'] ?? $itemName;
                $startingPrice = (float)($itemData['data']['base_price'] ?? $startingPrice);
                
                // Celah #6: Bandingkan auction_end_at dengan waktu sekarang untuk status aslinya
                $auctionEndAtStr = $itemData['data']['auction_end_at'] ?? null;
                if ($auctionEndAtStr) {
                    $auctionEndedAt = $auctionEndAtStr;
                    $endTime = \Carbon\Carbon::parse($auctionEndAtStr);
                    $auctionStatus = now()->gt($endTime) ? "ended" : "ongoing";
                }
            }
        } catch (\Exception $e) {
            Log::warning('Gagal mengambil detail item dari katalog service: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'bid_id' => (string)$highestBid->id,
                'auction_id' => $highestBid->item_id,
                'item_id' => $highestBid->item_id,
                'bidder_id' => $highestBid->bidder_id,
                'bidder_name' => $bidderName,
                'bidder_email' => $bidderEmail,
                'item_name' => $itemName,
                'amount' => (float)$highestBid->bid_amount,
                'starting_price' => $startingPrice,
                'auction_status' => $auctionStatus,
                'auction_ended_at' => $auctionEndedAt
            ]
        ], 200);
    }
}