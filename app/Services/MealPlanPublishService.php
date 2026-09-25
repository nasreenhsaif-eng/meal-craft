<?php

namespace App\Services;

use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Models\MealPlan;
use App\Support\MealPlanDateRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Publishes admin default meal picks for a week and seeds customer craft slots.
 */
final class MealPlanPublishService
{
    /**
     * @param  array<int, array<string, list<int>>>  $selections
     * @return array{seeded: int, skipped: int}
     */
    public static function publish(
        MealPlan $plan,
        array $selections,
        string $startsOn,
        string $endsOn,
        ?string $description = null,
    ): array {
        $starts = Carbon::parse($startsOn)->startOfDay();
        $ends = Carbon::parse($endsOn)->startOfDay();

        MealPlanDefaultDaySelections::store($plan, $selections);

        $plan->forceFill([
            'description' => $description !== null && trim($description) !== '' ? trim($description) : $plan->description,
            'published_starts_on' => $starts->toDateString(),
            'published_ends_on' => $ends->toDateString(),
        ])->save();

        return self::seedCustomers($plan->fresh(), $starts, $ends);
    }

    /**
     * @return array{seeded: int, skipped: int}
     */
    private static function seedCustomers(MealPlan $plan, Carbon $starts, Carbon $ends): array
    {
        $remainingWeekdays = MealPlanDateRange::remainingWeekdays($starts, $ends);
        $defaults = MealPlanDefaultDaySelections::forPlan($plan);

        if ($remainingWeekdays === [] || $defaults === []) {
            return ['seeded' => 0, 'skipped' => 0];
        }

        $seeded = 0;
        $skipped = 0;

        CustomerProfile::query()
            ->whereNotNull('daily_calorie_target')
            ->orderBy('id')
            ->chunkById(50, function ($profiles) use ($starts, $ends, $remainingWeekdays, $defaults, &$seeded, &$skipped): void {
                foreach ($profiles as $profile) {
                    if (! $profile instanceof CustomerProfile) {
                        continue;
                    }

                    if (self::customerAlreadyChoseForWeek($profile, $starts, $ends)) {
                        $skipped++;

                        continue;
                    }

                    self::seedProfile($profile, $remainingWeekdays, $defaults);
                    $seeded++;
                }
            });

        return ['seeded' => $seeded, 'skipped' => $skipped];
    }

    public static function customerAlreadyChoseForWeek(
        CustomerProfile $profile,
        Carbon $starts,
        Carbon $ends,
    ): bool {
        return $profile->craftPlans()
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [
                $starts->copy()->subDays(7)->startOfDay(),
                $ends->copy()->endOfDay(),
            ])
            ->exists();
    }

    /**
     * @param  list<int>  $remainingWeekdays
     * @param  array<int, array<string, list<int>>>  $defaults
     */
    private static function seedProfile(
        CustomerProfile $profile,
        array $remainingWeekdays,
        array $defaults,
    ): void {
        $days = [];

        foreach ($remainingWeekdays as $dayOfWeek) {
            $dayDefaults = $defaults[$dayOfWeek] ?? null;

            if ($dayDefaults === null) {
                continue;
            }

            $days[] = [
                'day_of_week' => $dayOfWeek,
                'include_soup' => ($dayDefaults['soup'] ?? []) !== [],
                'selections' => [
                    'breakfasts' => $dayDefaults['breakfasts'] ?? [],
                    'meals' => $dayDefaults['meals'] ?? [],
                    'sideSalads' => $dayDefaults['sideSalads'] ?? [],
                    'desserts' => $dayDefaults['desserts'] ?? [],
                    'soup' => $dayDefaults['soup'] ?? [],
                ],
            ];
        }

        if ($days === []) {
            return;
        }

        DB::transaction(function () use ($profile, $remainingWeekdays, $days): void {
            $profile->craftPlans()
                ->whereNull('submitted_at')
                ->delete();

            CustomerCraftPlanService::storeSubmission($profile, [
                'craft_key' => 'full',
                'week_duration' => count($remainingWeekdays),
                'selected_days' => array_values($remainingWeekdays),
                'days' => $days,
            ]);

            // storeSubmission always sets submitted_at; clear it so the customer still "chooses".
            $latest = $profile->craftPlans()->latest('id')->first();

            if ($latest instanceof CustomerCraftPlan) {
                $latest->forceFill(['submitted_at' => null])->save();
            }
        });
    }
}
