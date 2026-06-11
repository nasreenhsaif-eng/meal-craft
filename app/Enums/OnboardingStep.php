<?php

namespace App\Enums;

use App\Models\CustomerProfile;

enum OnboardingStep: string
{
    case Welcome = 'welcome';
    case Gender = 'gender';
    case PeriodTracking = 'period_tracking';
    case Birthday = 'birthday';
    case Height = 'height';
    case Weight = 'weight';
    case TargetWeight = 'target_weight';
    case Activity = 'activity';
    case DietProtocol = 'diet_protocol';
    case DailyTargets = 'daily_targets';
    case FoodFilters = 'food_filters';

    /** @deprecated Removed from onboarding flow; normalized to {@see self::DailyTargets} */
    case WeightGoal = 'weight_goal';

    /** @deprecated Replaced by {@see self::DietProtocol} */
    case Macros = 'macros';

    /** @deprecated Removed from onboarding flow */
    case Meals = 'meals';

    /** @deprecated Completion marker for legacy profiles */
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => __('Welcome'),
            self::Gender => __('Gender'),
            self::PeriodTracking => __('Track your period'),
            self::Birthday => __('Birthday'),
            self::Height => __('Height'),
            self::Weight => __('Weight'),
            self::TargetWeight => __('Target weight'),
            self::Activity => __('Activity'),
            self::DietProtocol => __('Diet protocol'),
            self::DailyTargets => __('Daily targets'),
            self::FoodFilters => __('Food filters'),
            self::Macros => __('Macro split'),
            self::Meals => __('Choose meals'),
            self::Review => __('Review'),
        };
    }

    public function routeName(): string
    {
        return 'onboarding.'.$this->value;
    }

    public function next(?CustomerProfile $profile = null): ?self
    {
        $next = match ($this) {
            self::Welcome => self::Gender,
            self::Gender => self::DietProtocol,
            self::DietProtocol => self::PeriodTracking,
            self::PeriodTracking => self::Birthday,
            self::Birthday => self::Height,
            self::Height => self::Weight,
            self::Weight => self::TargetWeight,
            self::TargetWeight => self::Activity,
            self::Activity => self::DailyTargets,
            self::DailyTargets => self::FoodFilters,
            self::FoodFilters => null,
            self::Macros => self::DietProtocol,
            self::Meals => self::DailyTargets,
            self::Review => null,
        };

        if ($next === self::PeriodTracking && ! self::shouldShowPeriodTracking($profile)) {
            return match ($this) {
                self::DietProtocol => self::Birthday,
                default => $next,
            };
        }

        return $next;
    }

    public function previous(?CustomerProfile $profile = null): ?self
    {
        return match ($this) {
            self::Welcome, self::Gender => null,
            self::DietProtocol => self::Gender,
            self::PeriodTracking => self::DietProtocol,
            self::Birthday => self::shouldShowPeriodTracking($profile)
                ? self::PeriodTracking
                : self::DietProtocol,
            self::Height => self::Birthday,
            self::Weight => self::Height,
            self::TargetWeight => self::Weight,
            self::Activity => self::TargetWeight,
            self::DailyTargets => self::Activity,
            self::FoodFilters => self::DailyTargets,
            self::Macros => self::Activity,
            self::Meals => self::DietProtocol,
            self::Review => self::DailyTargets,
        };
    }

    public static function shouldShowPeriodTracking(?CustomerProfile $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        return DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::CycleSync;
    }

    /**
     * Map legacy persisted steps onto the current flow.
     */
    public static function normalizeStoredStep(self|string $step): self
    {
        $resolved = $step instanceof self ? $step : self::from($step);

        return match ($resolved) {
            self::Welcome => self::Gender,
            self::Macros => self::DietProtocol,
            self::Meals => self::DailyTargets,
            self::WeightGoal => self::DailyTargets,
            self::Review => self::FoodFilters,
            default => $resolved,
        };
    }

    /**
     * First step customers see in the live onboarding wizard.
     */
    public static function entry(): self
    {
        return self::Gender;
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::Gender,
            self::DietProtocol,
            self::PeriodTracking,
            self::Birthday,
            self::Height,
            self::Weight,
            self::TargetWeight,
            self::Activity,
            self::DailyTargets,
            self::FoodFilters,
        ];
    }

    /**
     * @return list<self>
     */
    public static function orderedFor(?CustomerProfile $profile): array
    {
        return array_values(array_filter(
            self::ordered(),
            static fn (self $step): bool => $step !== self::PeriodTracking
                || self::shouldShowPeriodTracking($profile),
        ));
    }
}
