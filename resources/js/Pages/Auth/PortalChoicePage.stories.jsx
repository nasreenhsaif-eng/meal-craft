import { fn } from 'storybook/test';
import PortalChoicePage from './PortalChoicePage';

export default {
    title: 'MealCraft/Pages/Auth/PortalChoicePage',
    component: PortalChoicePage,
    parameters: {
        layout: 'fullscreen',
        viewport: {
            defaultViewport: 'mobile1',
        },
        docs: {
            description: {
                component:
                    'Post-login workspace picker at `/login/portal-choice` — **admin/staff only**. Shown after successful login instead of jumping straight to the admin dashboard. **Customer Onboarding / Experience** previews `/onboarding/welcome`; **Admin Portal Dashboard** goes to `/admin/dashboard`. Standard customers never see this screen.',
            },
        },
    },
    args: {
        onSelectCustomerExperience: fn(),
        onSelectAdminPortal: fn(),
    },
};

/** Admin interstitial immediately after smart login redirect. */
export const Default = {
    name: 'Default',
};

/** Greeting line when the signed-in admin name is available. */
export const WithUserName = {
    name: 'With user name',
    args: {
        userName: 'Nasreen',
    },
};
