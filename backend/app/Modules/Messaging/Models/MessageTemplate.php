<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class MessageTemplate extends Model
{
    protected $fillable = [
        'client_account_id',
        'name',
        'content',
    ];

    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }
}
