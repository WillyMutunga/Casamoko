<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'client_account_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the User that performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the ClientAccount associated with the action.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    /**
     * Helper method to write an immutable log entry.
     */
    public static function log(string $action, ?string $description = null): self
    {
        $user = auth()->user();
        return self::create([
            'user_id' => $user ? $user->id : null,
            'client_account_id' => $user ? $user->client_account_id : null,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
