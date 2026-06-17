<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Winner;
use App\Services\SoapAuditService;
use App\Services\AmqpPublisherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Invoices",
    description: "Invoices API"
)]
class InvoiceController extends Controller
{
    use BaseApiResponse;

    #[OA\Get(
        path: "/api/v1/invoices",
        summary: "Daftar invoice",
        description: "Menampilkan daftar invoice dengan pagination. Bisa difilter berdasarkan status.",
        tags: ["Invoices"],
        parameters: [
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Filter berdasarkan status: unpaid, paid, overdue",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["unpaid", "paid", "overdue"])
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Jumlah data per halaman (default: 10)",
                required: false,
                schema: new OA\Schema(type: "integer", default: 10)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Daftar invoice berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Daftar invoice berhasil diambil."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(type: "object")
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized: Bearer token is missing.")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with('winner')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest();

        $perPage = min($request->get('per_page', 10), 100);
        $invoices = $query->paginate($perPage);

        return $this->paginatedResponse($invoices, 'Daftar invoice berhasil diambil.');
    }

    #[OA\Get(
        path: "/api/v1/invoices/{id}",
        summary: "Detail invoice",
        description: "Menampilkan detail invoice berdasarkan ID atau Nomor Invoice beserta pemenang terkait.",
        tags: ["Invoices"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID atau Nomor Invoice",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", example: "INV-2024-000001")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Detail invoice berhasil diambil",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Detail invoice berhasil diambil."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized: Bearer token is missing."),
            new OA\Response(response: 404, description: "Invoice tidak ditemukan.")
        ]
    )]
    public function show($id): JsonResponse
    {
        $invoice = Invoice::with('winner')
            ->where('id', $id)
            ->orWhere('invoice_number', $id)
            ->first();

        if (!$invoice) {
            return $this->notFoundResponse('Invoice');
        }

        return $this->successResponse($invoice, 'Detail invoice berhasil diambil.');
    }

    #[OA\Post(
        path: "/api/v1/invoices",
        summary: "Buat invoice",
        tags: ["Invoices"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: "auction_id",
                        type: "string",
                        example: "AUC-001"
                    ),
                    new OA\Property(
                        property: "use_mock",
                        type: "boolean",
                        example: true
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Invoice created"
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'auction_id' => 'required|string',
            'use_mock'   => 'nullable|boolean'
        ]);

        try {
            $invoiceService = app(\App\Services\InvoiceService::class);
            $invoiceData = $invoiceService->createInvoice($request->auction_id, $request->boolean('use_mock', false));
            $invoice = Invoice::find($invoiceData['id']);

            // Perform synchronous SOAP Audit logging
            $soapService = app(SoapAuditService::class);
            $receiptNumber = $soapService->sendAuditLog('InvoiceCreated', [
                'invoice_number' => $invoice->invoice_number,
                'total_amount'   => $invoice->total_amount,
                'auction_id'     => $invoice->auction_id,
                'item_id'        => $invoice->item_id,
                'bidder_name'    => $invoice->bidder_name,
            ]);

            if ($receiptNumber) {
                $invoice->update(['soap_receipt_number' => $receiptNumber]);
            }

            // Perform asynchronous AMQP Event notification (RabbitMQ proxy)
            $amqpService = app(AmqpPublisherService::class);
            $amqpService->publishEvent('invoice.created', [
                'event' => 'InvoiceCreated',
                'data'  => [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount'   => $invoice->total_amount,
                    'auction_id'     => $invoice->auction_id,
                    'receipt_number' => $receiptNumber,
                ]
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Invoice berhasil dibuat otomatis dari data pemenang lelang.',
                'data'    => $invoice->fresh(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Gagal membuat invoice: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal membuat invoice: ' . $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
        }
    }
}