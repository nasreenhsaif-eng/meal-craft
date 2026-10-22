import { create } from 'storybook/theming';

/**
 * Storybook chrome theme — tokens from `resources/css/app.css` / landing palette.
 */
export const mealCraftTheme = create({
    base: 'light',
    brandTitle: 'Meal Craft',
    brandUrl: 'https://meal-craft.test',
    brandTarget: '_self',

    colorPrimary: '#6E8C47',
    colorSecondary: '#6E8C47',

    appBg: '#F9FAFB',
    appContentBg: '#FFFFFF',
    appHoverBg: '#F6F6F4',
    appPreviewBg: '#F9FAFB',
    appBorderColor: '#E5E7EB',
    appBorderRadius: 12,

    fontBase: '"Montserrat", ui-sans-serif, system-ui, sans-serif',
    fontCode: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',

    textColor: '#262A22',
    textInverseColor: '#FFFFFF',
    textMutedColor: '#555555',

    barTextColor: '#555555',
    barHoverColor: '#5A6B44',
    barSelectedColor: '#6E8C47',
    barBg: '#FFFFFF',

    buttonBg: '#FFFFFF',
    buttonBorder: '#E5E7EB',
    booleanBg: '#F2F2F2',
    booleanSelectedBg: '#6E8C47',

    inputBg: '#FFFFFF',
    inputBorder: '#E5E7EB',
    inputTextColor: '#262A22',
    inputBorderRadius: 12,
});
