import { describe, expect, it } from 'vitest';
import { getNextOnboardingStep, getPreviousOnboardingStep, shouldShowPeriodTrackingStep } from './onboardingFlow.js';

describe('onboardingFlow', () => {
    it('moves gender into birthday for every sex', () => {
        expect(getNextOnboardingStep('gender', { gender: 'male' })).toBe('birthday');
        expect(getNextOnboardingStep('gender', { gender: 'female' })).toBe('birthday');
    });

    it('follows the body metrics spine', () => {
        const context = { gender: 'female', dietProtocol: 'balanced' };

        expect(getNextOnboardingStep('birthday', context)).toBe('height');
        expect(getNextOnboardingStep('height', context)).toBe('weight');
        expect(getNextOnboardingStep('weight', context)).toBe('target_weight');
        expect(getNextOnboardingStep('target_weight', context)).toBe('activity');
        expect(getNextOnboardingStep('activity', context)).toBe('diet_protocol');
    });

    it('skips cycle sync steps for non cycle protocols', () => {
        expect(getNextOnboardingStep('diet_protocol', { gender: 'male', dietProtocol: 'balanced' })).toBe(
            'daily_targets',
        );
        expect(getNextOnboardingStep('diet_protocol', { gender: 'female', dietProtocol: 'balanced' })).toBe(
            'daily_targets',
        );
    });

    it('inserts the three cycle sync screens for cycle_sync', () => {
        expect(getNextOnboardingStep('diet_protocol', { gender: 'female', dietProtocol: 'cycle_sync' })).toBe(
            'period_tracking',
        );

        const cycleSyncContext = { gender: 'female', dietProtocol: 'cycle_sync' };

        expect(getNextOnboardingStep('period_tracking', cycleSyncContext)).toBe('protocol_selection');
        expect(getNextOnboardingStep('protocol_selection', cycleSyncContext)).toBe('active_phase');
        expect(getNextOnboardingStep('active_phase', cycleSyncContext)).toBe('daily_targets');
        expect(getNextOnboardingStep('daily_targets', cycleSyncContext)).toBe('food_filters');
    });

    it('walks back through cycle sync screens', () => {
        expect(getPreviousOnboardingStep('daily_targets', { gender: 'male', dietProtocol: 'balanced' })).toBe(
            'diet_protocol',
        );
        expect(getPreviousOnboardingStep('daily_targets', { gender: 'female', dietProtocol: 'ketobiotic' })).toBe(
            'diet_protocol',
        );
        expect(getPreviousOnboardingStep('diet_protocol', { gender: 'male', dietProtocol: 'balanced' })).toBe(
            'activity',
        );
        expect(getPreviousOnboardingStep('birthday', { gender: 'female', dietProtocol: 'ketobiotic' })).toBe('gender');

        const cycleSyncContext = { gender: 'female', dietProtocol: 'cycle_sync' };

        expect(getPreviousOnboardingStep('daily_targets', cycleSyncContext)).toBe('active_phase');
        expect(getPreviousOnboardingStep('active_phase', cycleSyncContext)).toBe('protocol_selection');
        expect(getPreviousOnboardingStep('protocol_selection', cycleSyncContext)).toBe('period_tracking');
        expect(getPreviousOnboardingStep('period_tracking', cycleSyncContext)).toBe('diet_protocol');
    });

    it('only shows period tracking for cycle_sync', () => {
        expect(shouldShowPeriodTrackingStep({ dietProtocol: 'cycle_sync' })).toBe(true);
        expect(shouldShowPeriodTrackingStep({ dietProtocol: 'balanced' })).toBe(false);
    });
});
