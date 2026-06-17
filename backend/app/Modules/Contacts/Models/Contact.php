<?php

namespace App\Modules\Contacts\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class Contact extends Model
{
    protected $table = 'contacts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'msisdn',
        'name',
        'metadata',
        'msisdn_hash',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * The "booted" method of the model.
     * Enforces transparent privacy hashing on MSISDN save.
     */
    protected static function booted()
    {
        static::saving(function ($contact) {
            if ($contact->isDirty('msisdn')) {
                $contact->msisdn_hash = self::hashMsisdn($contact->msisdn);
            }
        });
    }

    /**
     * Computes the salted privacy SHA-256 hash for E.164 numbers.
     */
    public static function hashMsisdn(string $msisdn): string
    {
        // Clean number by removing non-digits or plus sign
        $cleanMsisdn = preg_replace('/[^0-9]/', '', $msisdn);
        
        $salt = config('app.key', 'casamoko_production_fallback_salt');
        return hash_hmac('sha256', $cleanMsisdn, $salt);
    }

    /**
     * The tenant account associated with this contact.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    /**
     * The lists containing this contact.
     */
    public function contactLists()
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_members', 'contact_id', 'contact_list_id');
    }
}
