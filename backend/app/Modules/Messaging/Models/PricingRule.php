<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class PricingRule extends Model
{
    protected $table = 'pricing_rules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'destination_prefix',
        'base_cost',
        'reseller_markup',
        'client_account_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_cost' => 'decimal:4',
        'reseller_markup' => 'decimal:4',
    ];

    /**
     * Get client account this specific rule targets.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }
}
