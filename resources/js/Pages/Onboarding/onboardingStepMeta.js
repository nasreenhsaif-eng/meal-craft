/** @typedef {import('../../meal-craft/onboarding/onboardingConstants.js').OnboardingStepId} OnboardingStepId */

/** @type {Record<OnboardingStepId, { title: string; description: string; centerHeader?: boolean; titleClassName?: string; hideNext?: boolean }>} */
export const ONBOARDING_STEP_META = {
    gender: {
        title: 'Create your profile',
        description: 'Select your gender so we can personalize calorie and macro calculations.',
        centerHeader: true,
        hideNext: true,
    },
    period_tracking: {
        title: 'Track your period',
        description:
            'Log recent cycles so we can personalize nutrition recommendations across your menstrual phases.',
        centerHeader: true,
        titleClassName: 'text-brand-primary-pressed',
    },
    birthday: {
        title: 'When is your birthday?',
        description:
            'Your age helps us tailor recommendations to match your changing nutritional needs over time.',
        centerHeader: true,
    },
    height: {
        title: 'How tall are you?',
        description: 'Height is used with weight to calculate your Total Daily Energy Expenditure (TDEE).',
        centerHeader: true,
    },
    weight: {
        title: 'How much do you weigh?',
        description:
            'Weight is used alongside height to accurately calculate your Total Daily Energy Expenditure (TDEE) and target calories.',
        centerHeader: true,
    },
    target_weight: {
        title: 'What is your target weight?',
        description:
            'Your target weight helps us personalize calorie and macro recommendations for your goal.',
        centerHeader: true,
    },
    activity: {
        title: 'How active are you every day?',
        description:
            'Your activity level influences how many calories you burn, allowing us to provide accurate daily nutrition targets.',
        centerHeader: true,
    },
    diet_protocol: {
        title: 'Which diet protocol fits you best?',
        description: 'We will tailor your macro split and meal suggestions to match this approach.',
        centerHeader: true,
        hideNext: true,
    },
    daily_targets: {
        title: 'Your Daily Targets',
        description: '',
        centerHeader: true,
        hideDefaultHeader: true,
    },
    food_filters: {
        title: 'Food filters',
        description: 'Select any ingredients or sensitivities we should avoid when planning your meals.',
        centerHeader: true,
    },
};
