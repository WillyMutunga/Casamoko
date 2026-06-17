<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class SenderIDMnoApproval extends Model
{
    protected $table = 'sender_id_mno_approvals';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sender_id_id',
        'mno_name',
        'status', // PENDING, APPROVED, REJECTED
        'reason',
    ];

    /**
     * Get the parent Sender ID record.
     */
    public function senderId()
    {
        return $this->belongsTo(SenderID::class, 'sender_id_id');
    }
}
