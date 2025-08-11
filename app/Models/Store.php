<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'location',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'gst_number',
        'pan_number',
        'default_tax_rate',
        'phone',
        'email',
        'status',
        'settings',
    ];

    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'settings' => 'array',
    ];
}
