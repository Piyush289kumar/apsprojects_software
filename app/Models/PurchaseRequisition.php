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
}
