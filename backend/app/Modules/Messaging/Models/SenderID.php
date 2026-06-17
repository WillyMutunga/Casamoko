<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class SenderID extends Model
{
    protected $table = 'sender_ids';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'sender_id',
        'status', // PENDING, APPROVED, REJECTED
        'fallback_sender_id_id',
    ];

    /**
     * Get the client account that owns this sender identity.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    /**
     * Get MNO-specific approval registry matrix.
     */
    public function mnoApprovals()
    {
        return $this->hasMany(SenderIDMnoApproval::class, 'sender_id_id');
    }

    /**
     * Get the designated fallback Sender ID.
     */
    public function fallbackSender()
    {
        return $this->belongsTo(SenderID::class, 'fallback_sender_id_id');
    }
}
