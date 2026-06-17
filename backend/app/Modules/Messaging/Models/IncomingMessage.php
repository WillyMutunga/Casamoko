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
        'msisdn',
        'msisdn_hash',
        'message',
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
}
