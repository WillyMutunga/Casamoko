<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class ShortcodeSession extends Model
{
    protected $fillable = [
        'shortcode_id',
        'client_account_id',
        'msisdn_hash',
        'msisdn',
        'webhook_url',
        'current_state',
        'context_data',
        'expires_at'
    ];

    protected $casts = [
        'context_data' => 'array',
        'expires_at' => 'datetime'
    ];

    public function shortcode()
    {
        return $this->belongsTo(Shortcode::class);
    }

    public function clientAccount()
    {
        return $this->belongsTo(\App\Modules\Accounts\Models\ClientAccount::class);
    }
}
