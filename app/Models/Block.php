<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
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

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function inventories()
    {
        return $this->hasMany(BlockInventory::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
