<?php

namespace App\Support;

use App\Enums\MacroSplitStyle;
use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;

/**
 * Gives staff a minimal customer profile so consultation UI/API can be exercised end-to-end.
 */
final class AdminConsultationPreviewProfile
{
    public static function resolve(User $user): ?CustomerProfile
    {
        $profile = $user->customerProfile;

        if ($profile?->daily_calorie_target !== null) {
            return $profile;
        }

        if (! $user->isAdmin()) {
            return $profile;
        }

        return self::ensure($user, $profile);
    }

    public static function ensure(User $user, ?CustomerProfile $profile = null): CustomerProfile
    {
        $profile ??= $user->customerProfile;

        $style = MacroSplitStyle::Balanced;
        $percentages = $style->percentages();

        $attributes = [
            'onboarding_step' => OnboardingStep::FoodFilters,
            'macro_split_style' => $style,
            'daily_calorie_target' => 2000,
            'protein_percentage' => $percentages['protein_percentage'],
            'carb_percentage' => $percentages['carb_percentage'],
            'fat_percentage' => $percentages['fat_percentage'],
            'onboarding_completed_at' => now(),
        ];

        if ($profile !== null) {
            foreach ($attributes as $key => $value) {
                if ($profile->{$key} === null) {
                    $profile->{$key} = $value;
                }
            }
            $profile->save();

            return $profile->fresh();
        }

        return CustomerProfile::query()->create([
            'user_id' => $user->id,
            ...$attributes,
        ]);
    }
}
