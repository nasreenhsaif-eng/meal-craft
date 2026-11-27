<?php

namespace App\Models;

use Database\Factories\PartnerKitchenDiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerKitchenDiscount extends Model
{
    /** @use HasFactory<PartnerKitchenDiscountFactory> */
    use HasFactory;

    protected $fillable = [
        'label',
        'kind',
        'amount_bhd',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_bhd' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
