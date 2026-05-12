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
            const mode = context.globals.canvasBackground ?? 'grey';
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

        /** MealCraft Identity: Brand Marks → axis stories → Vertical → tier focus (not A–Z). */
        options: {
            storySort: (a, b) => {
                const ta = a.title ?? '';
                const tb = b.title ?? '';

                const topOrder = [
                    'MealCraft/Identity',
                    'MealCraft/Atoms',
                    'MealCraft/Meal System',
                    'MealCraft/Components',
                    'MealCraft/Pages',
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

                const identityBase = 'MealCraft/Identity/';
                const inIdentityA = ta.startsWith(identityBase);
                const inIdentityB = tb.startsWith(identityBase);
                if (inIdentityA && inIdentityB) {
                    const identityOrder = [
                        `${identityBase}Horizontal Lockups`,
                        `${identityBase}Vertical Lockups`,
                        `${identityBase}Brand Marks`,
                        `${identityBase}Animated`,
                        `${identityBase}All Variants/Minimal`,
                        `${identityBase}All Variants/Smart`,
                        `${identityBase}All Variants/Marketing`,
                    ];
                    const ia = identityOrder.indexOf(ta);
                    const ib = identityOrder.indexOf(tb);
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const actionAtomsBase = 'MealCraft/Atoms/Buttons & Links/';
                const inActionA = ta.startsWith(actionAtomsBase);
                const inActionB = tb.startsWith(actionAtomsBase);
                if (inActionA && inActionB) {
                    const actionOrder = [
                        `${actionAtomsBase}Buttons`,
                        `${actionAtomsBase}NavButton`,
                        `${actionAtomsBase}Icons/RoundIconButton`,
                        `${actionAtomsBase}Icons/SquareCheckbox`,
                        `${actionAtomsBase}TextLink`,
                    ];
                    const ia = actionOrder.indexOf(ta);
                    const ib = actionOrder.indexOf(tb);
                    const ra = ia === -1 ? 1000 : ia;
                    const rb = ib === -1 ? 1000 : ib;
                    if (ra !== rb) {
                        return ra - rb;
                    }
                }

                const componentsBase = 'MealCraft/Components/';
                const inComponentsA = ta.startsWith(componentsBase);
                const inComponentsB = tb.startsWith(componentsBase);
                if (inComponentsA && inComponentsB) {
                    const componentsOrder = [
                        `${componentsBase}Calendar`,
                        `${componentsBase}MacroGrid`,
                        `${componentsBase}MealCard`,
                    ];
                    const ia = componentsOrder.indexOf(ta);
                    const ib = componentsOrder.indexOf(tb);
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
