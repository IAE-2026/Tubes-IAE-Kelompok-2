<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'bidder_id',
        'item_id',
        'bid_amount',
        'status',
        'receipt_number'
    ];
}