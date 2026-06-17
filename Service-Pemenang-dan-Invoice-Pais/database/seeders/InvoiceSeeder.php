<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Winner;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    // Tarif PPN dan admin
    const TAX_RATE       = 0.11;
    const ADMIN_FEE_RATE = 0.02;

    public function run(): void
    {
        $this->command->info('Seeding invoices...');

        // Ambil winners yang sudah invoiced/paid
        $invoicedWinners = Winner::whereIn('status', ['invoiced', 'paid'])->get();

        $counter = 1;
        foreach ($invoicedWinners as $winner) {
            // Skip jika sudah ada invoice
            if (Invoice::where('winner_id', $winner->id)->exists()) {
                continue;
            }

            $subtotal  = $winner->winning_bid;
            $tax       = round($subtotal * self::TAX_RATE, 2);
            $adminFee  = round($subtotal * self::ADMIN_FEE_RATE, 2);
            $total     = $subtotal + $tax + $adminFee;

            $issuedAt  = $winner->auction_ended_at ?? Carbon::now()->subDays(rand(1, 7));
            $dueDate   = $issuedAt->copy()->addDays(7);
            $isPaid    = $winner->status === 'paid';

            Invoice::create([
                'invoice_number' => sprintf('INV-%s-%06d', date('Y'), $counter),
                'winner_id'      => $winner->id,
                'auction_id'     => $winner->auction_id,
                'item_id'        => $winner->item_id,
                'bidder_id'      => $winner->bidder_id,
                'bidder_name'    => $winner->bidder_name,
                'bidder_email'   => $winner->bidder_email,
                'item_name'      => $winner->item_name,
                'subtotal'       => $subtotal,
                'tax_amount'     => $tax,
                'admin_fee'      => $adminFee,
                'total_amount'   => $total,
                'status'         => $isPaid ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'unpaid'),
                'issued_at'      => $issuedAt,
                'due_date'       => $dueDate,
                'paid_at'        => $isPaid ? $issuedAt->copy()->addDays(rand(1, 5)) : null,
                'notes'          => "Invoice otomatis untuk pemenang lelang {$winner->auction_id}",
            ]);

            $counter++;
        }

        $this->command->info('✅ Invoices seeded: ' . ($counter - 1) . ' records.');
    }
}
