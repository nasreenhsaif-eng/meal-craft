import { describe, expect, it } from 'vitest';
import { mergeDetailViews } from './useMealDetailModal.js';
import { buildNutritionalDataFromNutrition } from './buildNutritionalDataFromNutrition.js';

describe('mergeDetailViews', () => {
    it('keeps card-adapted macros, nutritionalData, and ingredients when API returns different numbers', () => {
        const initial = {
            macros: { calories: 510, protein: 41.7, carbs: 40.9, fat: 20.8 },
            nutritionalData: buildNutritionalDataFromNutrition({
                calories: 510,
                protein: 41.7,
                carbs: 40.9,
                fat: 20.8,
                fiber: 10.3,
            }),
            nutritionSubheading: 'Adapted for your plan',
            ingredients: ['120g Beef Liver', '20g Cooked Quinoa (Base)'],
            instructions: ['Card step'],
        };

        const fromApi = {
            macros: { calories: 540, protein: 44.9, carbs: 43.3, fat: 21.5 },
            nutritionalData: buildNutritionalDataFromNutrition({
                calories: 540,
                protein: 44.9,
                carbs: 43.3,
                fat: 21.5,
                fiber: 10.3,
            }),
            nutritionSubheading: 'Adapted for your plan',
            ingredients: ['130g Beef Liver', '45g Cooked Quinoa (Base)'],
            instructions: ['API step one', 'API step two'],
            safetyAlerts: [{ label: 'SESAME', variant: 'allergy' }],
        };

        const merged = mergeDetailViews(initial, fromApi);

        expect(merged.macros).toEqual(initial.macros);
        expect(merged.ingredients).toEqual(initial.ingredients);
        expect(merged.nutritionalData).toEqual(initial.nutritionalData);
        expect(merged.instructions).toEqual(fromApi.instructions);
        expect(merged.safetyAlerts).toEqual(fromApi.safetyAlerts);
    });

    it('uses API nutrition when the card has no adapted detail', () => {
        const fromApi = {
            macros: { calories: 400, protein: 30, carbs: 20, fat: 15 },
            nutritionalData: buildNutritionalDataFromNutrition({
                calories: 400,
                protein: 30,
                carbs: 20,
                fat: 15,
            }),
            ingredients: ['150g Chicken Breast'],
            instructions: ['Cook'],
        };

        const merged = mergeDetailViews({ title: 'Chicken' }, fromApi);

        expect(merged.macros).toEqual(fromApi.macros);
        expect(merged.ingredients).toEqual(fromApi.ingredients);
        expect(merged.instructions).toEqual(fromApi.instructions);
    });
});

describe('buildNutritionalDataFromNutrition', () => {
    it('shows total carbs on the primary macro row and net carbs separately', () => {
        const data = buildNutritionalDataFromNutrition({
            calories: 540,
            protein: 44.9,
            carbs: 43.3,
            fat: 21.5,
            fiber: 10.3,
            sugar: 15.2,
        });

        const macroRows = data.sections[0].rows;
        const labels = macroRows.map((row) => row.label);

        expect(labels).toContain('Carbs (g)');
        expect(labels).toContain('Net carbs (g)');

        const carbsRow = macroRows.find((row) => row.label === 'Carbs (g)');
        const netCarbsRow = macroRows.find((row) => row.label === 'Net carbs (g)');

        expect(carbsRow?.value).toBe('43.3');
        expect(carbsRow?.valueClass).toBe('text-[#8F55A8]');
        expect(netCarbsRow?.value).toBe('33');
        expect(netCarbsRow?.valueClass).toBeUndefined();
    });
});
