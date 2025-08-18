<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'floor_id',
        'name',
        'code',
        'zone',
        'type',
        'capacity',
        'description',
        'status',
        'settings',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'settings' => 'array',
    ];

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function inventories()
    {
        return $this->hasMany(BlockInventory::class);
    }
}
