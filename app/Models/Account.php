<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Account extends Model
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $fillable = ['code', 'name', 'type', 'normal_balance', 'parent_id', 'is_postable', 'meta', 'is_active'];

    public function postings()
    {
        return $this->hasMany(JournalPosting::class);
    }

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }
}
