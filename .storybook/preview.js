import '../resources/css/app.css';
import './preview.css';
import React from 'react';

/** @type { import('@storybook/react-vite').Preview } */
const preview = {
    globalTypes: {
        canvasBackground: {
            description: 'Preview canvas (helps white/low-contrast logos)',
            defaultValue: 'grey',
            toolbar: {
                title: 'Canvas',
                icon: 'mirror',
                items: [
                    { value: 'grey', title: 'App grey (#F9FAFB)' },
                    { value: 'white', title: 'White' },
                    { value: 'dark', title: 'Dark' },
                ],
                dynamicTitle: true,
            },
        },
    },
    decorators: [
        (Story, context) => {
            const mode = context.parameters.canvasBackground ?? context.globals.canvasBackground ?? 'grey';
            const backgroundColor =
                mode === 'dark' ? '#111827' : mode === 'white' ? '#FFFFFF' : '#F9FAFB';
            const color = mode === 'dark' ? '#e5e7eb' : '#374151';

            return React.createElement(
                'div',
                {
                    style: {
                        position: 'fixed',
                        inset: 0,
                        backgroundColor,
                        color,
                        boxSizing: 'border-box',
                        width: '100%',
                        minWidth: '100%',
                        maxWidth: 'none',
                        minHeight: '100vh',
                        height: '100%',
                        margin: 0,
                        padding: 0,
                        overflow: 'auto',
                    },
                },
                React.createElement(Story),
            );
        },
    ],
    parameters: {
        /** Full-bleed preview (avoids default `centered` max-width column on every story). */
        layout: 'fullscreen',

        controls: {
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },

        a11y: {
            test: 'todo',
        },

        /** Design System: Foundations → Atoms → Molecules → Organisms → Templates. */
        options: {
            storySort: (a, b) => {
                const ta = a.title ?? '';
                const tb = b.title ?? '';

                const topOrder = [
                    'Design System/01. Foundations',
                    'Design System/02. Atoms',
                    'Design System/03. Molecules',
                    'Design System/04. Organisms',
                    'Design System/05. Templates & Pages',
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

                const foundationsBase = 'Design System/01. Foundations/';
                const inFoundationsA = ta.startsWith(foundationsBase);
                const inFoundationsB = tb.startsWith(foundationsBase);
                if (inFoundationsA && inFoundationsB) {
                    const foundationsOrder = [
                        `${foundationsBase}Colors`,
                        `${foundationsBase}Typography`,
                        `${foundationsBase}Spacing & Elevation`,
                        `${foundationsBase}Icons`,
                        `${foundationsBase}Logos/Horizontal Lockups`,
                        `${foundationsBase}Logos/Vertical Lockups`,
                        `${foundationsBase}Logos/Brand Marks`,
                        `${foundationsBase}Logos/Animated`,
                        `${foundationsBase}Logos/All Variants/Minimal`,
                        `${foundationsBase}Logos/All Variants/Smart`,
                        `${foundationsBase}Logos/All Variants/Marketing`,
                    ];
                    const ia = foundationsOrder.indexOf(ta);
                    const ib = foundationsOrder.indexOf(tb);
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const atomsBase = 'Design System/02. Atoms/';
                const inAtomsA = ta.startsWith(atomsBase);
                const inAtomsB = tb.startsWith(atomsBase);
                if (inAtomsA && inAtomsB) {
                    const atomsOrder = [
                        `${atomsBase}Button`,
                        `${atomsBase}Button/Pill`,
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
                const inMoleculesA = ta.startsWith(moleculesBase);
                const inMoleculesB = tb.startsWith(moleculesBase);
                if (inMoleculesA && inMoleculesB) {
                    const moleculesOrder = [
                        `${moleculesBase}Dropdown`,
                        `${moleculesBase}Dropdown/FoodFilterMultiSelect`,
                        `${moleculesBase}Form and pickers/Calendar`,
                        `${moleculesBase}Form and pickers/MacroGrid`,
                        `${moleculesBase}Form and pickers/WheelDatePicker`,
                        `${moleculesBase}Form and pickers/Weight`,
                        `${moleculesBase}Form and pickers/Height`,
                        `${moleculesBase}Form and pickers/DairyFreeFilterNotice`,
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
                const inOrganismsA = ta.startsWith(organismsBase);
                const inOrganismsB = tb.startsWith(organismsBase);
                if (inOrganismsA && inOrganismsB) {
                    const organismsOrder = [
                        `${organismsBase}Header Navbar/AdminLayout`,
                        `${organismsBase}DataCard/MealCard`,
                        `${organismsBase}DataCard/StackedDeckCarousel`,
                        `${organismsBase}DataCard/PartnerKitchenCard`,
                        `${organismsBase}DataCard/MealDetailView`,
                        `${organismsBase}Table`,
                        `${organismsBase}ChooseYourMeals`,
                        `${organismsBase}ProtocolSelectedMeals`,
                    ];
                    const ia = organismsOrder.findIndex((t) => ta === t || ta.startsWith(`${t}/`));
                    const ib = organismsOrder.findIndex((t) => tb === t || tb.startsWith(`${t}/`));
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const pagesBase = 'Design System/05. Templates & Pages/';
                const inPagesA = ta.startsWith(pagesBase);
                const inPagesB = tb.startsWith(pagesBase);
                if (inPagesA && inPagesB) {
                    const pagesOrder = [
                        `${pagesBase}DashboardLayout`,
                        `${pagesBase}SettingsView`,
                        `${pagesBase}Auth`,
                        `${pagesBase}Onboarding`,
                        `${pagesBase}Admin`,
                        `${pagesBase}Consultation`,
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
