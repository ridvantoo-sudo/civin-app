<?php

namespace App\Features\GameAnalytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RevenueStatistic extends Model
{
    use HasUuids;

    protected $table = 'game_revenue_statistics';

    protected $fillable = [
        'stat_date', 'game_slug', 'source', 'gross_revenue', 'net_revenue',
        'coins_wagered', 'coins_won', 'coins_burned', 'diamonds_consumed',
        'gift_revenue', 'wallet_revenue', 'vip_revenue', 'agency_commission',
        'paying_users', 'arpu', 'arppu', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'gross_revenue' => 'decimal:4',
            'net_revenue' => 'decimal:4',
            'coins_wagered' => 'decimal:4',
            'coins_won' => 'decimal:4',
            'coins_burned' => 'decimal:4',
            'diamonds_consumed' => 'decimal:4',
            'gift_revenue' => 'decimal:4',
            'wallet_revenue' => 'decimal:4',
            'vip_revenue' => 'decimal:4',
            'agency_commission' => 'decimal:4',
            'arpu' => 'decimal:4',
            'arppu' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
