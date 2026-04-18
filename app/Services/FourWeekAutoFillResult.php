<?php

namespace App\Services;

/**
 * Outcome of {@see MealPlanService::autoFillFourWeekPlan()}.
 */
readonly final class FourWeekAutoFillResult
{
    /**
     * @param  array<string, float>|null  $optionADailyAverages  Keys: calories, protein, carbs, fat (daily averages for path A)
     * @param  array<string, float>|null  $optionBDailyAverages
     */
    public function __construct(
        public bool $ok,
        public ?string $message = null,
        public ?array $optionADailyAverages = null,
        public ?array $optionBDailyAverages = null,
    ) {}

    public static function failure(string $message): self
    {
        return new self(ok: false, message: $message);
    }

    /**
     * @param  array<string, float>  $optionADailyAverages
     * @param  array<string, float>  $optionBDailyAverages
     */
    public static function success(string $message, array $optionADailyAverages, array $optionBDailyAverages): self
    {
        return new self(
            ok: true,
            message: $message,
            optionADailyAverages: $optionADailyAverages,
            optionBDailyAverages: $optionBDailyAverages,
        );
    }
}
