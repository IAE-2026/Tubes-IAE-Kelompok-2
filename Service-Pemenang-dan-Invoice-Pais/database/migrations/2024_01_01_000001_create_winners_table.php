<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel ini menyimpan data pemenang lelang.
     * Dipopulasi ketika sistem menentukan highest bid setelah lelang berakhir.
     */
    public function up(): void
    {
        Schema::create('winners', function (Blueprint $table) {
            $table->id();
            $table->string('auction_id')->comment('ID lelang dari Service B');
            $table->string('item_id')->comment('ID barang dari Service A');
            $table->string('bidder_id')->comment('ID bidder dari Service D');
            $table->string('bidder_name')->nullable()->comment('Nama bidder (cached dari Service D)');
            $table->string('bidder_email')->nullable()->comment('Email bidder (cached dari Service D)');
            $table->string('item_name')->nullable()->comment('Nama barang (cached dari Service A)');
            $table->decimal('winning_bid', 15, 2)->comment('Nilai bid tertinggi yang menang');
            $table->decimal('starting_price', 15, 2)->nullable()->comment('Harga awal lelang');
            $table->string('bid_id')->nullable()->comment('ID bid dari Service B');
            $table->enum('status', ['pending', 'invoiced', 'paid', 'cancelled'])
                  ->default('pending')
                  ->comment('Status pemenang: pending=belum diinvoice, invoiced=sudah ada invoice');
            $table->timestamp('auction_ended_at')->nullable()->comment('Waktu lelang berakhir');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('auction_id');
            $table->index('bidder_id');
            $table->index('status');
            $table->unique('auction_id', 'unique_winner_per_auction'); // 1 pemenang per lelang
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('winners');
    }
};
