import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerOnboardingPhoneFrame from '../../Components/Molecules/Customer/CustomerOnboardingPhoneFrame.jsx';

/** Content width inside the onboarding phone frame. */
const contentWidthClass = 'mx-auto w-full max-w-full px-1 text-center';

/**
 * Sign-out / session gate — same phone frame as login & join.
 *
 * @param {{
 *   authenticated?: boolean;
 *   email?: string;
 *   logoutAction?: string;
 *   csrfToken?: string;
 *   joinHref?: string;
 *   loginHref?: string;
 *   onLogout?: () => void;
 *   onJoin?: () => void;
 *   onLogin?: () => void;
 * }} props
 */
export default function SignOutPage({
    authenticated = false,
    email = '',
    logoutAction = '/logout',
    csrfToken = '',
    joinHref = '/join',
    loginHref = '/login',
    onLogout,
    onJoin,
    onLogin,
}) {
    const handleLogout = (event) => {
        if (onLogout) {
            event.preventDefault();
            onLogout();
        }
    };

    return (
        <CustomerOnboardingPhoneFrame>
            <div className="relative flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden bg-white">
                <main className="relative z-0 flex min-h-0 flex-1 flex-col items-center justify-center overflow-y-auto overscroll-contain px-3 py-6">
                    <div className={`flex w-full flex-col items-center ${contentWidthClass}`}>
                        <MealCraftLogo
                            variant="vertical-marketing"
                            width={280}
                            className="mx-auto h-auto max-w-full"
                            alt="Meal Craft"
                        />

                        {authenticated ? (
                            <>
                                <h1 className="mt-8 font-montserrat text-2xl font-bold tracking-tight text-heading">
                                    Sign out
                                </h1>
                                <p className="mx-auto mt-3 max-w-prose text-base font-medium leading-relaxed text-body">
                                    {email
                                        ? `You are signed in as ${email}. Sign out to create a customer account or sign in with a different account.`
                                        : 'Sign out to create a customer account or sign in with a different account.'}
                                </p>

                                <form
                                    method="POST"
                                    action={logoutAction}
                                    className="mt-10 flex w-full justify-center"
                                    onSubmit={handleLogout}
                                >
                                    {csrfToken ? (
                                        <input type="hidden" name="_token" value={csrfToken} />
                                    ) : null}
                                    <Button
                                        type="submit"
                                        label="Log out"
                                        variant="primary"
                                        className="w-full"
                                        data-test="logout-button"
                                    />
                                </form>
                            </>
                        ) : (
                            <>
                                <h1 className="mt-8 font-montserrat text-2xl font-bold tracking-tight text-heading">
                                    You are not signed in
                                </h1>
                                <p className="mx-auto mt-3 max-w-prose text-base font-medium leading-relaxed text-body">
                                    No account session was found in this browser. You can sign up as a customer or log
                                    in.
                                </p>

                                <div className="mt-10 flex w-full flex-col gap-3">
                                    <Button
                                        type="button"
                                        label="Customer signup"
                                        variant="primary"
                                        className="w-full"
                                        onClick={() => {
                                            if (onJoin) {
                                                onJoin();
                                                return;
                                            }

                                            window.location.href = joinHref;
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        label="Log in"
                                        variant="ghost"
                                        className="w-full"
                                        onClick={() => {
                                            if (onLogin) {
                                                onLogin();
                                                return;
                                            }

                                            window.location.href = loginHref;
                                        }}
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </main>
            </div>
        </CustomerOnboardingPhoneFrame>
    );
}
