<?php

namespace App\Models;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerContactPreference;
use App\Enums\CustomerDeliveryTime;
use App\Enums\CustomerGoal;
use App\Enums\CustomerPlanType;
use App\Enums\CustomerSex;
use App\Enums\DietType;
use App\Enums\MacroSplitStyle;
use App\Enums\OnboardingStep;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\CustomerIntake;
use Database\Factories\CustomerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerProfile extends Model
{
    /** @use HasFactory<CustomerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'onboarding_step',
        'first_name',
        'last_name',
        'phone',
        'phone_verified_at',
        'contact_preference',
        'weight_kg',
        'target_weight_kg',
        'height_cm',
        'age',
        'date_of_birth',
        'birthdate',
        'sex',
        'gender',
        'activity_level',
        'goal',
        'diet_type',
        'diet_protocol',
        'plan_type',
        'plan_days',
        'macro_split_style',
        'daily_calorie_target',
        'protein_percentage',
        'carb_percentage',
        'fat_percentage',
        'logged_periods',
        'average_cycle_length',
        'period_tracking_data',
        'allergies',
        'food_filters',
        'dislikes',
        'delivery_time',
        'area',
        'block',
        'road',
        'house_number',
        'gate_flat_number',
        'country',
        'planned_start_date',
        'follow_instagram',
        'customer_question',
        'uncalculated_plan',
        'unique_code',
        'intake_submission_id',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'target_weight_kg' => 'float',
            'height_cm' => 'float',
            'age' => 'integer',
            'date_of_birth' => 'date',
            'birthdate' => 'date',
            'sex' => CustomerSex::class,
            'period_tracking_data' => 'array',
            'food_filters' => 'array',
            'activity_level' => CustomerActivityLevel::class,
            'goal' => CustomerGoal::class,
            'diet_type' => DietType::class,
            'plan_type' => CustomerPlanType::class,
            'plan_days' => 'integer',
            'macro_split_style' => MacroSplitStyle::class,
            'daily_calorie_target' => 'integer',
            'protein_percentage' => 'float',
            'carb_percentage' => 'float',
            'fat_percentage' => 'float',
            'allergies' => 'array',
            'dislikes' => 'array',
            'logged_periods' => 'array',
            'average_cycle_length' => 'integer',
            'onboarding_step' => OnboardingStep::class,
            'onboarding_completed_at' => 'datetime',
            'contact_preference' => CustomerContactPreference::class,
            'phone_verified_at' => 'datetime',
            'delivery_time' => CustomerDeliveryTime::class,
            'planned_start_date' => 'date',
            'follow_instagram' => 'boolean',
            'uncalculated_plan' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerProfile $profile): void {
            if ($profile->unique_code === null || $profile->unique_code === '') {
                $profile->unique_code = CustomerIntake::generateUniqueCode();
            }
        });

        static::saving(function (CustomerProfile $profile): void {
            if ($profile->sex !== null) {
                $profile->gender = $profile->sex->value;
            }

            if ($profile->date_of_birth !== null) {
                $profile->birthdate = $profile->date_of_birth;
            }

            if ($profile->allergies !== null) {
                $profile->food_filters = $profile->allergies;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function craftPlans(): HasMany
    {
        return $this->hasMany(CustomerCraftPlan::class);
    }

    /**
     * @return array{protein_g: float, carbs_g: float, fat_g: float}
     */
    public function dailyMacroGramsFromPercentages(): array
    {
        return UserPlanCalculator::macroGramsFromCaloriesAndPercentages(
            (float) $this->daily_calorie_target,
            (float) $this->protein_percentage,
            (float) $this->carb_percentage,
            (float) $this->fat_percentage,
        );
    }
}
