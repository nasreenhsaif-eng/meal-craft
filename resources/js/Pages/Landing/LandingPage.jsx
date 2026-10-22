import { useEffect, useState } from 'react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';

/** Show the floating control once the visitor has left the hero. */
const BACK_TO_TOP_OFFSET_PX = 480;

const NAV = [
    { href: '#top', label: 'Home' },
    { href: '#philosophy', label: 'Philosophy' },
    { href: '#weekly-menu', label: 'Weekly Menu' },
    { href: '#meal-plans', label: 'Meal Plans' },
    { href: '#sickle-cell', label: 'Sickle Cell Anemia Center' },
];

const PLANS = [
    {
        name: 'Balanced',
        tone: 'green',
        description:
            'A well-rounded approach to anti-inflammatory eating for overall wellness and vitality.',
        points: ['Nutrient-dense meals', 'Sustainable energy', 'Whole food focus'],
    },
    {
        name: 'Ketobiotic',
        tone: 'gold',
        description: 'Low-carb, high-fat meals supporting ketosis and gut health for metabolic wellness.',
        points: ['Ketogenic principles', 'Gut microbiome support', 'Metabolic optimization'],
    },
    {
        name: 'Cycle Sync',
        tone: 'purple',
        description:
            "Hormone-balancing nutrition tailored to women's monthly cycles for optimal well-being.",
        points: ['Hormonal balance', 'Phase-specific meals', 'Energy optimization'],
    },
    {
        name: 'Sickle Cell Warrior',
        tone: 'red',
        description:
            'Specialized nutrition supporting blood health and reducing inflammation for sickle cell warriors.',
        points: ['Blood health support', 'Crisis prevention', 'Targeted nutrients'],
    },
];

const TONE = {
    green: {
        border: 'border-brand-primary',
        icon: 'bg-brand-primary',
        check: 'text-brand-primary',
    },
    gold: {
        border: 'border-brand-secondary',
        icon: 'bg-brand-secondary',
        check: 'text-brand-secondary',
    },
    purple: {
        border: 'border-brand-accent',
        icon: 'bg-brand-accent',
        check: 'text-brand-accent',
    },
    red: {
        border: 'border-status-error',
        icon: 'bg-status-error',
        check: 'text-status-error',
    },
};

/**
 * @param {{ className?: string }} props
 */
function LeafMark({ className = '' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M12 20c4.5-3.2 7-7.2 7-11.2C19 5.2 16.2 3 12 3 7.8 3 5 5.2 5 8.8 5 12.8 7.5 16.8 12 20Z"
                stroke="currentColor"
                strokeWidth="1.6"
            />
            <path d="M12 20V8" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function CheckIcon({ className = '' }) {
    return (
        <svg className={className} width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden>
            <circle cx="9" cy="9" r="8" stroke="currentColor" strokeWidth="1.5" />
            <path
                d="M5.2 9.2 7.6 11.5 12.8 6.4"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ label: string; className?: string }} props
 */
function PhotoPlaceholder({ label, className = '' }) {
    return (
        <div
            role="img"
            aria-label={label}
            className={[
                'flex items-center justify-center bg-[color-mix(in_srgb,var(--brand-secondary)_18%,white)] px-4 text-center font-montserrat text-sm font-semibold text-brand-primary-pressed',
                className,
            ].join(' ')}
        >
            {label}
        </div>
    );
}

/**
 * @param {{
 *   children: import('react').ReactNode;
 *   className?: string;
 *   onClick?: () => void;
 * }} props
 */
function PillButton({ children, className = '', onClick, href }) {
    const classes = [
        'inline-flex items-center justify-center gap-2 rounded-full px-6 py-2.5',
        'font-montserrat text-sm font-semibold',
        'transition-colors duration-200',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2',
        className,
    ].join(' ');

    if (href) {
        return (
            <a href={href} onClick={onClick} className={classes}>
                {children}
            </a>
        );
    }

    return (
        <button type="button" onClick={onClick} className={classes}>
            {children}
        </button>
    );
}

/**
 * @param {{ icon: import('react').ReactNode; title: string; children: import('react').ReactNode }} props
 */
function TechCard({ icon, title, children }) {
    return (
        <article className="rounded-[16px] border border-border-light bg-white p-6 shadow-sm">
            <div className="flex h-10 w-10 items-center justify-center rounded-[10px] bg-tag-magnesium text-white">
                {icon}
            </div>
            <h3 className="mt-4 font-montserrat text-lg font-bold">{title}</h3>
            <div className="mt-2 text-sm leading-relaxed text-grey-33">{children}</div>
        </article>
    );
}

/**
 * Floating control that returns the visitor to the hero after they scroll past it.
 */
function BackToTopButton() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const onScroll = () => {
            setVisible(window.scrollY > BACK_TO_TOP_OFFSET_PX);
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <button
            type="button"
            aria-label="Back to top"
            onClick={() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }}
            className={[
                'fixed bottom-6 right-5 z-30 inline-flex items-center gap-2 rounded-full',
                'bg-brand-primary px-4 py-2.5 text-white shadow-md',
                'font-montserrat text-sm font-semibold',
                'transition-colors duration-200 hover:bg-brand-primary-pressed',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2',
            ].join(' ')}
        >
            <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" aria-hidden>
                <path
                    d="M8 3.5 3.5 8M8 3.5 12.5 8M8 3.5v9"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
            Back to top
        </button>
    );
}

/**
 * Public marketing landing — full desktop width.
 *
 * @param {{
 *   loginHref?: string;
 *   onStartNow?: () => void;
 *   onLearnMore?: (planName: string) => void;
 * }} props
 */
export default function LandingPage({ loginHref = '/login', onStartNow, onLearnMore }) {
    return (
        <div id="top" className="min-h-screen bg-white font-body text-[#262A22]">
            <header className="sticky top-0 z-20 border-b border-border-light bg-white/95 backdrop-blur-sm">
                <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4">
                    <a href="#top" className="shrink-0" aria-label="Meal Craft home">
                        <MealCraftLogo variant="smart" width={168} alt="Meal Craft" />
                    </a>
                    <nav aria-label="Landing" className="flex flex-wrap items-center gap-2">
                        {NAV.map((item) => (
                            <Button
                                key={item.href}
                                label={item.label}
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    document.querySelector(item.href)?.scrollIntoView({ behavior: 'smooth' });
                                }}
                            />
                        ))}
                    </nav>
                </div>
            </header>

            <img
                src="/images/landing/kitchen.png"
                alt="Sunlit kitchen with a wooden island, copper lights, and herbs"
                className="aspect-[16/7] min-h-40 w-full object-cover"
            />

            <section className="mx-auto grid w-full max-w-6xl items-center gap-10 px-5 py-16 lg:grid-cols-[1.2fr_0.8fr]">
                <div>
                    <h1 className="font-montserrat text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl">
                        Healing
                        <br />
                        Through
                        <br />
                        <span className="text-brand-primary">Whole Foods</span>
                    </h1>
                    <p className="mt-5 max-w-prose text-base leading-relaxed text-grey-33">
                        Create personalized anti-inflammatory meal plans from whole foods with our clean food
                        philosophy and zero tolerance for harmful additives.
                    </p>
                    <PillButton
                        className="mt-8 bg-brand-primary text-white hover:bg-brand-primary-pressed"
                        href={onStartNow ? undefined : loginHref}
                        onClick={onStartNow}
                    >
                        Start now
                        <span aria-hidden>›</span>
                    </PillButton>
                </div>
                <div className="flex justify-center">
                    <MealCraftLogo variant="seal-lg" width={240} alt="Craft Seal" />
                </div>
            </section>

            <section className="border-y border-border-light bg-bg-page" aria-label="Standards">
                <dl className="mx-auto grid w-full max-w-6xl grid-cols-3 gap-4 px-5 py-8 text-center">
                    {[
                        ['4', 'Meal Plans'],
                        ['100%', 'Whole Foods'],
                        ['0', 'Additives'],
                    ].map(([value, label]) => (
                        <div key={label}>
                            <dt className="font-montserrat text-3xl font-bold">{value}</dt>
                            <dd className="mt-1 font-montserrat text-xs font-medium text-grey-33">{label}</dd>
                        </div>
                    ))}
                </dl>
            </section>

            <section className="bg-bg-page px-5 py-16">
                <div className="mx-auto w-full max-w-6xl">
                    <h2 className="text-center font-montserrat text-3xl font-bold tracking-tight">
                        Smart Kitchen Technology
                    </h2>
                    <p className="mx-auto mt-3 max-w-xl text-center text-grey-33">
                        Advanced tools to make anti-inflammatory eating effortless and enjoyable.
                    </p>
                    <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <TechCard
                            title="Personalized Meal Plans"
                            icon={
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden>
                                    <rect x="2" y="3" width="14" height="13" rx="2" stroke="currentColor" strokeWidth="1.5" />
                                    <path d="M2 7h14M6 3v4M12 3v4" stroke="currentColor" strokeWidth="1.5" />
                                </svg>
                            }
                        >
                            AI-powered meal planning tailored to your specific health goals and dietary needs.
                        </TechCard>
                        <TechCard
                            title="Non-Toxic Kitchen Guide"
                            icon={
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden>
                                    <path d="M9 2.5 14.5 5v4.2c0 3.2-2.2 5.4-5.5 6.3-3.3-.9-5.5-3.1-5.5-6.3V5L9 2.5Z" stroke="currentColor" strokeWidth="1.5" />
                                </svg>
                            }
                        >
                            <p>
                                Audit your kitchen tools and discover which items may be leaching harmful chemicals
                                into your food.
                            </p>
                            <p className="mt-4 font-montserrat text-sm font-semibold text-tag-magnesium">
                                Take the Quiz ›
                            </p>
                        </TechCard>
                        <TechCard
                            title="Ingredient Scanner"
                            icon={
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden>
                                    <circle cx="8" cy="8" r="4.5" stroke="currentColor" strokeWidth="1.5" />
                                    <path d="M11.5 11.5 15 15" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                </svg>
                            }
                        >
                            Instantly identify harmful additives and verify food quality with our smart scanner.
                            Coming soon feature.
                        </TechCard>
                        <TechCard
                            title="Health Tracking"
                            icon={
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden>
                                    <path
                                        d="M9 15s-5.2-3.2-5.2-7A3 3 0 0 1 9 6a3 3 0 0 1 5.2 2c0 3.8-5.2 7-5.2 7Z"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                    />
                                </svg>
                            }
                        >
                            Monitor inflammation markers and track your wellness journey with detailed insights.
                        </TechCard>
                    </div>
                </div>
            </section>

            <section id="meal-plans" className="px-5 py-16">
                <div className="mx-auto w-full max-w-6xl">
                    <h2 className="text-center font-montserrat text-3xl font-bold tracking-tight sm:text-4xl">
                        Four Anti-inflammatory Meal Plans
                    </h2>
                    <p className="mx-auto mt-3 max-w-2xl text-center text-grey-33">
                        Each plan is carefully crafted to address specific health needs while maintaining our
                        zero-tolerance standard for harmful additives.
                    </p>
                    <div className="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {PLANS.map((plan) => {
                            const tone = TONE[plan.tone];

                            return (
                                <article
                                    key={plan.name}
                                    className={[
                                        'flex flex-col rounded-[20px] border-t-4 bg-white p-5 shadow-sm',
                                        tone.border,
                                    ].join(' ')}
                                >
                                    <div
                                        className={[
                                            'flex h-12 w-12 items-center justify-center rounded-[12px] text-white',
                                            tone.icon,
                                        ].join(' ')}
                                    >
                                        <LeafMark className="h-6 w-6" />
                                    </div>
                                    <h3 className="mt-5 font-montserrat text-xl font-bold">{plan.name}</h3>
                                    <p className="mt-3 text-sm leading-relaxed text-grey-33">{plan.description}</p>
                                    <ul className="mt-4 flex flex-col gap-2">
                                        {plan.points.map((point) => (
                                            <li key={point} className="flex items-start gap-2 text-sm">
                                                <CheckIcon className={['mt-0.5 shrink-0', tone.check].join(' ')} />
                                                <span>{point}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <PillButton
                                        className="mt-6 border border-brand-primary text-brand-primary hover:bg-brand-primary/10"
                                        onClick={() => onLearnMore?.(plan.name)}
                                    >
                                        Learn More
                                        <span aria-hidden>›</span>
                                    </PillButton>
                                </article>
                            );
                        })}
                    </div>
                </div>
            </section>

            <section id="sickle-cell" className="bg-bg-page px-5 py-16">
                <div className="mx-auto grid w-full max-w-6xl items-center gap-10 rounded-[24px] bg-white p-6 shadow-sm lg:grid-cols-2 lg:p-10">
                    <PhotoPlaceholder label="Journey photo" className="aspect-[4/5] w-full rounded-[20px]" />
                    <div>
                        <p className="inline-flex rounded-full bg-[color-mix(in_srgb,var(--status-error)_14%,white)] px-3 py-1 font-montserrat text-xs font-semibold text-status-error">
                            My Journey
                        </p>
                        <h2 className="mt-4 font-montserrat text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
                            From Pain to Empowerment: My life-long Journey
                        </h2>
                        <p className="mt-4 leading-relaxed text-grey-33">
                            For those living with Sickle Cell, a &quot;normal life&quot; often feels out of reach. I
                            know because I lived it. Today, I share a different reality:{' '}
                            <strong className="font-semibold text-brand-primary">
                                I have been free of sickle cell crises for over 19 years.
                            </strong>
                        </p>
                        <p className="mt-4 leading-relaxed text-grey-33">
                            My secret wasn&apos;t found in a pharmacy—it was found in the balance of nature. Meal
                            Craft was born from my fight to reclaim my health through functional nutrition and mindful
                            movement. I discovered that many modern ailments—from metabolic struggles and obesity to
                            hormonal imbalances—are often{' '}
                            <strong className="font-semibold text-brand-secondary">
                                &quot;diseases of deficiency.&quot;
                            </strong>
                        </p>
                        <p className="mt-4 leading-relaxed text-grey-33">
                            This isn&apos;t just an app; it is your guide to a clean, vibrant life that honors the
                            human body.
                        </p>
                        <PillButton className="mt-8 border border-status-error text-status-error hover:bg-status-error/10">
                            Sickle Cell Anemia Warrior Center
                            <span aria-hidden>›</span>
                        </PillButton>
                    </div>
                </div>
            </section>

            <section id="philosophy" className="px-5 py-16">
                <div className="mx-auto w-full max-w-6xl">
                    <div>
                        <h2 className="font-montserrat text-3xl font-bold tracking-tight">Our Philosophy</h2>
                        <p className="mt-4 max-w-prose leading-relaxed text-grey-33">
                            Our clean food philosophy is represented by the double gold ring in our Craft Seal—a
                            visual commitment to maintaining the highest standards. We believe true healing comes from
                            pure, whole foods.
                        </p>
                        <ul className="mt-6 flex flex-col gap-4">
                            {[
                                [
                                    '100% Whole Foods',
                                    'Every ingredient is sourced from nature, not a laboratory.',
                                    'text-brand-primary',
                                ],
                                [
                                    'No Artificial Additives',
                                    'Zero preservatives, artificial colors, or chemical flavor enhancers.',
                                    'text-status-error',
                                ],
                                [
                                    'Craft Standard',
                                    'Every meal is crafted with intention and backed by science.',
                                    'text-brand-secondary',
                                ],
                            ].map(([title, body, color]) => (
                                <li key={title} className="flex gap-3">
                                    <CheckIcon className={['mt-0.5 shrink-0', color].join(' ')} />
                                    <div>
                                        <p className="font-montserrat font-bold">{title}</p>
                                        <p className="text-sm text-grey-33">{body}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </section>

            <section
                id="weekly-menu"
                className="bg-[color-mix(in_srgb,var(--brand-secondary)_16%,white)] px-5 py-16"
            >
                <div className="mx-auto grid w-full max-w-6xl items-center gap-8 lg:grid-cols-2">
                    <div className="grid gap-4">
                        <img
                            src="/images/landing/roast.jpg"
                            alt="Roast chicken with vegetables on a wooden table"
                            className="aspect-[16/9] w-full rounded-[20px] object-cover"
                        />
                        <img
                            src="/images/landing/stew.jpg"
                            alt="Stew with vegetables, flatbread, and lime"
                            className="aspect-[16/9] w-full rounded-[20px] object-cover"
                        />
                    </div>
                    <div>
                        <h2 className="font-montserrat text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
                            Food the way it was 100 years ago.
                        </h2>
                        <p className="mt-4 max-w-prose text-grey-33">
                            Returning to our roots. Simple ingredients. Traditional wisdom. Real nourishment.
                        </p>
                        <ul className="mt-6 flex flex-col gap-3">
                            {["Grandmother's Kitchen", 'Honest Ingredients', 'Time-Tested Recipes'].map((item) => (
                                <li key={item} className="flex items-center gap-2 font-montserrat text-sm font-semibold">
                                    <CheckIcon className="text-brand-primary" />
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </section>

            <section className="bg-tag-magnesium px-5 py-16 text-white" aria-label="Zero tolerance">
                <div className="mx-auto w-full max-w-6xl">
                    <h2 className="text-center font-montserrat text-3xl font-bold">
                        Our Zero Tolerance Standard
                        <span className="mx-auto mt-3 block h-0.5 w-16 bg-brand-secondary" />
                    </h2>
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            ['ZERO', 'Trans-fats', 'text-status-error'],
                            ['ZERO', 'Refined Sugar', 'text-status-error'],
                            ['ZERO', 'Processed Ingredients', 'text-status-error'],
                            ['100%', 'Bioavailable Nutrition', 'text-brand-primary'],
                        ].map(([value, label, color]) => (
                            <div key={label} className="rounded-[16px] bg-white/10 px-4 py-6 text-center">
                                <p className={['font-montserrat text-3xl font-bold', color].join(' ')}>{value}</p>
                                <p className="mt-2 text-sm">{label}</p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-6 rounded-[16px] bg-white/10 px-6 py-8 text-center">
                        <div className="mx-auto flex justify-center">
                            <MealCraftLogo variant="seal-lg" width={160} alt="Craft Seal" />
                        </div>
                        <p className="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-white/90">
                            The double gold ring in our Craft Seal is a visible boundary: zero tolerance for harmful
                            additives. The botanical leaf stands for nature&apos;s healing power, and the root system
                            for our commitment to the foundation of health.
                        </p>
                    </div>
                </div>
            </section>

            <section className="bg-brand-primary px-5 py-16 text-center text-white">
                <div className="mx-auto flex w-full max-w-3xl flex-col items-center gap-8">
                    <div className="flex w-full flex-col items-center justify-center gap-3 sm:flex-row">
                        <PillButton className="w-full bg-[color-mix(in_srgb,white_78%,var(--brand-primary-default))] text-[#262A22] sm:w-auto">
                            Chat with a Person
                        </PillButton>
                        <PillButton className="w-full bg-[color-mix(in_srgb,white_78%,var(--brand-primary-default))] text-[#262A22] sm:w-auto">
                            Chat with our AI agent
                        </PillButton>
                    </div>
                    <h2 className="font-montserrat text-3xl font-bold tracking-tight sm:text-4xl">
                        Start Your Healing Journey Today
                    </h2>
                    <a
                        href="mailto:?subject=Meal%20Craft"
                        className="font-montserrat text-sm text-white/80 underline decoration-white/40 underline-offset-2 hover:text-white"
                    >
                        Send us an email
                    </a>
                </div>
            </section>

            <BackToTopButton />
        </div>
    );
}
