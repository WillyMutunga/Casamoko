<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Model;

class ResellerAccount extends Model
{
    protected $table = 'reseller_accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'markup_percentage',
        'api_credits',
        'billing_config',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'markup_percentage' => 'decimal:2',
        'api_credits' => 'decimal:4',
        'billing_config' => 'array',
    ];

    /**
     * Get all client accounts managed by this reseller.
     */
    public function clientAccounts()
    {
        return $this->hasMany(ClientAccount::class, 'reseller_account_id');
    }
}
