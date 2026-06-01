import { createContext, useCallback, useContext, useEffect, useMemo, useReducer } from 'react';
import { usePage } from '@inertiajs/react';
import { calculateDailyTargets } from '../dailyTargetsCalculator.js';
import { onboardingFromPage } from '../mealCraftPageProps.js';
import { ONBOARDING_STORAGE_KEY } from './onboardingConstants.js';
import { getNextOnboardingStep } from './onboardingFlow.js';
import {
    createInitialOnboardingState,
    hydrateOnboardingFromServer,
    onboardingStateToProfile,
    patchOnboardingState,
} from './onboardingState.js';

/** @type {import('react').Context<null | object>} */
const OnboardingContext = createContext(null);

/**
 * @param {import('./onboardingState.js').OnboardingWizardState | undefined} persisted
 */
function loadPersistedState(persisted) {
    if (!persisted || typeof persisted !== 'object') {
        return createInitialOnboardingState();
    }

    return patchOnboardingState(createInitialOnboardingState(), persisted);
}

function readStorage() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(ONBOARDING_STORAGE_KEY);

        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeStorage(state) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.setItem(ONBOARDING_STORAGE_KEY, JSON.stringify(state));
    } catch {
        // ignore quota errors
    }
}

/**
 * @param {import('./onboardingState.js').OnboardingWizardState} state
 * @param {{ type: string; payload?: unknown }} action
 */
function onboardingReducer(state, action) {
    switch (action.type) {
        case 'HYDRATE_SERVER':
            return hydrateOnboardingFromServer(state, /** @type {object} */ (action.payload));
        case 'PATCH':
            return patchOnboardingState(state, /** @type {object} */ (action.payload));
        case 'SET_COMPUTED_TARGETS':
            return { ...state, computedTargets: /** @type {object} */ (action.payload) };
        case 'RESET':
            return createInitialOnboardingState();
        default:
            return state;
    }
}

/**
 * @param {{ children: import('react').ReactNode }} props
 */
export function OnboardingProvider({ children }) {
    const pageProps = usePage().props;
    const serverOnboarding = onboardingFromPage(pageProps);

    const [state, dispatch] = useReducer(
        onboardingReducer,
        undefined,
        () => hydrateOnboardingFromServer(loadPersistedState(readStorage()), serverOnboarding),
    );

    useEffect(() => {
        dispatch({ type: 'HYDRATE_SERVER', payload: serverOnboarding });
    }, [serverOnboarding.currentStep]);

    useEffect(() => {
        writeStorage(state);
    }, [state]);

    const profileInput = useMemo(() => onboardingStateToProfile(state), [state]);

    const computedTargets = useMemo(() => {
        if (state.computedTargets) {
            return state.computedTargets;
        }

        if (!state.weight || !state.height || !state.birthdate) {
            return null;
        }

        return calculateDailyTargets(profileInput);
    }, [state, profileInput]);

    const patch = useCallback((payload) => {
        dispatch({ type: 'PATCH', payload });
    }, []);

    const recomputeTargets = useCallback(() => {
        const targets = calculateDailyTargets(onboardingStateToProfile(state));

        dispatch({ type: 'SET_COMPUTED_TARGETS', payload: targets });

        return targets;
    }, [state]);

    const computeTargetsBeforeSummary = useCallback(() => {
        const targets = calculateDailyTargets(onboardingStateToProfile(state));

        dispatch({ type: 'SET_COMPUTED_TARGETS', payload: targets });

        return targets;
    }, [state]);

    const nextStep = useCallback(
        (fromStep = state.currentStep) => getNextOnboardingStep(fromStep, { gender: state.gender }),
        [state.currentStep, state.gender],
    );

    const value = useMemo(
        () => ({
            state,
            profileInput,
            computedTargets,
            serverOnboarding,
            patch,
            recomputeTargets,
            computeTargetsBeforeSummary,
            nextStep,
            dispatch,
        }),
        [state, profileInput, computedTargets, serverOnboarding, patch, recomputeTargets, computeTargetsBeforeSummary, nextStep],
    );

    return <OnboardingContext.Provider value={value}>{children}</OnboardingContext.Provider>;
}

export function useOnboardingStore() {
    const context = useContext(OnboardingContext);

    if (!context) {
        throw new Error('useOnboardingStore must be used within OnboardingProvider');
    }

    return context;
}

export default OnboardingProvider;
