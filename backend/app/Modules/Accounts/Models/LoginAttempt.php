<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $table = 'login_attempts';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ip_address',
        'user_agent',
        'username',
        'status', // SUCCESS, FAILED
        'failure_reason', // INVALID_CREDENTIALS, BRUTE_FORCE, SUSPENDED
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
