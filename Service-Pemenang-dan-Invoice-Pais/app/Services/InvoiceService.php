<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Winner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * InvoiceService
 *
 * Core business logic untuk pembuatan invoice dan penentuan pemenang.
 *
 * Alur Pembuatan Invoice:
 *   1. Terima auction_id dari request
 *   2. Fetch highest bid dari Service B via BiddingService
 *   3. Validasi: lelang sudah berakhir? Sudah ada winner/invoice?
 *   4. Simpan data pemenang ke tabel winners
 *   5. Hitung subtotal, pajak, biaya admin, total
 *   6. Buat invoice dan simpan ke tabel invoices
 *   7. Update status winner menjadi 'invoiced'
 *   8. Return data invoice lengkap
 */
class InvoiceService
{
    // Konfigurasi perhitungan invoice
    const TAX_RATE       = 0.11; // PPN 11%
    const ADMIN_FEE_RATE = 0.02; // Biaya admin 2%

    public function __construct(private BiddingService $biddingService)
    {
    }

    /**
     * Buat invoice baru berdasarkan data dari Service B.
     *
     * @param  string $auctionId
     * @param  bool   $useMock    Gunakan data mock jika Service B tidak tersedia
     * @return array
     * @throws \Exception
     */
    public function createInvoice(string $auctionId, bool $useMock = false): array
    {
        // ----------------------------------------
        // STEP 1: Cek apakah invoice sudah ada
        // ----------------------------------------
        $existingInvoice = Invoice::where('auction_id', $auctionId)->first();
        if ($existingInvoice) {
            throw new \Exception("Invoice untuk auction {$auctionId} sudah ada. Invoice Number: {$existingInvoice->invoice_number}", 409);
        }

        // ----------------------------------------
        // STEP 2: Ambil highest bid dari Service B
        // ----------------------------------------
        $bidData = $useMock
            ? $this->biddingService->getMockHighestBid($auctionId)
            : $this->biddingService->getHighestBid($auctionId);

        if (!$bidData) {
            throw new \Exception("Gagal mengambil data highest bid dari Service B untuk auction: {$auctionId}", 422);
        }

        // ----------------------------------------
        // STEP 3: Validasi status lelang
        // ----------------------------------------
        if (!$useMock && isset($bidData['auction_status']) && $bidData['auction_status'] !== 'ended') {
            throw new \Exception("Lelang {$auctionId} belum berakhir. Invoice hanya bisa dibuat setelah lelang selesai.", 422);
        }

        // ----------------------------------------
        // STEP 4: Simpan/update data pemenang
        // ----------------------------------------
        DB::beginTransaction();
        try {
            $winner = Winner::firstOrCreate(
                ['auction_id' => $auctionId],
                [
                    'item_id'          => $bidData['item_id'],
                    'bidder_id'        => $bidData['bidder_id'],
                    'bidder_name'      => $bidData['bidder_name'] ?? 'Unknown',
                    'bidder_email'     => $bidData['bidder_email'] ?? '',
                    'item_name'        => $bidData['item_name'] ?? 'Unknown Item',
                    'winning_bid'      => $bidData['amount'],
                    'starting_price'   => $bidData['starting_price'] ?? 0,
                    'bid_id'           => $bidData['bid_id'] ?? null,
                    'status'           => 'invoiced',
                    'auction_ended_at' => isset($bidData['auction_ended_at'])
                        ? \Carbon\Carbon::parse($bidData['auction_ended_at'])
                        : now(),
                ]
            );

            // Update status ke invoiced jika winner sudah ada
            if (!$winner->wasRecentlyCreated) {
                $winner->update(['status' => 'invoiced']);
            }

            // ----------------------------------------
            // STEP 5: Hitung biaya invoice
            // ----------------------------------------
            $subtotal  = (float) $bidData['amount'];
            $tax       = round($subtotal * self::TAX_RATE, 2);
            $adminFee  = round($subtotal * self::ADMIN_FEE_RATE, 2);
            $total     = $subtotal + $tax + $adminFee;

            $dueDays   = config('invoice.due_days', 7);

            // ----------------------------------------
            // STEP 6: Buat invoice
            // ----------------------------------------
            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'winner_id'      => $winner->id,
                'auction_id'     => $auctionId,
                'item_id'        => $bidData['item_id'],
                'bidder_id'      => $bidData['bidder_id'],
                'bidder_name'    => $bidData['bidder_name'] ?? 'Unknown',
                'bidder_email'   => $bidData['bidder_email'] ?? '',
                'item_name'      => $bidData['item_name'] ?? 'Unknown Item',
                'subtotal'       => $subtotal,
                'tax_amount'     => $tax,
                'admin_fee'      => $adminFee,
                'total_amount'   => $total,
                'status'         => 'unpaid',
                'issued_at'      => now(),
                'due_date'       => now()->addDays($dueDays),
                'notes'          => "Invoice otomatis untuk pemenang lelang {$auctionId}",
            ]);

            DB::commit();

            Log::info("[InvoiceService] Invoice created successfully", [
                'invoice_number' => $invoice->invoice_number,
                'auction_id'     => $auctionId,
                'winner_id'      => $winner->id,
                'total'          => $total,
            ]);

            return $invoice->load('winner')->toArray();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[InvoiceService] Failed to create invoice", [
                'auction_id' => $auctionId,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Hitung breakdown biaya invoice.
     */
    public function calculateFees(float $amount): array
    {
        $tax      = round($amount * self::TAX_RATE, 2);
        $adminFee = round($amount * self::ADMIN_FEE_RATE, 2);
        $total    = $amount + $tax + $adminFee;

        return [
            'subtotal'   => $amount,
            'tax'        => $tax,
            'tax_rate'   => (self::TAX_RATE * 100) . '%',
            'admin_fee'  => $adminFee,
            'admin_rate' => (self::ADMIN_FEE_RATE * 100) . '%',
            'total'      => $total,
        ];
    }
}
