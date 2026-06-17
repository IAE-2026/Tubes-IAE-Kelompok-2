<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel invoice menyimpan tagihan kepada pemenang lelang.
     * Setiap pemenang mendapat satu invoice.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique()->comment('Nomor invoice unik, format: INV-YYYY-XXXXXX');
            $table->foreignId('winner_id')->constrained('winners')->onDelete('cascade');
            $table->string('auction_id')->comment('ID lelang dari Service B');
            $table->string('item_id')->comment('ID barang dari Service A');
            $table->string('bidder_id')->comment('ID bidder dari Service D');
            $table->string('bidder_name')->comment('Nama lengkap bidder');
            $table->string('bidder_email')->comment('Email bidder untuk pengiriman invoice');
            $table->string('item_name')->comment('Nama barang yang dilelang');
            $table->decimal('subtotal', 15, 2)->comment('Nilai bid pemenang (harga barang)');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('Pajak (default 11% PPN)');
            $table->decimal('admin_fee', 15, 2)->default(0)->comment('Biaya administrasi (default 2%)');
            $table->decimal('total_amount', 15, 2)->comment('Total yang harus dibayar');

            $table->enum('status', ['unpaid', 'paid', 'overdue', 'cancelled'])
                  ->default('unpaid')
                  ->comment('Status pembayaran invoice');

            $table->dateTime('issued_at')->comment('Tanggal invoice diterbitkan');
            $table->dateTime('due_date')->comment('Batas waktu pembayaran');

            $table->dateTime('paid_at')->nullable()->comment('Tanggal lunas');

            $table->text('notes')->nullable()->comment('Catatan tambahan');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('invoice_number');
            $table->index('bidder_id');
            $table->index('auction_id');
            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};