import '../resources/css/app.css';
import './preview.css';
import React from 'react';
import { mealCraftTheme } from './mealCraftTheme.js';

/** Canvas modes — same tokens as `resources/css/app.css` / landing. */
const CANVAS = {
    page: { backgroundColor: '#F6F6F4', color: '#262A22' },
    white: { backgroundColor: '#FFFFFF', color: '#262A22' },
    ink: { backgroundColor: '#262A22', color: '#F6F6F4' },
};

/** @type { import('@storybook/react-vite').Preview } */
const preview = {
    globalTypes: {
        canvasBackground: {
            description: 'Preview canvas (design-system page / white / ink)',
            defaultValue: 'page',
            toolbar: {
                title: 'Canvas',
                icon: 'mirror',
                items: [
                    { value: 'page', title: 'Soft surface (#F6F6F4)' },
                    { value: 'white', title: 'Canvas (White)' },
                    { value: 'ink', title: 'Heading (#262A22)' },
                ],
                dynamicTitle: true,
            },
        },
    },
    decorators: [
        (Story, context) => {
            const mode = context.parameters.canvasBackground ?? context.globals.canvasBackground ?? 'page';
            const resolved =
                mode === 'white'
                    ? 'white'
                    : mode === 'ink' || mode === 'dark'
                      ? 'ink'
                      : 'page';
            const { backgroundColor, color } = CANVAS[resolved];

            // Avoid position:fixed + overflow:auto — it traps touch/wheel scroll inside
            // Storybook's mobile viewport iframe. Let the document scroll instead.
            return React.createElement(
                'div',
                {
                    style: {
                        boxSizing: 'border-box',
                        width: '100%',
                        minWidth: '100%',
                        maxWidth: 'none',
                        minHeight: '100%',
                        margin: 0,
                        padding: 0,
                        backgroundColor,
                        color,
                        fontFamily: 'var(--font-family-font-2), ui-sans-serif, system-ui, sans-serif',
                    },
                },
                React.createElement(Story),
            );
        },
    ],
    parameters: {
        /** Full-bleed preview (avoids default `centered` max-width column on every story). */
        layout: 'fullscreen',

        docs: {
            theme: mealCraftTheme,
        },

        backgrounds: {
            disable: true,
        },

        controls: {
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },

        a11y: {
            test: 'todo',
        },

        /** Design System: Tokens → Atoms → Molecules → Organisms → Templates → Pages. */
        options: {
            storySort: (a, b) => {
                const ta = a.title ?? '';
                const tb = b.title ?? '';

                const topOrder = [
                    'Design System/01. Tokens',
                    'Design System/02. Atoms',
                    'Design System/03. Molecules',
                    'Design System/04. Organisms',
                    'Design System/05. Templates',
                    'Design System/06. Pages',
                ];

                const topOf = (t) => {
                    const parts = t.split('/');
                    return parts.length >= 2 ? `${parts[0]}/${parts[1]}` : t;
                };

                const iaTop = topOrder.indexOf(topOf(ta));
                const ibTop = topOrder.indexOf(topOf(tb));
                const raTop = iaTop === -1 ? 1000 : iaTop;
                const rbTop = ibTop === -1 ? 1000 : ibTop;
                if (raTop !== rbTop) {
                    return raTop - rbTop;
                }

                const tokensBase = 'Design System/01. Tokens/';
                if (ta.startsWith(tokensBase) && tb.startsWith(tokensBase)) {
                    const tokensOrder = [
                        `${tokensBase}Colors`,
                        `${tokensBase}Typography`,
                        `${tokensBase}Spacing & Elevation`,
                        `${tokensBase}Icons`,
                        `${tokensBase}Logos/Horizontal Lockups`,
                        `${tokensBase}Logos/Vertical Lockups`,
                        `${tokensBase}Logos/Brand Marks`,
                        `${tokensBase}Logos/Animated`,
                        `${tokensBase}Logos/All Variants/Minimal`,
                        `${tokensBase}Logos/All Variants/Smart`,
                        `${tokensBase}Logos/All Variants/Marketing`,
                    ];
                    const ia = tokensOrder.indexOf(ta);
                    const ib = tokensOrder.indexOf(tb);
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const atomsBase = 'Design System/02. Atoms/';
                if (ta.startsWith(atomsBase) && tb.startsWith(atomsBase)) {
                    const atomsOrder = [
                        `${atomsBase}Button`,
                        `${atomsBase}Button/Tab`,
                        `${atomsBase}Button/PrimaryButton`,
                        `${atomsBase}Button/NavButton`,
                        `${atomsBase}Button/GenderOptionButton`,
                        `${atomsBase}Button/OnboardingOptionButton`,
                        `${atomsBase}Button/IconButton`,
                        `${atomsBase}Button/DragHandle`,
                        `${atomsBase}Button/SquareCheckbox`,
                        `${atomsBase}Button/TextLink`,
                        `${atomsBase}Input`,
                        `${atomsBase}Badge/CategoryBadges`,
                        `${atomsBase}Badge/TimeBadge`,
                        `${atomsBase}Badge/NutrientBadge`,
                        `${atomsBase}Badge/DietaryTags`,
                        `${atomsBase}Badge/SafetyAlerts`,
                        `${atomsBase}Badge/ProtocolTags`,
                        `${atomsBase}Badge/PreferenceTags`,
                        `${atomsBase}Badge/SelectionCheckBadge`,
                        `${atomsBase}Badge/FoodFilterPill`,
                    ];
                    const atomIndex = (title) => {
                        let best = -1;
                        let bestLen = -1;
                        atomsOrder.forEach((t, i) => {
                            if (title === t || title.startsWith(`${t}/`)) {
                                if (t.length > bestLen) {
                                    best = i;
                                    bestLen = t.length;
                                }
                            }
                        });

                        return best === -1 ? 1000 : best;
                    };
                    const ra = atomIndex(ta);
                    const rb = atomIndex(tb);
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const moleculesBase = 'Design System/03. Molecules/';
                if (ta.startsWith(moleculesBase) && tb.startsWith(moleculesBase)) {
                    const moleculesOrder = [
                        `${moleculesBase}Dropdown`,
                        `${moleculesBase}Dropdown/FoodFilterMultiSelect`,
                        `${moleculesBase}Form and pickers/Calendar`,
                        `${moleculesBase}Form and pickers/MacroGrid`,
                        `${moleculesBase}Form and pickers/WheelDatePicker`,
                        `${moleculesBase}Form and pickers/Weight`,
                        `${moleculesBase}Form and pickers/Height`,
                        `${moleculesBase}Form and pickers/DairyFreeFilterNotice`,
                        `${moleculesBase}Customer/CustomerAppBottomNav`,
                    ];
                    const ia = moleculesOrder.findIndex((t) => ta === t || ta.startsWith(`${t}/`));
                    const ib = moleculesOrder.findIndex((t) => tb === t || tb.startsWith(`${t}/`));
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const organismsBase = 'Design System/04. Organisms/';
                if (ta.startsWith(organismsBase) && tb.startsWith(organismsBase)) {
                    const organismsOrder = [
                        `${organismsBase}DataCard/AdminMealCard`,
                        `${organismsBase}DataCard/CustomerMealCard`,
                        `${organismsBase}DataCard/StackedDeckCarousel`,
                        `${organismsBase}DataCard/PartnerKitchenCard`,
                        `${organismsBase}DataCard/MealDetailView`,
                        `${organismsBase}Consultation/ChooseYourMeals`,
                        `${organismsBase}Consultation/ProtocolSelectedMeals`,
                        `${organismsBase}Consultation/ProtocolFixedChoiceSides`,
                        `${organismsBase}Consultation/DayNutritionalSummaryPanel`,
                        `${organismsBase}Consultation/CaloricThresholdNotice`,
                        `${organismsBase}Table`,
                    ];
                    const ia = organismsOrder.findIndex((t) => ta === t || ta.startsWith(`${t}/`));
                    const ib = organismsOrder.findIndex((t) => tb === t || tb.startsWith(`${t}/`));
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const templatesBase = 'Design System/05. Templates/';
                if (ta.startsWith(templatesBase) && tb.startsWith(templatesBase)) {
                    const templatesOrder = [
                        `${templatesBase}AdminLayout`,
                        `${templatesBase}Admin/Logistics`,
                    ];
                    const ia = templatesOrder.findIndex((t) => ta === t || ta.startsWith(`${t}/`));
                    const ib = templatesOrder.findIndex((t) => tb === t || tb.startsWith(`${t}/`));
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const pagesBase = 'Design System/06. Pages/';
                if (ta.startsWith(pagesBase) && tb.startsWith(pagesBase)) {
                    const pagesOrder = [
                        `${pagesBase}Landing`,
                        `${pagesBase}Auth`,
                        `${pagesBase}Onboarding`,
                        `${pagesBase}App`,
                        `${pagesBase}Checkout`,
                        `${pagesBase}Crafted for you`,
                        `${pagesBase}Admin`,
                        `${pagesBase}Mocks`,
                    ];
                    const ia = pagesOrder.findIndex((t) => ta === t || ta.startsWith(`${t}/`));
                    const ib = pagesOrder.findIndex((t) => tb === t || tb.startsWith(`${t}/`));
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                return ta.localeCompare(tb, undefined, { sensitivity: 'base' });
            },
        },
    },
};

export default preview;
