<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        // 'role', // optional: leave it if used for non-auth purposes, but don't use it for auth
        'store_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // REMOVE these to avoid shadowing Spatie methods:
    // public function hasRole(string $role): bool { ... }
    // public function isStoreManager(): bool { ... }
    // public function isAdmin(): bool { ... }

    // If you want convenience accessors, delegate to the trait:
    public function isStoreManager(): bool
    {
        return $this->hasRole('manager'); // uses Spatie's hasRole
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin'); // or 'admin' if that’s your role name
    }
}
