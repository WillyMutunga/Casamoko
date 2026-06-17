<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class Campaign extends Model
{
    protected $table = 'campaigns';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'sender_id_id',
        'name',
        'template',
        'opt_out_message',
        'unicode_type', // GSM-7, UCS-2
        'sent_count',
        'delivered_count',
        'failed_count',
        'status', // DRAFT, SCHEDULED, PROCESSING, COMPLETED, PAUSED
        'scheduled_at',
        'tps_limit',
        'quiet_hours_start',
        'quiet_hours_end',
        'recurring_type',
        'approval_status',
        'is_ab_test',
        'template_b',
        'ab_split_ratio',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /**
     * Get client that launched this campaign.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    /**
     * Get alphabetic identity of campaign sender mask.
     */
    public function senderId()
    {
        return $this->belongsTo(SenderID::class, 'sender_id_id');
    }

    /**
     * Get child message records.
     */
    public function messageRecords()
    {
        return $this->hasMany(MessageRecord::class, 'campaign_id');
    }
}
