<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $table = 'event_log';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'event_uid',
        'event_type',
        'conversation_id',
        'message_uid',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];
}
