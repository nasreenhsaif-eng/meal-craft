import { useCallback, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { calculateAgeFromBirthdate } from './onboardingDates.js';
import {
    activityLevelToServer,
    dietProtocolToServer,
    normalizeActivityLevel,
    normalizeDietProtocol,
} from './onboardingNormalize.js';
import { useOnboardingStore } from './OnboardingProvider.jsx';

/**
 * Syncs an Inertia useForm instance with the onboarding wizard store for a step.
 *
 * @template {Record<string, unknown>} T
 * @param {import('./onboardingConstants.js').OnboardingStepId} stepId
 * @param {T} initialData
 * @param {(state: import('./onboardingState.js').OnboardingWizardState) => Partial<T>} mapStateToForm
 * @param {(formData: T, patch: (p: object) => void) => void} mapFormToStore
 */
export function useOnboardingStepForm(stepId, initialData, mapStateToForm, mapFormToStore) {
    const { state, patch, serverOnboarding, computeTargetsBeforeSummary } = useOnboardingStore();
    const form = useForm(initialData);

    useEffect(() => {
        const fromStore = mapStateToForm(state);
        const hasStoreValues = Object.values(fromStore).some((value) => value !== '' && value != null);

        if (hasStoreValues) {
            form.setData((current) => ({ ...current, ...fromStore }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only re-hydrate when server step advances
    }, [serverOnboarding.currentStep]);

    const syncToStore = useCallback(() => {
        mapFormToStore(form.data, patch);
    }, [form.data, patch, mapFormToStore]);

    const submit = useCallback(() => {
        syncToStore();

        if (stepId === 'diet_protocol') {
            computeTargetsBeforeSummary();
        }

        const urlKey = {
            gender: 'gender',
            period_tracking: 'periodTracking',
            birthday: 'birthday',
            height: 'height',
            weight: 'weight',
            target_weight: 'targetWeight',
            activity: 'activity',
            diet_protocol: 'dietProtocol',
            daily_targets: 'dailyTargets',
            food_filters: 'foodFilters',
        }[stepId];

        const url = urlKey ? serverOnboarding.urls?.[urlKey] : null;

        if (!url) {
            return;
        }

        form.post(url, {
            preserveScroll: true,
            onSuccess: () => {
                patch({ currentStep: stepId });
            },
        });
    }, [stepId, syncToStore, computeTargetsBeforeSummary, serverOnboarding.urls, form, patch]);

    return {
        ...form,
        state,
        patch,
        syncToStore,
        submit,
        onboarding: serverOnboarding,
    };
}

export { activityLevelToServer, dietProtocolToServer, normalizeActivityLevel, normalizeDietProtocol, calculateAgeFromBirthdate };
