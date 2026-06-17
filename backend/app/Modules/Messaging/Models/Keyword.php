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
}
