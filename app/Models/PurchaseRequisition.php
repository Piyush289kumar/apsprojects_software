<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'requested_by',
        'reference',
        'notes',
        'status',
        'requisition_pdf',
        'priority',
        'approved_by',
        'approved_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'approved_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequisitionItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
