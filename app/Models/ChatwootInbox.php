<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatwootInbox extends Model
{
    protected $fillable = [
        'tenant_id',
        'chatwoot_inbox_id',
        'phone_number',
        'whatsapp_phone_number_id',
        'flow_key',
    ];
}
