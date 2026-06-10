<?php

namespace App\Http\Controllers\Customer;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Enums\DietProtocol;
use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreOnboardingActivityRequest;
use App\Http\Requests\Customer\StoreOnboardingBirthdayRequest;
use App\Http\Requests\Customer\StoreOnboardingDietProtocolRequest;
use App\Http\Requests\Customer\StoreOnboardingFoodFiltersRequest;
use App\Http\Requests\Customer\StoreOnboardingGenderRequest;
use App\Http\Requests\Customer\StoreOnboardingHeightRequest;
use App\Http\Requests\Customer\StoreOnboardingPeriodTrackingRequest;
use App\Http\Requests\Customer\StoreOnboardingTargetWeightRequest;
use App\Http\Requests\Customer\StoreOnboardingWeightRequest;
use App\Models\User;
use App\Services\Nutrition\OnboardingDailyTargetsCalculator;
use App\Services\Nutrition\PeriodTrackingPhaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        return redirect()->route('onboarding.show', [
            'step' => $user?->currentOnboardingStep()->value ?? OnboardingStep::entry()->value,
        ]);
    }

    public function show(Request $request, string $step): Response
    {
        $user = $request->user();
        if ($step === OnboardingStep::Welcome->value) {
            return redirect()->route('onboarding.show', ['step' => OnboardingStep::Gender->value]);
        }

        $requestedStep = OnboardingStep::normalizeStoredStep(OnboardingStep::from($step));

        return Inertia::render('Onboarding/Container', array_merge(
            ['activeStep' => $requestedStep->value],
            $this->stepProps($user, $requestedStep),
        ));
    }

    public function resetForTesting(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user?->customerProfile()?->update([
            'onboarding_step' => OnboardingStep::Gender,
            'onboarding_completed_at' => null,
        ]);

        return redirect()->route('onboarding.show', ['step' => OnboardingStep::Gender->value]);
    }

    public function storeGender(StoreOnboardingGenderRequest $request): RedirectResponse
    {
        $user = $request->user();
        $sex = CustomerSex::from($request->validated('sex'));

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'sex' => $sex,
                'gender' => $sex->value,
            ],
        );

        return $this->advanceStep($request, OnboardingStep::Gender);
    }

    public function storePeriodTracking(StoreOnboardingPeriodTrackingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $loggedPeriods = $request->validated('logged_periods');
        $averageCycleLength = $request->validated('average_cycle_length');

        $periodTrackingData = PeriodTrackingPhaseService::buildPeriodTrackingData(
            $loggedPeriods,
            $averageCycleLength,
        );

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'logged_periods' => $loggedPeriods,
                'average_cycle_length' => $averageCycleLength,
                'period_tracking_data' => $periodTrackingData,
            ],
        );

        return $this->advanceStep($request, OnboardingStep::PeriodTracking);
    }

    public function storeBirthday(StoreOnboardingBirthdayRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dateOfBirth = Carbon::parse($request->validated('date_of_birth'))->startOfDay();

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'date_of_birth' => $dateOfBirth->toDateString(),
                'birthdate' => $dateOfBirth->toDateString(),
                'age' => (int) $dateOfBirth->diffInYears(now()),
            ],
        );

        return $this->advanceStep($request, OnboardingStep::Birthday);
    }

    public function storeHeight(StoreOnboardingHeightRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'height_cm' => (float) $request->validated('height_cm'),
            ],
        );

        return $this->advanceStep($request, OnboardingStep::Height);
    }

    public function storeWeight(StoreOnboardingWeightRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'weight_kg' => (float) $request->validated('weight_kg'),
            ],
        );

        return $this->advanceStep($request, OnboardingStep::Weight);
    }

    public function storeTargetWeight(StoreOnboardingTargetWeightRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'target_weight_kg' => (float) $request->validated('target_weight_kg'),
            ],
        );

        return $this->advanceStep($request, OnboardingStep::TargetWeight);
    }

    public function storeActivity(StoreOnboardingActivityRequest $request): RedirectResponse
    {
        $user = $request->user();
        $activity = CustomerActivityLevel::tryFromStored($request->validated('activity_level'));

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'activity_level' => $activity,
            ],
        );

        return $this->advanceStep($request, OnboardingStep::Activity);
    }

    public function storeDietProtocol(StoreOnboardingDietProtocolRequest $request): RedirectResponse
    {
        $user = $request->user();
        $protocol = DietProtocol::tryFromStored($request->validated('diet_protocol'));

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'diet_protocol' => $protocol->value,
            ],
        );

        $profile = $user->fresh()->customerProfile;

        if ($profile !== null) {
            $targets = OnboardingDailyTargetsCalculator::calculate($profile);

            $profile->update([
                'daily_calorie_target' => $targets['daily_calories'],
                'protein_percentage' => $targets['protein_percentage'],
                'carb_percentage' => $targets['carb_percentage'],
                'fat_percentage' => $targets['fat_percentage'],
            ]);
        }

        return $this->advanceStep($request, OnboardingStep::DietProtocol);
    }

    public function storeDailyTargets(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user?->customerProfile;

        if ($profile === null) {
            abort(403);
        }

        $targets = OnboardingDailyTargetsCalculator::calculate($profile);

        $profile->update([
            'daily_calorie_target' => $targets['daily_calories'],
            'protein_percentage' => $targets['protein_percentage'],
            'carb_percentage' => $targets['carb_percentage'],
            'fat_percentage' => $targets['fat_percentage'],
        ]);

        return $this->advanceStep($request, OnboardingStep::DailyTargets);
    }

    public function storeFoodFilters(StoreOnboardingFoodFiltersRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user?->customerProfile;

        if ($profile === null || $user->currentOnboardingStep() !== OnboardingStep::FoodFilters) {
            return redirect()->route('onboarding.show', [
                'step' => $user?->currentOnboardingStep()->value ?? OnboardingStep::entry()->value,
            ]);
        }

        $foodFilters = array_values($request->validated('allergies') ?? []);

        $profile->update([
            'allergies' => $foodFilters,
            'food_filters' => $foodFilters,
            'onboarding_completed_at' => now(),
            'onboarding_step' => OnboardingStep::FoodFilters,
        ]);

        return redirect()->route('app.home');
    }

    private function advanceStep(Request $request, OnboardingStep $completedStep): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('onboarding.show', [
                'step' => OnboardingStep::entry()->value,
            ]);
        }

        $user->load('customerProfile');
        $nextStep = $completedStep->next($user->customerProfile);

        if ($nextStep === null) {
            return redirect()->route('app.home');
        }

        $savedStep = OnboardingStep::normalizeStoredStep($user->currentOnboardingStep());
        $orderedSteps = OnboardingStep::orderedFor($user->customerProfile);
        $savedIndex = array_search($savedStep, $orderedSteps, true);
        $nextIndex = array_search($nextStep, $orderedSteps, true);

        if ($nextIndex !== false && ($savedIndex === false || $nextIndex > $savedIndex)) {
            $user->customerProfile()?->update([
                'onboarding_step' => $nextStep,
            ]);
        }

        return redirect()->route('onboarding.show', ['step' => $nextStep->value]);
    }

    private function pageForStep(OnboardingStep $step): string
    {
        $step = OnboardingStep::normalizeStoredStep($step);

        return match ($step) {
            OnboardingStep::Gender => 'Onboarding/Gender',
            OnboardingStep::PeriodTracking => 'Onboarding/PeriodTracking',
            OnboardingStep::Birthday => 'Onboarding/Birthday',
            OnboardingStep::Height => 'Onboarding/Height',
            OnboardingStep::Weight => 'Onboarding/Weight',
            OnboardingStep::TargetWeight => 'Onboarding/TargetWeight',
            OnboardingStep::Activity => 'Onboarding/Activity',
            OnboardingStep::DietProtocol => 'Onboarding/DietProtocol',
            OnboardingStep::DailyTargets => 'Onboarding/DailyTargetsSummary',
            OnboardingStep::FoodFilters => 'Onboarding/FoodFilter',
            OnboardingStep::Macros => 'Onboarding/DietProtocol',
            OnboardingStep::Meals => 'Onboarding/DailyTargetsSummary',
            OnboardingStep::Review => 'Onboarding/DailyTargetsSummary',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function stepProps(?User $user, OnboardingStep $step): array
    {
        if ($step !== OnboardingStep::DailyTargets || $user?->customerProfile === null) {
            return [];
        }

        $targets = OnboardingDailyTargetsCalculator::calculate($user->customerProfile);

        return [
            'computedTargets' => [
                'bmr' => $targets['bmr'],
                'tdee' => $targets['tdee'],
                'dailyCalories' => $targets['daily_calories'],
                'proteinGrams' => $targets['protein_grams'],
                'carbGrams' => $targets['carb_grams'],
                'fatGrams' => $targets['fat_grams'],
                'proteinPercentage' => $targets['protein_percentage'],
                'carbPercentage' => $targets['carb_percentage'],
                'fatPercentage' => $targets['fat_percentage'],
                'goal' => $targets['goal'],
                'dietProtocol' => $targets['diet_protocol'],
                'currentPhase' => $targets['current_phase'],
            ],
        ];
    }
}
