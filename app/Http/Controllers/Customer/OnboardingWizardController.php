<?php

namespace App\Http\Controllers\Customer;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Enums\MacroSplitStyle;
use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreOnboardingActivityRequest;
use App\Http\Requests\Customer\StoreOnboardingBirthdayRequest;
use App\Http\Requests\Customer\StoreOnboardingGenderRequest;
use App\Http\Requests\Customer\StoreOnboardingHeightRequest;
use App\Http\Requests\Customer\StoreOnboardingPeriodTrackingRequest;
use App\Http\Requests\Customer\StoreOnboardingTargetWeightRequest;
use App\Http\Requests\Customer\StoreOnboardingWeightRequest;
use App\Models\User;
use App\Services\Nutrition\OnboardingCalorieCalculator;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class OnboardingWizardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        return redirect()->route('onboarding.show', [
            'step' => $user?->currentOnboardingStep()->value ?? OnboardingStep::Welcome->value,
        ]);
    }

    public function show(Request $request, string $step): RedirectResponse|Response
    {
        $user = $request->user();
        $requestedStep = $this->resolveStep($step);
        $currentStep = $user?->currentOnboardingStep() ?? OnboardingStep::Welcome;

        if ($requestedStep !== $currentStep) {
            return redirect()->route('onboarding.show', ['step' => $currentStep->value]);
        }

        if ($requestedStep === OnboardingStep::PeriodTracking
            && ! OnboardingStep::shouldShowPeriodTracking($user?->customerProfile)) {
            return redirect()->route('onboarding.show', ['step' => $currentStep->value]);
        }

        return Inertia::render($this->pageForStep($requestedStep), $this->stepProps($user, $requestedStep));
    }

    public function storeWelcome(Request $request): RedirectResponse
    {
        return $this->advanceStep($request, OnboardingStep::Welcome);
    }

    public function storeGender(StoreOnboardingGenderRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'sex' => CustomerSex::from($request->validated('sex')),
            ],
        );

        return $this->advanceStep($request, OnboardingStep::Gender);
    }

    public function storePeriodTracking(StoreOnboardingPeriodTrackingRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'logged_periods' => $request->validated('logged_periods'),
                'average_cycle_length' => $request->validated('average_cycle_length'),
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

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'activity_level' => CustomerActivityLevel::from($request->validated('activity_level')),
            ],
        );

        return $this->advanceStep($request, OnboardingStep::Activity);
    }

    public function storeMacros(StoreOnboardingMacrosRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $profile = $user->customerProfile;

        if ($profile === null) {
            abort(403);
        }

        $macroStyle = MacroSplitStyle::from($validated['macro_split_style']);
        $percentages = $macroStyle->percentages();

        $dailyCalories = isset($validated['daily_calorie_target'])
            ? (int) $validated['daily_calorie_target']
            : OnboardingCalorieCalculator::estimateDailyCalories(
                (float) $profile->weight_kg,
                (float) $profile->height_cm,
                (int) $profile->age,
                $profile->sex,
                $profile->activity_level,
            );

        $profile->update([
            'macro_split_style' => $macroStyle,
            'daily_calorie_target' => $dailyCalories,
            'protein_percentage' => $percentages['protein_percentage'],
            'carb_percentage' => $percentages['carb_percentage'],
            'fat_percentage' => $percentages['fat_percentage'],
        ]);

        return $this->advanceStep($request, OnboardingStep::Macros);
    }

    public function storeMeals(Request $request): RedirectResponse
    {
        return $this->advanceStep($request, OnboardingStep::Meals);
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user?->customerProfile;

        if ($profile === null || $user->currentOnboardingStep() !== OnboardingStep::Review) {
            return redirect()->route('onboarding.show', [
                'step' => $user?->currentOnboardingStep()->value ?? OnboardingStep::Welcome->value,
            ]);
        }

        $profile->update([
            'onboarding_completed_at' => now(),
            'onboarding_step' => OnboardingStep::Review,
        ]);

        return redirect()->route('app.home');
    }

    private function advanceStep(Request $request, OnboardingStep $completedStep): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->currentOnboardingStep() !== $completedStep) {
            return redirect()->route('onboarding.show', [
                'step' => $user?->currentOnboardingStep()->value ?? OnboardingStep::Welcome->value,
            ]);
        }

        $user->load('customerProfile');
        $nextStep = $completedStep->next($user->customerProfile);

        if ($nextStep === null) {
            return redirect()->route('app.home');
        }

        $user->customerProfile()?->update([
            'onboarding_step' => $nextStep,
        ]);

        return redirect()->route('onboarding.show', ['step' => $nextStep->value]);
    }

    private function resolveStep(string $step): OnboardingStep
    {
        try {
            return OnboardingStep::from($step);
        } catch (\ValueError) {
            throw new InvalidArgumentException('Invalid onboarding step.');
        }
    }

    private function pageForStep(OnboardingStep $step): string
    {
        return match ($step) {
            OnboardingStep::Welcome => 'Onboarding/Welcome',
            OnboardingStep::Gender => 'Onboarding/Gender',
            OnboardingStep::PeriodTracking => 'Onboarding/PeriodTracking',
            OnboardingStep::Birthday => 'Onboarding/Birthday',
            OnboardingStep::Height => 'Onboarding/Height',
            OnboardingStep::Weight => 'Onboarding/Weight',
            OnboardingStep::TargetWeight => 'Onboarding/TargetWeight',
            OnboardingStep::Activity => 'Onboarding/Activity',
            OnboardingStep::Macros => 'Onboarding/Macros',
            OnboardingStep::Meals => 'Onboarding/Meals',
            OnboardingStep::Review => 'Onboarding/Review',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function stepProps(?User $user, OnboardingStep $step): array
    {
        if ($step !== OnboardingStep::Review) {
            return [];
        }

        $profile = $user?->customerProfile;

        if ($profile === null) {
            return ['reviewPlan' => null];
        }

        return [
            'reviewPlan' => UserPlanCalculator::calculateUserPlan($profile),
        ];
    }
}
