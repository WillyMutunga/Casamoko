<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $table = 'routes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'carrier_id',
        'cost_per_sms',
        'tps_limit',
        'priority',
        'is_active',
        'destination_network',
        'bind_type',
        'window_size',
        'use_tls',
        'delivery_rate_score',
        'latency_score',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cost_per_sms' => 'decimal:4',
        'is_active' => 'boolean',
        'window_size' => 'integer',
        'use_tls' => 'boolean',
        'delivery_rate_score' => 'decimal:2',
        'latency_score' => 'integer',
    ];

    /**
     * Get carrier supplying this route.
     */
    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }
}
