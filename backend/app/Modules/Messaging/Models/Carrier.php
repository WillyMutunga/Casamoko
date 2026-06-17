<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    protected $table = 'carriers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'binding_details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'binding_details' => 'array',
    ];

    /**
     * Get routes provided by this operator.
     */
    public function routes()
    {
        return $this->hasMany(Route::class, 'carrier_id');
    }
}
