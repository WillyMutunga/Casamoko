<?php

namespace App\Modules\Contacts\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class ContactList extends Model
{
    protected $table = 'contact_lists';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'name',
        'description',
        'tags',
        'subscription_source',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['contact_count'];

    /**
     * The tenant account associated with this contact group list.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    /**
     * The contacts populated in this list.
     */
    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_list_members', 'contact_list_id', 'contact_id');
    }

    /**
     * Get the dynamic count of contacts in this list.
     */
    public function getContactCountAttribute()
    {
        return \Illuminate\Support\Facades\DB::table('contact_list_members')
            ->where('contact_list_id', $this->id)
            ->count();
    }
}
