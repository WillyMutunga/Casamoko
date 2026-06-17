<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class MpesaPayment extends Model
{
    protected $table = 'mpesa_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'merchant_request_id',
        'checkout_request_id',
        'mpesa_receipt_number',
        'amount',
        'phone_number',
        'status', // PENDING, SUCCESS, FAILED
        'response_metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:4',
        'response_metadata' => 'array',
    ];

    /**
     * The tenant account associated with this M-Pesa transaction.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }
}
