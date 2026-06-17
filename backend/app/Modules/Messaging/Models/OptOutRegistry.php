<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class OptOutRegistry extends Model
{
    protected $table = 'opt_out_registries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'msisdn',
        'msisdn_hash',
    ];

    /**
     * The booted lifecycle hook.
     */
    protected static function booted()
    {
        static::saving(function ($registry) {
            if ($registry->isDirty('msisdn')) {
                $registry->msisdn_hash = self::hashMsisdn($registry->msisdn);
            }
        });
    }

    /**
     * Computes the salted privacy SHA-256 hash.
     */
    public static function hashMsisdn(string $msisdn): string
    {
        $cleanMsisdn = preg_replace('/[^0-9]/', '', $msisdn);
        $salt = config('app.key', 'casamoko_production_fallback_salt');
        return hash_hmac('sha256', $cleanMsisdn, $salt);
    }

    /**
     * Get the client account that owns this registry entry.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }
}
