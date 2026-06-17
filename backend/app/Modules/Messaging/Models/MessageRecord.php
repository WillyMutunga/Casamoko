<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Contacts\Models\Contact;

class MessageRecord extends Model
{
    protected $table = 'message_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'campaign_id',
        'contact_id',
        'msisdn_hash',
        'route_id',
        'price',
        'status', // QUEUED, SENT, DELIVERED, FAILED
        'mno_message_id',
        'network_status_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:4',
    ];

    /**
     * Get parent campaign if one exists.
     */
    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Get target recipient contact profile.
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Get route SMS was sent on.
     */
    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
    }
}
