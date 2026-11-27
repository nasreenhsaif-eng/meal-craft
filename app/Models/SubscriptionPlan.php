<?php

namespace App\Models;

use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'slug',
        'name',
        'meal_structure',
        'min_calories',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meal_structure' => 'array',
            'min_calories' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PlanCaloriePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanCaloriePrice::class, 'plan_id');
    }

    /**
     * @return list<int>
     */
    public function calorieTiers(): array
    {
        $tiers = $this->prices()
            ->distinct()
            ->orderBy('calorie_tier')
            ->pluck('calorie_tier')
            ->map(fn ($tier): int => (int) $tier)
            ->all();

        return $tiers !== [] ? array_values($tiers) : [];
    }
}
