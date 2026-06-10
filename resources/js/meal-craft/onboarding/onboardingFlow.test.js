import { describe, expect, it } from 'vitest';
import { getNextOnboardingStep, getPreviousOnboardingStep, shouldShowPeriodTrackingStep } from './onboardingFlow.js';

describe('onboardingFlow', () => {
    it('advances from gender to diet protocol for all customers', () => {
        expect(getNextOnboardingStep('gender', { gender: 'male' })).toBe('diet_protocol');
        expect(getNextOnboardingStep('gender', { gender: 'female' })).toBe('diet_protocol');
    });

    it('skips period tracking when cycle sync is not selected', () => {
        expect(shouldShowPeriodTrackingStep({ gender: 'male', dietProtocol: 'balanced' })).toBe(false);
        expect(shouldShowPeriodTrackingStep({ gender: 'female', dietProtocol: 'ketobiotic' })).toBe(false);
        expect(getNextOnboardingStep('diet_protocol', { gender: 'male', dietProtocol: 'balanced' })).toBe('birthday');
        expect(getNextOnboardingStep('diet_protocol', { gender: 'female', dietProtocol: 'balanced' })).toBe('birthday');
    });

    it('includes period tracking only when cycle sync is selected', () => {
        expect(shouldShowPeriodTrackingStep({ gender: 'female', dietProtocol: 'cycle_sync' })).toBe(true);
        expect(getNextOnboardingStep('diet_protocol', { gender: 'female', dietProtocol: 'cycle_sync' })).toBe(
            'period_tracking',
        );
    });

    it('walks the full linear sequence after period tracking', () => {
        const cycleSyncContext = { gender: 'female', dietProtocol: 'cycle_sync' };

        expect(getNextOnboardingStep('period_tracking', cycleSyncContext)).toBe('birthday');
        expect(getNextOnboardingStep('activity', cycleSyncContext)).toBe('daily_targets');
        expect(getNextOnboardingStep('daily_targets', cycleSyncContext)).toBe('food_filters');
    });

    it('skips period tracking when navigating backwards without cycle sync', () => {
        expect(getPreviousOnboardingStep('birthday', { gender: 'male', dietProtocol: 'balanced' })).toBe(
            'diet_protocol',
        );
        expect(getPreviousOnboardingStep('birthday', { gender: 'female', dietProtocol: 'ketobiotic' })).toBe(
            'diet_protocol',
        );
        expect(getPreviousOnboardingStep('diet_protocol', { gender: 'male', dietProtocol: 'balanced' })).toBe('gender');
    });

    it('walks back through period tracking when cycle sync is selected', () => {
        const cycleSyncContext = { gender: 'female', dietProtocol: 'cycle_sync' };

        expect(getPreviousOnboardingStep('birthday', cycleSyncContext)).toBe('period_tracking');
        expect(getPreviousOnboardingStep('period_tracking', cycleSyncContext)).toBe('diet_protocol');
    });
});
