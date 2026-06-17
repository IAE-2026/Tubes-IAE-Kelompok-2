<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Verification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nik',
        'bank_account_number',
        'verification_status',
        'receipt_number',
    ];
}
