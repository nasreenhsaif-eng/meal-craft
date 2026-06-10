import { useEffect, useState } from 'react';
import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import Button from '../../Components/Atoms/Button/Button.jsx';

/** Same responsive width band as `LoginPage` — 90% on mobile, capped on desktop. */
const contentWidthClass = 'mx-auto w-full max-w-[90%] md:max-w-[440px]';

/**
 * Public welcome landing — geometric seal hero and a single entry CTA.
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
        <div className="relative min-h-screen w-screen bg-white">
            <main className="flex min-h-screen w-screen flex-col items-center justify-center px-2 py-8 sm:px-4 md:px-6 antialiased">
                <div
                    className={`${contentWidthClass} text-center transition-opacity duration-1000 ease-out ${
                        visible ? 'opacity-100' : 'opacity-0'
                    }`}
                >
                    <MealCraftLogo
                        variant="vertical-marketing"
                        width={400}
                        className="mx-auto h-auto max-w-full"
                        alt="Meal Craft"
                    />


                    <h1 className="mt-8 font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                        Welcome to Meal Craft
                    </h1>

                    <p className="mx-auto mt-3 max-w-prose text-base font-medium leading-relaxed text-[#4B5563] sm:text-lg">
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
            </main>
        </div>
    );
}
