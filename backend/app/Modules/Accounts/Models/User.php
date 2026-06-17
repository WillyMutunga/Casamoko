<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'reseller_account_id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'role_tier', // SUPER_ADMIN, RESELLER, CLIENT
        'sub_role',  // CLIENT_ADMIN, CAMPAIGN_MANAGER, ANALYST, FINANCE_OFFICER
        'two_factor_secret',
        'two_factor_confirmed_at',
        'password_changed_at',
        'password_expires_at',
        'is_active_user',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'password_expires_at' => 'datetime',
        'is_active_user' => 'boolean',
    ];

    /**
     * Get the ClientAccount that owns this user.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    /**
     * Get the ResellerAccount that owns this user.
     */
    public function resellerAccount()
    {
        return $this->belongsTo(ResellerAccount::class, 'reseller_account_id');
    }

    /**
     * Check if user is system administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role_tier === 'SUPER_ADMIN';
    }

    /**
     * Check if user belongs to reseller channel.
     */
    public function isReseller(): bool
    {
        return $this->role_tier === 'RESELLER';
    }

    /**
     * Check if user is client.
     */
    public function isClient(): bool
    {
        return $this->role_tier === 'CLIENT';
    }
}
