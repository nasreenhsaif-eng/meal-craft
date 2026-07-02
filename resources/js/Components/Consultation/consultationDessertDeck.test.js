import { describe, expect, it } from 'vitest';
import {
    consultationDessertDeckForDay,
    isGreekYogurtChiaDessertMeal,
} from './ChooseYourMeals.jsx';

/** @param {string} id @param {string} title */
function dessert(id, title) {
    return { id, title, mealType: 'Dessert' };
}

describe('consultationDessertDeckForDay', () => {
    it('includes one Greek yogurt chia option alongside baked desserts for nutrient dense', () => {
        const catalog = [
            dessert('brownie', 'Chocolate Orange Brownie'),
            dessert('fruit', 'Fruit Salad Bowl'),
            dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding'),
            dessert('chia-raspberry', 'Raspberry Cacao Greek Yogurt Chia Pudding'),
            dessert('muffin', 'Saffron Pumpkin Muffin'),
        ];

        const scheduled = [dessert('brownie', 'Chocolate Orange Brownie'), dessert('fruit', 'Fruit Salad Bowl')];

        const deck = consultationDessertDeckForDay(catalog, scheduled, { preferBakedDesserts: true });

        expect(deck).toHaveLength(3);
        expect(deck.map((meal) => meal.id)).toEqual(['brownie', 'fruit', 'chia-blueberry']);
    });

    it('does not add extra chia desserts when one is already scheduled', () => {
        const catalog = [
            dessert('brownie', 'Chocolate Orange Brownie'),
            dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding'),
            dessert('chia-raspberry', 'Raspberry Cacao Greek Yogurt Chia Pudding'),
        ];

        const scheduled = [dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding')];

        const deck = consultationDessertDeckForDay(catalog, scheduled, { preferBakedDesserts: true });

        expect(deck.map((meal) => meal.id)).toEqual(['chia-blueberry', 'brownie']);
    });
});

describe('isGreekYogurtChiaDessertMeal', () => {
    it('matches Greek yogurt chia pudding titles', () => {
        expect(
            isGreekYogurtChiaDessertMeal({
                title: 'Blueberry Walnut Greek Yogurt Chia Pudding',
            }),
        ).toBe(true);
    });
});
