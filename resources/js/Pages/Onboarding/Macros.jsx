import { useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.jsx';
import { OnboardingShell } from './Welcome.jsx';

const inputClassName =
    'h-[50px] w-full rounded-[15px] border border-[#E5E7EB] bg-white px-[18px] text-sm text-[#364153] focus:border-[#6E8C47] focus:outline-none focus:ring-2 focus:ring-[#6E8C47]/30';

export default function Macros() {
    const onboarding = onboardingFromPage(usePage().props);
    const profile = onboarding.profile ?? {};
    const options = onboarding.options ?? {};

    const { data, setData, post, processing, errors } = useForm({
        macro_split_style: profile.macro_split_style ?? 'balanced',
        daily_calorie_target: profile.daily_calorie_target ?? '',
    });

    return (
        <OnboardingShell
            title="Choose your macro split"
            description="Pick a macro style or override your daily calorie target if you already know it."
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'macros'}
            customerName={onboarding.customerName ?? ''}
        >
            <form
                className="grid gap-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    post(onboarding.urls?.macros ?? '/onboarding/macros');
                }}
            >
                <fieldset className="grid gap-3">
                    <legend className="text-sm font-medium">Macro style</legend>
                    {(options.macroSplitStyles ?? []).map((option) => (
                        <label key={option.value} className="flex items-center gap-3 text-sm">
                            <input
                                type="radio"
                                name="macro_split_style"
                                value={option.value}
                                checked={data.macro_split_style === option.value}
                                onChange={(event) => setData('macro_split_style', event.target.value)}
                            />
                            {option.label}
                        </label>
                    ))}
                    {errors.macro_split_style ? (
                        <span className="text-sm text-red-600">{errors.macro_split_style}</span>
                    ) : null}
                </fieldset>

                <label className="grid gap-2 text-sm font-medium">
                    Daily calorie target (optional)
                    <input
                        className={inputClassName}
                        type="number"
                        value={data.daily_calorie_target}
                        onChange={(event) => setData('daily_calorie_target', event.target.value)}
                        placeholder="Leave blank to auto-estimate"
                    />
                    {errors.daily_calorie_target ? (
                        <span className="text-sm text-red-600">{errors.daily_calorie_target}</span>
                    ) : null}
                </label>

                <div>
                    <Button type="submit" label="Continue" disabled={processing} className="min-w-[160px]" />
                </div>
            </form>
        </OnboardingShell>
    );
}

Macros.layout = customerOnboardingLayout;
