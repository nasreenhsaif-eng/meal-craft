<?php

namespace App\Models;

use Database\Factories\PlanCaloriePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanCaloriePrice extends Model
{
    /** @use HasFactory<PlanCaloriePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'calorie_tier',
        'days_count',
        'base_total_price',
        'discounted_total_price',
        'daily_rate',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calorie_tier' => 'integer',
            'days_count' => 'integer',
            'base_total_price' => 'integer',
            'discounted_total_price' => 'integer',
            'daily_rate' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function effectiveTotalPrice(): int
    {
        if ($this->discounted_total_price !== null && $this->discounted_total_price < $this->base_total_price) {
            return (int) $this->discounted_total_price;
        }

        return (int) $this->base_total_price;
    }

    public function syncDailyRate(): void
    {
        $days = max(1, (int) $this->days_count);
        $this->daily_rate = (int) round($this->effectiveTotalPrice() / $days);
    }

    public function applyDiscount(string $type, int $value): void
    {
        $base = (int) $this->base_total_price;
        $value = max(0, $value);

        if ($type === 'percent') {
            $sale = (int) round($base * (1 - (min(100, $value) / 100)));
        } else {
            $sale = max(0, $base - $value);
        }

        $this->discounted_total_price = $sale < $base ? $sale : null;
        $this->syncDailyRate();
        $this->save();
    }

    public function clearDiscount(): void
    {
        $this->discounted_total_price = null;
        $this->syncDailyRate();
        $this->save();
    }
}
