<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAccount extends Model
{
    protected $table = 'client_accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reseller_account_id',
        'name',
        'phone_number',
        'status', // PENDING, APPROVED, SUSPENDED
        'wallet_balance',
        'credit_limit',
        'cost_per_sms',
        'default_sender_id',
        'low_balance_threshold',
        'kyc_metadata',
        'billing_type', // PREPAID, POSTPAID
        'currency', // KES, USD
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'wallet_balance' => 'decimal:4',
        'credit_limit' => 'decimal:4',
        'cost_per_sms' => 'decimal:4',
        'low_balance_threshold' => 'decimal:4',
        'kyc_metadata' => 'array',
    ];

    /**
     * Get the reseller account associated with this client account.
     */
    public function resellerAccount()
    {
        return $this->belongsTo(ResellerAccount::class, 'reseller_account_id');
    }

    /**
     * Get the users linked to this client account.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'client_account_id');
    }

    /**
     * Helper to verify if the account has enough funds for a debit.
     */
    public function hasSufficientFunds(float $amount): bool
    {
        return ($this->wallet_balance + $this->credit_limit) >= $amount;
    }
}
