import { addons } from 'storybook/manager-api';
import { mealCraftTheme } from './mealCraftTheme.js';

addons.setConfig({
    theme: mealCraftTheme,
});
