import { Fragment, useEffect, useRef, useState } from 'react';
import { addons } from 'storybook/preview-api';
import LoginPage from './LoginPage';

/** Storybook preview channel — same string as `STORY_RENDERED` in Storybook core. */
const STORY_RENDERED = 'storyRendered';

/**
 * @param {{ storyId: string; children: import('react').ReactNode }} props
 */
function LoginSealRemountHost({ storyId, children }) {
    const [remountKey, setRemountKey] = useState(0);
    const skipNextRendered = useRef(true);

    useEffect(() => {
        const channel = addons.getChannel();
        if (!channel) {
            return undefined;
        }

        const onStoryRendered = (renderedId) => {
            if (renderedId !== storyId) {
                return;
            }
            if (skipNextRendered.current) {
                skipNextRendered.current = false;
                return;
            }
            setRemountKey((k) => k + 1);
        };

        channel.on(STORY_RENDERED, onStoryRendered);
        return () => channel.off(STORY_RENDERED, onStoryRendered);
    }, [storyId]);

    return <Fragment key={remountKey}>{children}</Fragment>;
}

export default {
    title: 'MealCraft/Pages/Auth/LoginPage',
    component: LoginPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Phase 1: full-viewport white (`#FFFFFF`) splash with `MealCraftLogo` `marketing-animated` (~5s hold, then 700ms crossfade), then the login form with `seal-md` mark only. **Form only** skips the wait.\n\n**Smart login redirect (server):** after submit, admins/staff → [`PortalChoicePage`](./PortalChoicePage.stories.jsx); customers with incomplete onboarding → `/onboarding/welcome`; customers with completed onboarding → `/app`. Guests start at [`WelcomePage`](./WelcomePage.stories.jsx).',
            },
        },
    },
    decorators: [
        (Story, context) => (
            <LoginSealRemountHost storyId={context.id}>
                <Story />
            </LoginSealRemountHost>
        ),
    ],
};

/** Full entry: white splash ~2.5s, then form. */
export const Default = {
    name: 'Default',
    render: () => <LoginPage />,
};

/** Form + static lockup only — `splashDurationMs={0}`. */
export const FormOnly = {
    name: 'Form only',
    render: () => <LoginPage splashDurationMs={0} />,
};
