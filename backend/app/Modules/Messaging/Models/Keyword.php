<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class Keyword extends Model
{
    protected $table = 'keywords';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'shortcode_id',
        'client_account_id',
        'keyword',
        'callback_webhook',
        'action_type', // OPT_IN, OPT_OUT, WEBHOOK
        'reply_message',
    ];

    /**
     * Get parent virtual shortcode number.
     */
    public function shortcode()
    {
        return $this->belongsTo(Shortcode::class, 'shortcode_id');
    }

    /**
     * Get the client account this keyword belongs to.
     */
    public function clientAccount()
    {
        return $this->belongsTo(\App\Modules\Accounts\Models\ClientAccount::class, 'client_account_id');
    }
}
