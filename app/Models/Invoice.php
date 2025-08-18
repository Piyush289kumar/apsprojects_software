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

    /**
     * Polymorphic relation for billable (customer, vendor, etc.)
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Invoice has many items
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * User who created the invoice
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Boot method to auto set created_by and invoice_number
     */
    protected static function booted()
    {
        static::creating(function ($invoice) {
            // Set created_by automatically
            if (empty($invoice->created_by)) {
                $invoice->created_by = auth()->id();
            }

            // Generate unique invoice number if not already set
            if (empty($invoice->invoice_number)) {
                $prefix = 'INV-';
                $datePart = date('Ymd');

                // Get latest invoice for today
                $lastInvoice = static::whereDate('created_at', now()->toDateString())
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNumber = 1;

                if ($lastInvoice && preg_match('/INV-\d{8}-(\d+)/', $lastInvoice->invoice_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                }

                $invoice->invoice_number = $prefix . $datePart . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
