<?php

namespace App\Enums;

enum CycleSyncPhase: string
{
    case PowerPhase1 = 'power_phase_1';
    case Manifestation = 'manifestation';
    case PowerPhase2 = 'power_phase_2';
    case NurturePhase = 'nurture_phase';

    public function label(): string
    {
        return $this->phaseName();
    }

    public function phaseName(): string
    {
        return match ($this) {
            self::PowerPhase1, self::PowerPhase2 => 'Power Phase',
            self::Manifestation => 'Manifestation',
            self::NurturePhase => 'Nurture Phase',
        };
    }

    public function biologicalPhase(): string
    {
        return match ($this) {
            self::PowerPhase1 => 'Follicular Phase',
            self::Manifestation => 'Ovulation Window',
            self::PowerPhase2 => 'Early Luteal Phase',
            self::NurturePhase => 'Mid–Late Luteal Phase',
        };
    }

    public function subLabel(): string
    {
        return match ($this) {
            self::PowerPhase1 => 'Follicular · Menstruation',
            self::Manifestation => 'Late Follicular · Ovulation',
            self::PowerPhase2 => 'Early Luteal',
            self::NurturePhase => 'Mid–Late Luteal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PowerPhase1 => '#6E8C47',
            self::Manifestation => '#D8A933',
            self::PowerPhase2 => '#8F55A8',
            self::NurturePhase => '#2F4C9B',
        };
    }

    /**
     * Fasting window for the fasting protocol. Nurture is always no fasting.
     */
    public function fastingWindow(): string
    {
        return match ($this) {
            self::PowerPhase1, self::PowerPhase2 => '13–72 hours',
            self::Manifestation => '13–15 hours',
            self::NurturePhase => 'No Fasting',
        };
    }

    public function fastingGuidance(): string
    {
        return $this->fastingWindow();
    }

    public function fastingType(): string
    {
        return match ($this) {
            self::NurturePhase => 'no_fasting',
            default => 'fasting',
        };
    }

    public function foodApproach(): string
    {
        return match ($this) {
            self::PowerPhase1, self::PowerPhase2 => 'Ketobiotic',
            self::Manifestation, self::NurturePhase => 'Hormone Feasting',
        };
    }

    public function foodGuidance(): string
    {
        return $this->foodApproach();
    }

    public function mealPlanCtaLabel(): string
    {
        $phaseWord = match ($this) {
            self::PowerPhase1 => 'follicular',
            self::Manifestation => 'ovulation',
            self::PowerPhase2 => 'early luteal',
            self::NurturePhase => 'luteal',
        };

        return "Generate my {$phaseWord} meal plan";
    }

    /**
     * Effective fasting fields after applying the user's protocol preference.
     * Nurture phase always resolves to no fasting, even when protocol_mode is fasting.
     *
     * @return array{
     *     fasting_window: string,
     *     fasting_type: string,
     *     why_description: string,
     *     schedules_full_daily_meals: bool
     * }
     */
    public function effectiveFastingFor(CycleProtocolMode $protocolMode): array
    {
        $phaseRequiresNoFasting = $this === self::NurturePhase;
        $fastingActive = $protocolMode->isFastingEnabled() && ! $phaseRequiresNoFasting;

        if (! $fastingActive) {
            return [
                'fasting_window' => 'Regular Meals · No Fasting',
                'fasting_type' => 'no_fasting',
                'why_description' => $this->nonFastingWhyDescription(),
                'schedules_full_daily_meals' => true,
            ];
        }

        return [
            'fasting_window' => $this->fastingWindow(),
            'fasting_type' => $this->fastingType(),
            'why_description' => $this->fastingWhyDescription(),
            'schedules_full_daily_meals' => false,
        ];
    }

    public function whyDescriptionFor(CycleProtocolMode $protocolMode): string
    {
        return $this->effectiveFastingFor($protocolMode)['why_description'];
    }

    private function fastingWhyDescription(): string
    {
        return match ($this) {
            self::PowerPhase1 => 'Estrogen is rising in the follicular phase — lean into Ketobiotic meals with an optional 13–72 hour fasting window when it feels right for your body.',
            self::Manifestation => 'Around ovulation energy peaks — Hormone Feasting fuels performance while a lighter 13–15 hour overnight fast stays gentle.',
            self::PowerPhase2 => 'Early luteal is still a strong metabolic window — keep Ketobiotic meals and the optional 13–72 hour fasting range if you opted in.',
            self::NurturePhase => 'Mid–late luteal asks for nourishment — Hormone Feasting with regular meals and no fasting.',
        };
    }

    private function nonFastingWhyDescription(): string
    {
        return match ($this) {
            self::PowerPhase1 => 'Estrogen is rising in the follicular phase — focus on Ketobiotic meals with regular daily eating and no fasting windows.',
            self::Manifestation => 'Around ovulation energy peaks — Hormone Feasting supports you with regular meals and no fasting windows.',
            self::PowerPhase2 => 'Early luteal still favors Ketobiotic meals — keep a full daily meal rhythm without fasting windows.',
            self::NurturePhase => 'Mid–late luteal asks for nourishment — Hormone Feasting with regular meals and no fasting.',
        };
    }

    /**
     * @return array{
     *     id: string,
     *     phase_name: string,
     *     biological_phase: string,
     *     sub_label: string,
     *     fasting_window: string,
     *     fasting_type: string,
     *     food_approach: string,
     *     why_description: string,
     *     protocol_mode: string,
     *     schedules_full_daily_meals: bool,
     *     meal_plan_cta_label: string,
     *     color: string
     * }
     */
    public function metadata(?CycleProtocolMode $protocolMode = null): array
    {
        $mode = $protocolMode ?? CycleProtocolMode::default();
        $effective = $this->effectiveFastingFor($mode);

        return [
            'id' => $this->value,
            'phase_name' => $this->phaseName(),
            'biological_phase' => $this->biologicalPhase(),
            'sub_label' => $this->subLabel(),
            'fasting_window' => $effective['fasting_window'],
            'fasting_type' => $effective['fasting_type'],
            'food_approach' => $this->foodApproach(),
            'why_description' => $effective['why_description'],
            'protocol_mode' => $mode->value,
            'schedules_full_daily_meals' => $effective['schedules_full_daily_meals'],
            'meal_plan_cta_label' => $this->mealPlanCtaLabel(),
            'color' => $this->color(),
        ];
    }

    /**
     * Payload passed into meal-plan generation when the Active Phase CTA is clicked.
     *
     * @return array{
     *     protocol_mode: string,
     *     phase_key: string,
     *     biological_phase: string,
     *     food_approach: string,
     *     fasting_window: string,
     *     fasting_type: string,
     *     schedules_full_daily_meals: bool,
     *     craft_key: string
     * }
     */
    public function mealPlanGenerationPayload(CycleProtocolMode $protocolMode): array
    {
        $effective = $this->effectiveFastingFor($protocolMode);

        return [
            'protocol_mode' => $protocolMode->value,
            'phase_key' => $this->value,
            'biological_phase' => $this->biologicalPhase(),
            'food_approach' => $this->foodApproach(),
            'fasting_window' => $effective['fasting_window'],
            'fasting_type' => $effective['fasting_type'],
            'schedules_full_daily_meals' => $effective['schedules_full_daily_meals'],
            'craft_key' => $effective['schedules_full_daily_meals'] ? 'full' : 'intermittent',
        ];
    }

    /**
     * Map Cycle Sync phases onto the meal-library CyclePhase used by macros.
     */
    public function toLegacyCyclePhase(): CyclePhase
    {
        return match ($this) {
            self::PowerPhase1 => CyclePhase::Menstrual,
            self::Manifestation => CyclePhase::Ovulatory,
            self::PowerPhase2 => CyclePhase::Follicular,
            self::NurturePhase => CyclePhase::Luteal,
        };
    }
}
