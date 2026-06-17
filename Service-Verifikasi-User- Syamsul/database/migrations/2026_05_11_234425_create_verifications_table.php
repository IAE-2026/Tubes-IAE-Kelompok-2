<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table-> unsignedBigInteger('user_id')->unique();

            $table->string('nik',16)->unique();
            $table->string('bank_account_number');

            $table->enum('verification_status',['NOT_VERIFIED','VERIFIED'])->default('NOT_VERIFIED');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
