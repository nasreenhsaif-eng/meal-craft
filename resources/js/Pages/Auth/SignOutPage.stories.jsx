import SignOutPage from './SignOutPage.jsx';

export default {
    title: 'Design System/06. Pages/Auth/SignOutPage',
    component: SignOutPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Sign-out / session gate in the same phone frame as login & join. Authenticated users see Log out; guests see signup / log in.',
            },
        },
    },
};

/** Signed-in user — Log out CTA. */
export const SignedIn = {
    name: 'Signed in',
    render: () => (
        <SignOutPage
            authenticated
            email="nasreen.h.saif@gmail.com"
            onLogout={() => {}}
        />
    ),
};

/** Guest — signup / log in. */
export const Guest = {
    name: 'Guest',
    render: () => (
        <SignOutPage
            authenticated={false}
            onJoin={() => {}}
            onLogin={() => {}}
        />
    ),
};
