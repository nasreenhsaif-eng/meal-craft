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
    case Macros = 'macros';
    case Meals = 'meals';
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
            self::Gender => self::PeriodTracking,
            self::PeriodTracking => self::Birthday,
            self::Birthday => self::Height,
            self::Height => self::Weight,
            self::Weight => self::TargetWeight,
            self::TargetWeight => self::Activity,
            self::Activity => self::Macros,
            self::Macros => self::Meals,
            self::Meals => self::Review,
            self::Review => null,
        };

        if ($next === self::PeriodTracking && ! self::shouldShowPeriodTracking($profile)) {
            return match ($this) {
                self::Gender => self::Birthday,
                default => $next,
            };
        }

        return $next;
    }

    public function previous(?CustomerProfile $profile = null): ?self
    {
        return match ($this) {
            self::Welcome => null,
            self::Gender => self::Welcome,
            self::PeriodTracking => self::Gender,
            self::Birthday => self::shouldShowPeriodTracking($profile)
                ? self::PeriodTracking
                : self::Gender,
            self::Height => self::Birthday,
            self::Weight => self::Height,
            self::TargetWeight => self::Weight,
            self::Activity => self::TargetWeight,
            self::Macros => self::Activity,
            self::Meals => self::Macros,
            self::Review => self::Meals,
        };
    }

    public static function shouldShowPeriodTracking(?CustomerProfile $profile): bool
    {
        return $profile?->sex === CustomerSex::Female;
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::Welcome,
            self::Gender,
            self::PeriodTracking,
            self::Birthday,
            self::Height,
            self::Weight,
            self::TargetWeight,
            self::Activity,
            self::Macros,
            self::Meals,
            self::Review,
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
