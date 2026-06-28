<?php

namespace App\Console\Commands;

use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\IngredientMicroDataAuditor;
use App\Services\Nutrition\DayMicronutrientCoverageAnalyzer;
use App\Support\NutrientDailyRdi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nutrition:audit-day-coverage {--tier= : Limit audit to a single plan tier} {--day= : Limit audit to a single weekday (1-7)} {--ingredients : Audit boost-catalog ingredient micro data completeness}')]
#[Description('Audit Full Craft day micronutrient coverage against RDI targets by tier and fixed-slot combination')]
class NutritionAuditDayCoverage extends Command
{
    public function handle(): int
    {
        $profile = $this->resolveReferenceProfile();

        if ($profile === null) {
            $this->error('No customer profile available for simulation. Create a profile with a calorie target first.');

            return self::FAILURE;
        }

        if ($this->option('ingredients')) {
            $auditor = app(IngredientMicroDataAuditor::class);
            $this->info($auditor->formatReport($auditor->auditScheduledBoostIngredients()));
            $this->newLine();
        }

        $tierFilter = $this->option('tier');
        $dayFilter = $this->option('day');

        $tiers = NutrientDailyRdi::allAuditTiers();

        if (is_numeric($tierFilter)) {
            $tiers = [(int) $tierFilter];
        }

        $days = range(1, 7);

        if (is_numeric($dayFilter)) {
            $days = [max(1, min(7, (int) $dayFilter))];
        }

        $enforcedFailures = 0;
        $informationalFailures = 0;

        foreach ($tiers as $tier) {
            $sectionLabel = NutrientDailyRdi::tierEnforced($tier)
                ? "Enforced tier {$tier} kcal"
                : "Informational tier {$tier} kcal (expected gaps)";

            $this->newLine();
            $this->info($sectionLabel);
            $this->line(str_repeat('-', strlen($sectionLabel)));

            foreach (NutrientDailyRdi::fixedSlotCombinations() as $combination) {
                $slots = NutrientDailyRdi::parseFixedSlotCombination($combination);

                foreach ($days as $dayNumber) {
                    $report = DayMicronutrientCoverageAnalyzer::simulateFullCraftDay(
                        $profile,
                        $dayNumber,
                        (float) $tier,
                        $slots,
                    );

                    $fixedLabel = implode(' + ', $report['selected_fixed_slots']);
                    $status = $report['passes'] ? 'PASS' : 'FAIL';
                    $this->line("Day {$dayNumber} | {$fixedLabel} | {$status} | {$report['day_calories']} kcal");

                    if ($report['failing_floor'] !== []) {
                        $this->line('  Floor gaps: '.implode(', ', $report['failing_floor']));
                    }

                    if ($report['failing_ceiling'] !== []) {
                        $this->line('  Ceiling exceedances: '.implode(', ', $report['failing_ceiling']));
                    }

                    $vitaminD = collect($report['nutrients'])->firstWhere('label', 'Vitamin D (mcg)');

                    if (is_array($vitaminD)) {
                        $this->line(sprintf(
                            '  Vitamin D (best effort): %.1f%% (%.1f / %.0f mcg)',
                            $vitaminD['percent'],
                            $vitaminD['total'],
                            $vitaminD['rdi'],
                        ));
                    }

                    if (! $report['passes']) {
                        if ($report['enforced']) {
                            $enforcedFailures++;
                        } else {
                            $informationalFailures++;
                        }
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Enforced-tier failures: {$enforcedFailures}");
        $this->info("Informational-tier failures: {$informationalFailures} (expected at 1000/1200 kcal)");

        return $enforcedFailures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveReferenceProfile(): ?CustomerProfile
    {
        $profile = CustomerProfile::query()
            ->whereNotNull('daily_calorie_target')
            ->orderBy('id')
            ->first();

        if ($profile instanceof CustomerProfile) {
            return $profile;
        }

        $user = User::factory()->create();
        $profile = CustomerProfile::factory()->for($user)->create([
            'daily_calorie_target' => 1500,
            'protein_percentage' => 40.0,
            'carb_percentage' => 30.0,
            'fat_percentage' => 30.0,
        ]);

        return $profile;
    }
}
