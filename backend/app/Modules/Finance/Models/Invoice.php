<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class Invoice extends Model
{
    protected $table = 'invoices';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'invoice_number',
        'amount',
        'status', // PAID, UNPAID, CANCELLED
        'billing_period_start',
        'billing_period_end',
        'breakdown_metadata',
        'download_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:4',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'breakdown_metadata' => 'array',
    ];

    /**
     * The tenant account associated with this invoice.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }
}
