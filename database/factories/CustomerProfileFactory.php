<?php

namespace Database\Factories;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerGoal;
use App\Enums\CustomerSex;
use App\Enums\DietType;
use App\Enums\MacroSplitStyle;
use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\IngredientAllergenCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    protected $model = CustomerProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $style = MacroSplitStyle::Balanced;
        $percentages = $style->percentages();

        return [
            'user_id' => User::factory()->customer(),
            'onboarding_step' => OnboardingStep::Review,
            'weight_kg' => 72.5,
            'target_weight_kg' => 68.0,
            'height_cm' => 175.0,
            'age' => 34,
            'sex' => CustomerSex::Female,
            'activity_level' => CustomerActivityLevel::Moderate,
            'goal' => CustomerGoal::Maintain,
            'diet_type' => DietType::Balanced,
            'macro_split_style' => $style,
            'daily_calorie_target' => 2000,
            'protein_percentage' => $percentages['protein_percentage'],
            'carb_percentage' => $percentages['carb_percentage'],
            'fat_percentage' => $percentages['fat_percentage'],
            'allergies' => [IngredientAllergenCatalog::PEANUTS],
            'dislikes' => ['cilantro'],
            'onboarding_completed_at' => now(),
        ];
    }

    public function withoutOnboarding(): static
    {
        return $this->state(fn (): array => [
            'onboarding_step' => OnboardingStep::Gender,
            'onboarding_completed_at' => null,
            'weight_kg' => null,
            'height_cm' => null,
            'age' => null,
            'date_of_birth' => null,
            'sex' => null,
            'activity_level' => null,
            'goal' => null,
            'diet_type' => null,
            'target_weight_kg' => null,
            'allergies' => null,
            'dislikes' => null,
            'daily_calorie_target' => null,
            'protein_percentage' => null,
            'carb_percentage' => null,
            'fat_percentage' => null,
        ]);
    }
}
