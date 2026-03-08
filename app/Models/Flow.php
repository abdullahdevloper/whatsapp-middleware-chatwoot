<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    protected $fillable = [
        'tenant_id',
        'flow_key',
        'definition_json',
    ];

    protected $casts = [
        'definition_json' => 'array',
    ];
}
