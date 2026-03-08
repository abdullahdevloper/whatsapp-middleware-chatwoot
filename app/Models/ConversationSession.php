<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationSession extends Model
{
    protected $table = 'conversation_sessions';

    protected $fillable = [
        'tenant_id',
        'chatwoot_conversation_id',
        'inbox_id',
        'chatwoot_contact_id',
        'state_key',
        'state_payload',
        'status',
        'paused_reason',
        'paused_at',
        'expires_at',
        'expired_notified_at',
    ];

    protected $casts = [
        'state_payload' => 'array',
        'paused_at' => 'datetime',
        'expires_at' => 'datetime',
        'expired_notified_at' => 'datetime',
    ];
}
