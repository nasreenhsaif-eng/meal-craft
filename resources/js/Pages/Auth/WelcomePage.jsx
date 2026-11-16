import { useEffect, useState } from 'react';
import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import Button from '../../Components/Atoms/Button/Button.jsx';

/** Content width inside the onboarding phone frame (≈430px). */
const contentWidthClass = 'mx-auto w-full max-w-full px-1 text-center';

/**
 * Public welcome landing — geometric seal hero and a single entry CTA.
 * Sized for the same phone frame as customer onboarding screens.
 *
 * @param {{ loginHref?: string; onGetStarted?: () => void }} props
 */
export default function WelcomePage({ loginHref = '/login', onGetStarted }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const frame = requestAnimationFrame(() => setVisible(true));

        return () => cancelAnimationFrame(frame);
    }, []);

    return (
        <div className="flex h-full min-h-0 w-full flex-1 flex-col items-center justify-center bg-white px-2 py-6 antialiased">
            <div
                className={`${contentWidthClass} transition-opacity duration-1000 ease-out ${
                    visible ? 'opacity-100' : 'opacity-0'
                }`}
            >
                <MealCraftLogo
                    variant="vertical-marketing"
                    width={280}
                    className="mx-auto h-auto max-w-full"
                    alt="Meal Craft"
                />

                <h1 className="mt-8 font-montserrat text-2xl font-bold tracking-tight text-[#262A22]">
                    Welcome to Meal Craft
                </h1>

                <p className="mx-auto mt-3 max-w-prose text-base font-medium leading-relaxed text-[#4B5563]">
                    Personalized meal planning rooted in clean, geometric simplicity.
                </p>

                <Button
                    label="Get Started"
                    variant="primary"
                    className="mt-10 flex w-full justify-center whitespace-nowrap"
                    onClick={() => {
                        if (onGetStarted) {
                            onGetStarted();
                            return;
                        }

                        window.location.href = loginHref;
                    }}
                />
            </div>
        </div>
    );
}
