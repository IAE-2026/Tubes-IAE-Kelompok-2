<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Winner extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'winners';

    protected $fillable = [
        'auction_id',
        'item_id',
        'bidder_id',
        'bidder_name',
        'bidder_email',
        'item_name',
        'winning_bid',
        'starting_price',
        'bid_id',
        'status',
        'auction_ended_at',
    ];

    protected $casts = [
        'winning_bid'      => 'float',
        'starting_price'   => 'float',
        'auction_ended_at' => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];


    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'winner_id');
    }


    public function getHasInvoiceAttribute(): bool
    {
        return $this->invoice()->exists();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInvoiced($query)
    {
        return $query->where('status', 'invoiced');
    }
}
