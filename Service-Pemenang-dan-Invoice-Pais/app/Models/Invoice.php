<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'winner_id',
        'auction_id',
        'item_id',
        'bidder_id',
        'bidder_name',
        'bidder_email',
        'item_name',
        'subtotal',
        'tax_amount',
        'admin_fee',
        'total_amount',
        'status',
        'soap_receipt_number',
        'issued_at',
        'due_date',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'subtotal'      => 'float',
        'tax_amount'    => 'float',
        'admin_fee'     => 'float',
        'total_amount'  => 'float',
        'issued_at'     => 'datetime',
        'due_date'      => 'datetime',
        'paid_at'       => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];


    public function winner(): BelongsTo
    {
        return $this->belongsTo(Winner::class, 'winner_id');
    }


    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'unpaid' && now()->isAfter($this->due_date);
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }


    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'unpaid')
                     ->where('due_date', '<', now());
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix  = config('invoice.prefix', 'INV');
        $year    = date('Y');
        $lastInv = self::whereYear('created_at', $year)->count() + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $lastInv);
    }
}
