<?php

namespace App\Support;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Enums\DietProtocol;
use App\Enums\MacroSplitStyle;
use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;

/**
 * Gives staff a complete customer-shaped profile so consultation UI/API
 * adapts the same published meals a customer with those prefs would see.
 */
final class AdminConsultationPreviewProfile
{
    public static function resolve(User $user): ?CustomerProfile
    {
        $profile = $user->customerProfile;

        if (! $user->isAdmin()) {
            return $profile;
        }

        return self::ensure($user, $profile);
    }

    public static function ensure(User $user, ?CustomerProfile $profile = null): CustomerProfile
    {
        // Prefer an explicit profile, then the relation, then a DB lookup (relation may be
        // cached null on a long-lived User instance after a later create).
        $profile ??= $user->customerProfile
            ?? CustomerProfile::query()->where('user_id', $user->id)->first();

        $style = MacroSplitStyle::Balanced;
        $percentages = $style->percentages();

        $attributes = [
            'onboarding_step' => OnboardingStep::FoodFilters,
            'macro_split_style' => $style,
            'daily_calorie_target' => 2000,
            'protein_percentage' => $percentages['protein_percentage'],
            'carb_percentage' => $percentages['carb_percentage'],
            'fat_percentage' => $percentages['fat_percentage'],
            'diet_protocol' => DietProtocol::Balanced->value,
            'sex' => CustomerSex::Female,
            'activity_level' => CustomerActivityLevel::Moderate,
            'food_filters' => [],
            'onboarding_completed_at' => now(),
        ];

        if ($profile !== null) {
            foreach ($attributes as $key => $value) {
                if ($profile->{$key} === null) {
                    $profile->{$key} = $value;
                }
            }
            $profile->save();

            $fresh = $profile->fresh() ?? $profile;
            $user->setRelation('customerProfile', $fresh);

            return $fresh;
        }

        $created = CustomerProfile::query()->create([
            'user_id' => $user->id,
            ...$attributes,
        ]);

        $user->setRelation('customerProfile', $created);

        return $created;
    }
}
