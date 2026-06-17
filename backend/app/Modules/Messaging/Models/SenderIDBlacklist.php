<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class SenderIDBlacklist extends Model
{
    protected $table = 'sender_id_blacklists';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pattern',
        'reason',
    ];
}
