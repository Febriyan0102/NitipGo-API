<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HelpTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'customer_id', 'subject', 'description',
        'category', 'priority', 'status', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function replies()
    {
        return $this->hasMany(HelpTicketReply::class, 'ticket_id');
    }
}