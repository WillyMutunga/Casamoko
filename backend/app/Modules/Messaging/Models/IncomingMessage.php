<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingMessage extends Model
{
    protected $table = 'incoming_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'shortcode_id',
        'keyword_id',
        'client_account_id',
        'msisdn',
        'msisdn_hash',
        'message',
        'is_read',
        'link_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * Get target virtual number.
     */
    public function shortcode()
    {
        return $this->belongsTo(Shortcode::class, 'shortcode_id');
    }

    /**
     * Get parent keyword matched if one was triggered.
     */
    public function keyword()
    {
        return $this->belongsTo(Keyword::class, 'keyword_id');
    }

    /**
     * Get the client account this message was attributed to.
     */
    public function clientAccount()
    {
        return $this->belongsTo(\App\Modules\Accounts\Models\ClientAccount::class, 'client_account_id');
    }
}
