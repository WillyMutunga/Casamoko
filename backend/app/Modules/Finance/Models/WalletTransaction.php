<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'amount', // debit (-) or credit (+)
        'balance_after',
        'type', // TOPUP, SMS_DISPATCH, REFUND
        'reference_type',
        'reference_id',
        'description',
        'created_at',
        'reason_code',
        'adjusted_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    /**
     * The tenant account associated with this transaction record.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    /**
     * Polymorphic reference lookup helper (e.g. Campaign, MpesaPayment).
     */
    public function reference()
    {
        return $this->morphTo();
    }
}
