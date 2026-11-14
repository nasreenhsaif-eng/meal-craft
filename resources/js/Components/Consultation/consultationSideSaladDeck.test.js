import { describe, expect, it } from 'vitest';
import { consultationSideSaladDeckForDay } from './ChooseYourMeals.jsx';

/** @param {string} id @param {string} title */
function sideSalad(id, title) {
    return { id, title, mealType: 'Side salad' };
}

describe('consultationSideSaladDeckForDay', () => {
    it('shows up to two side salad options', () => {
        const catalog = [
            sideSalad('a', 'Kimchi Purslane Side Salad'),
            sideSalad('b', 'Tahini Purslane Pepper Salad'),
            sideSalad('c', 'Sauerkraut & Rocca Salad'),
            sideSalad('d', 'Citrus Beet Arugula Salad'),
        ];

        const scheduled = [sideSalad('a', 'Kimchi Purslane Side Salad')];

        const deck = consultationSideSaladDeckForDay(catalog, scheduled);

        expect(deck).toHaveLength(2);
        expect(deck.map((meal) => meal.id)).toEqual(['a', 'b']);
    });

    it('matches admin mealType Side Salad (title case)', () => {
        const catalog = [{ id: 'a', title: 'Kimchi Purslane Side Salad', mealType: 'Side Salad' }];

        expect(consultationSideSaladDeckForDay(catalog, []).map((meal) => meal.id)).toEqual(['a']);
    });
});
