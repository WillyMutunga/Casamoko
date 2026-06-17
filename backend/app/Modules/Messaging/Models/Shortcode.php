<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Accounts\Models\ClientAccount;

class Shortcode extends Model
{
    protected $table = 'shortcodes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_account_id',
        'shortcode',
        'is_dedicated',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_dedicated' => 'boolean',
    ];

    /**
     * Get owner account.
     */
    public function clientAccount()
    {
        return $this->belongsTo(ClientAccount::class, 'client_account_id');
    }

    /**
     * Get keywords registered on this shortcode.
     */
    public function keywords()
    {
        return $this->hasMany(Keyword::class, 'shortcode_id');
    }
}
