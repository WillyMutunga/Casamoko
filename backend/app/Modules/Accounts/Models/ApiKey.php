<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_account_id',
        'user_id',
        'name',
        'api_key',
        'last_used_at',
        'expires_at'
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
