<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'billable_id',
        'billable_type',
        'type',
        'invoice_date',
        'due_date',
        'place_of_supply',
        'taxable_value',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_tax',
        'discount',
        'total_amount',
        'status',
        'notes',
        'created_by',
    ];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->created_by)) {
                $invoice->created_by = auth()->id();
            }
        });
    }
    
}
