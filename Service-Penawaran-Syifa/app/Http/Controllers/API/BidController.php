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

            $userCheck = Http::withHeaders(['X-IAE-KEY' => '102022400117'])->get($verifikasiUrl . $request->bidder_id);
            $itemCheck = Http::get($katalogUrl . $request->item_id);

            if (!$userCheck->successful() || !$itemCheck->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi ke service lain gagal'
                ], 403);
            }
        } catch (\Exception $e) {
            Log::warning('Koneksi ke microservice verifikasi/item gagal, melanjutkan pemrosesan: ' . $e->getMessage());
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
}