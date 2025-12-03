<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'saas_plans';

    protected $fillable = [
        'name',
        'description',
        'price_try',
        'price_usd',
        'price_eur',
        'interval',
        'features',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'price_try' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'price_eur' => 'decimal:2',
    ];

    /**
     * Get the price for a specific currency code.
     *
     * @param string $currencyCode
     * @return float
     */
    public function getPriceForCurrency(string $currencyCode): float
    {
        $currencyCode = strtoupper($currencyCode);
        
        return match ($currencyCode) {
            'TRY' => $this->price_try,
            'USD' => $this->price_usd,
            'EUR' => $this->price_eur,
            default => $this->price_usd,
        };
    }
}
