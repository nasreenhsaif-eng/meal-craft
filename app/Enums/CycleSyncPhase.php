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
     * Short hormonal / energy summary for the Analysis tab.
     */
    public function analysisSummary(): string
    {
        return match ($this) {
            self::PowerPhase1 => 'Estrogen is rising after bleed. Energy, recovery, and motivation typically climb — a strong window for building habits and strength.',
            self::Manifestation => 'Estrogen (and a small testosterone bump) peak around ovulation. Outward energy, verbal fluency, and power output are often at their highest.',
            self::PowerPhase2 => 'Estrogen dips while progesterone begins to rise. Steady, structured effort suits this early-luteal stretch better than max intensity.',
            self::NurturePhase => 'Progesterone stays higher then both hormones decline before bleed. Core temperature and resting heart rate rise — prioritize recovery and lower stimulation.',
        };
    }

    /**
     * @return array{focus: string, why: string}
     */
    public function exerciseGuidance(): array
    {
        return match ($this) {
            self::PowerPhase1 => [
                'focus' => 'Progressive strength training, hypertrophy/lifting, and moderate-to-high intensity cardio or runs. Ease in during early bleed days, then build as estrogen rises.',
                'why' => 'Rising estrogen supports muscle recovery, higher pain tolerance, and strength gains.',
            ],
            self::Manifestation => [
                'focus' => 'Peak output — heavy lifts, PR attempts, sprints, HIIT, or upbeat group workouts, with solid warm-ups and joint stability.',
                'why' => 'Peak estrogen and testosterone boost neuromuscular drive (watch ligament laxity around ovulation — prioritize form).',
            ],
            self::PowerPhase2 => [
                'focus' => 'Moderate strength, steady-state Zone 2 runs, Pilates, and controlled tempo work.',
                'why' => 'Estrogen dips while progesterone rises; steady effort maintains fitness without overtaxing recovery.',
            ],
            self::NurturePhase => [
                'focus' => 'Low-impact movement — walking, restorative yoga, gentle Pilates, mobility, and lighter deload strength.',
                'why' => 'Higher progesterone raises core temperature and resting heart rate; lowering high-intensity cortisol spikes supports sleep and balance before menstruation.',
            ],
        };
    }

    /**
     * @return array{focus: string, what_to_do: string}
     */
    public function brainGuidance(): array
    {
        return match ($this) {
            self::PowerPhase1 => [
                'focus' => 'Fresh mental clarity, curiosity, problem-solving, and strategic planning as estrogen rises.',
                'what_to_do' => 'Start new projects, tackle analytical or complex planning work, brainstorm ideas, and set goals for the month.',
            ],
            self::Manifestation => [
                'focus' => 'Peak verbal fluency, social confidence, collaboration, and outward energy.',
                'what_to_do' => 'Schedule presentations, negotiations, interviews, networking, creative collaboration, and important conversations.',
            ],
            self::PowerPhase2 => [
                'focus' => 'Detail-oriented focus, structured execution, and task completion as calming progesterone enters.',
                'what_to_do' => 'Deep work, organizing systems, editing, finishing ongoing deliverables, and tying up loose ends.',
            ],
            self::NurturePhase => [
                'focus' => 'Reflective, inward-facing intuition with lower tolerance for overstimulation or heavy multitasking.',
                'what_to_do' => 'Prioritize rest, protect calendar boundaries, batch administrative tasks, journal/review progress, and avoid overloading late-night schedules.',
            ],
        };
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
