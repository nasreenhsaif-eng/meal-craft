<?php

namespace App\Models;

use Database\Factories\PartnerKitchenPriceCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerKitchenPriceCard extends Model
{
    /** @use HasFactory<PartnerKitchenPriceCardFactory> */
    use HasFactory;

    public const DISCOUNT_TYPE_AMOUNT = 'amount';

    public const DISCOUNT_TYPE_PERCENT = 'percent';

    protected $fillable = [
        'scope',
        'calorie_row',
        'billed_days',
        'amount_bhd',
        'discount_type',
        'discount_bhd',
    ];

    protected function casts(): array
    {
        return [
            'calorie_row' => 'integer',
            'billed_days' => 'integer',
            'amount_bhd' => 'float',
            'discount_bhd' => 'float',
        ];
    }

    public function netAmountBhd(): float
    {
        $amount = (float) $this->amount_bhd;
        $discount = max(0, (float) $this->discount_bhd);

        if ($this->discount_type === self::DISCOUNT_TYPE_PERCENT) {
            $percent = min(100, $discount);

            return round(max(0, $amount * (1 - ($percent / 100))), 3);
        }

        return round(max(0, $amount - $discount), 3);
    }
}
