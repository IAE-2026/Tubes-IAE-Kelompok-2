<?php

namespace App\Http\Controllers\Api\V1;

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
    #[OA\Get(
        path: "/api/v1/invoices",
        summary: "Daftar invoice",
        tags: ["Invoices"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success"
            )
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Invoices endpoint works'
        ]);
    }

    #[OA\Get(
        path: "/api/v1/invoices/{id}",
        summary: "Detail invoice",
        tags: ["Invoices"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Invoice ID",
                in: "path",
                required: true
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success"
            )
        ]
    )]
    public function show($id): JsonResponse
    {
        return response()->json([
            'message' => 'Detail invoice',
            'id' => $id
        ]);
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
            'winner_id'  => 'required_without:auction_id|nullable|exists:winners,id',
            'auction_id' => 'required_without:winner_id|nullable|exists:winners,auction_id',
        ]);

        $winner = null;
        if ($request->filled('winner_id')) {
            $winner = Winner::find($request->winner_id);
        } else if ($request->filled('auction_id')) {
            $winner = Winner::where('auction_id', $request->auction_id)->first();
        }

        if (!$winner) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pemenang tidak ditemukan.',
            ], 404);
        }

        if ($winner->status === 'invoiced') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invoice sudah pernah dibuat untuk pemenang ini.',
            ], 400);
        }

        try {
            $invoice = DB::transaction(function () use ($winner, $request) {
                // Calculate invoice amounts
                $subtotal = (float) $winner->winning_bid;
                $taxRate = config('invoice.tax_rate', 0.11);
                $adminFeeRate = config('invoice.admin_fee_rate', 0.02);

                $taxAmount = $subtotal * $taxRate;
                $adminFee = $subtotal * $adminFeeRate;
                $totalAmount = $subtotal + $taxAmount + $adminFee;

                $invoiceNumber = Invoice::generateInvoiceNumber();

                // Create local invoice
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'winner_id'      => $winner->id,
                    'auction_id'     => $winner->auction_id,
                    'item_id'        => $winner->item_id,
                    'bidder_id'      => $winner->bidder_id,
                    'bidder_name'    => $winner->bidder_name ?? 'Warga',
                    'bidder_email'   => $winner->bidder_email ?? 'warga@ktp.iae.id',
                    'item_name'      => $winner->item_name ?? 'Barang Lelang',
                    'subtotal'       => $subtotal,
                    'tax_amount'     => $taxAmount,
                    'admin_fee'      => $adminFee,
                    'total_amount'   => $totalAmount,
                    'status'         => 'unpaid',
                    'issued_at'      => now(),
                    'due_date'       => now()->addDays(config('invoice.due_days', 7)),
                    'notes'          => $request->input('notes'),
                ]);

                // Update winner status
                $winner->update(['status' => 'invoiced']);

                return $invoice;
            });

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
                'message' => 'Invoice berhasil dibuat, diaudit ke sistem SOAP, dan disebarkan ke RabbitMQ.',
                'data'    => $invoice->fresh(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Gagal membuat invoice: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal membuat invoice: ' . $e->getMessage(),
            ], 500);
        }
    }
}