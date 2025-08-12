<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'parent_id',
        'ticket_number',
        'user_id',
        'assigned_to',
        'subject',
        'message',
        'priority',
        'status',
    ];

    // Parent ticket (null for root tickets)
    public function parent()
    {
        return $this->belongsTo(Ticket::class, 'parent_id');
    }

    // Replies (children tickets)
    public function replies()
    {
        return $this->hasMany(Ticket::class, 'parent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scope to get only root tickets (no parent)
    public function scopeRootTickets($query)
    {
        return $query->whereNull('parent_id');
    }
}
