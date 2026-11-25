import { useEffect, useState } from 'react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import {
    IconCalendar,
    IconCheckCircle,
    IconClose,
    IconHeart,
    IconMenu,
    IconPerson,
    IconShield,
    IconTag,
} from '../../Components/Atoms/SvgIcons.jsx';
import {
    IconBalanced,
    IconCycleSync,
    IconKetobiotic,
    IconSickleCellWarrior,
} from '../../Components/Molecules/Onboarding/DietProtocolIcons.jsx';

const NAV = [
    { href: '#top', label: 'Home' },
    { href: '#meal-plans', label: 'Meal Plans' },
    { href: '#journey', label: 'My Journey' },
    { href: '#philosophy', label: 'Philosophy' },
    { href: '#contact', label: 'Contact' },
];

const SMART_KITCHEN = [
    {
        title: 'Personalized Meal Plans',
        body: 'AI-powered meal planning tailored to your specific health goals and dietary needs.',
        glyph: 'calendar',
    },
    {
        title: 'Non-Toxic Kitchen Guide',
        body: 'Audit your kitchen tools and discover which items may be leaching harmful chemicals into your food.',
        glyph: 'shield',
        link: 'Take the Quiz',
    },
    {
        title: 'Ingredient Scanner',
        body: 'Instantly identify harmful additives and verify food quality with our smart scanner.',
        glyph: 'tag',
    },
    {
        title: 'Sickle Cell Anemia Guide',
        body: 'Learn how whole-food nutrition supports blood health, hydration, and inflammation control for sickle cell warriors.',
        glyph: 'heart',
    },
];

const PLANS = [
    {
        name: 'Balanced',
        bar: 'bg-[#6E8C47]',
        icon: 'bg-[#6E8C47]',
        check: 'text-[#6E8C47]',
        description:
            'A well-rounded approach to anti-inflammatory eating for overall wellness and vitality.',
        points: ['Nutrient-dense meals', 'Sustainable energy', 'Whole food focus'],
        Icon: IconBalanced,
    },
    {
        name: 'Ketobiotic',
        bar: 'bg-[#D8A933]',
        icon: 'bg-[#D8A933]',
        check: 'text-[#D8A933]',
        description: 'Low-carb, high-fat meals supporting ketosis and gut health for metabolic wellness.',
        points: ['Ketogenic principles', 'Gut microbiome support', 'Metabolic optimization'],
        Icon: IconKetobiotic,
    },
    {
        name: 'Cycle Sync',
        bar: 'bg-[#8F55A8]',
        icon: 'bg-[#8F55A8]',
        check: 'text-[#8F55A8]',
        description:
            "Hormone-balancing nutrition tailored to women's monthly cycles for optimal well-being.",
        points: ['Hormonal balance', 'Phase-specific meals', 'Energy optimization'],
        Icon: IconCycleSync,
    },
    {
        name: 'Sickle Cell Warrior',
        bar: 'bg-[#C45C6A]',
        icon: 'bg-[#C45C6A]',
        check: 'text-[#C45C6A]',
        description:
            'Specialized nutrition supporting blood health and reducing inflammation for sickle cell warriors.',
        points: ['Blood health support', 'Crisis prevention', 'Targeted nutrients'],
        Icon: IconSickleCellWarrior,
    },
];

/**
 * Scroll so the section top clears the sticky landing header.
 *
 * @param {string} href
 */
function scrollToSection(href) {
    if (href === '#top') {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        return;
    }

    const target = document.querySelector(href);

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const header = document.querySelector('[data-landing-header]');
    const headerHeight =
        header instanceof HTMLElement ? header.getBoundingClientRect().height : 72;
    const gap = 16;
    const top = window.scrollY + target.getBoundingClientRect().top - headerHeight - gap;

    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
}

const SMART_KITCHEN_GLYPHS = {
    calendar: IconCalendar,
    heart: IconHeart,
    tag: IconTag,
    shield: IconShield,
};

/**
 * @param {{ name: keyof typeof SMART_KITCHEN_GLYPHS }} props
 */
function SmartKitchenGlyph({ name }) {
    const Glyph = SMART_KITCHEN_GLYPHS[name] ?? IconCalendar;

    return <Glyph className="text-white" />;
}

/**
 * Public marketing landing, matched to the Figma landing page.
 *
 * @param {{
 *   loginHref?: string;
 *   joinHref?: string;
 *   onStartNow?: () => void;
 * }} props
 */
export default function LandingPage({ loginHref = '/login', joinHref = '/join', onStartNow }) {
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    useEffect(() => {
        if (!mobileNavOpen) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                setMobileNavOpen(false);
            }
        };

        const onResize = () => {
            if (window.matchMedia('(min-width: 768px)').matches) {
                setMobileNavOpen(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('resize', onResize);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('resize', onResize);
        };
    }, [mobileNavOpen]);

    /**
     * @param {string} href
     */
    function goToSection(href) {
        setMobileNavOpen(false);
        // Wait for the hamburger panel to collapse so header height is correct.
        window.requestAnimationFrame(() => {
            window.setTimeout(() => scrollToSection(href), 50);
        });
    }

    return (
        <div id="top" className="min-h-screen bg-white font-body text-heading">
            <header
                data-landing-header
                className="sticky top-0 z-20 border-b border-border-light bg-white/95 backdrop-blur-sm"
            >
                <div className="mx-auto flex w-full max-w-[1100px] items-center justify-between gap-4 px-5 py-2.5">
                    <a href="#top" className="inline-flex items-center" aria-label="Meal Craft home">
                        <MealCraftLogo variant="minimal" width={168} alt="" />
                    </a>
                    <div className="flex items-center gap-1 sm:gap-2">
                        <nav
                            aria-label="Landing"
                            className="hidden items-center gap-6 md:flex"
                        >
                            {NAV.map((item, index) => (
                                <button
                                    key={item.href}
                                    type="button"
                                    onClick={() => goToSection(item.href)}
                                    className={[
                                        'font-montserrat text-sm',
                                        index === 0
                                            ? 'font-extrabold text-[#6E8C47]'
                                            : 'font-normal text-body hover:text-heading',
                                    ].join(' ')}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </nav>
                        <a
                            href={loginHref}
                            aria-label="Log in"
                            className="inline-flex h-10 w-10 items-center justify-center rounded-full text-body transition-colors hover:bg-[#6E8C47]/10 hover:text-[#6E8C47] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2"
                        >
                            <IconPerson />
                        </a>
                        <button
                            type="button"
                            className="inline-flex h-10 w-10 items-center justify-center rounded-full text-body transition-colors hover:bg-[#6E8C47]/10 hover:text-[#6E8C47] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2 md:hidden"
                            aria-expanded={mobileNavOpen}
                            aria-controls="landing-mobile-nav"
                            onClick={() => setMobileNavOpen((open) => !open)}
                        >
                            {mobileNavOpen ? <IconClose /> : <IconMenu />}
                            <span className="sr-only">{mobileNavOpen ? 'Close menu' : 'Open menu'}</span>
                        </button>
                    </div>
                </div>

                {mobileNavOpen ? (
                    <nav
                        id="landing-mobile-nav"
                        aria-label="Landing mobile"
                        className="border-t border-border-light bg-white md:hidden"
                    >
                        <ul className="mx-auto flex w-full max-w-[1100px] flex-col px-5 py-2">
                            {NAV.map((item, index) => (
                                <li key={item.href}>
                                    <button
                                        type="button"
                                        onClick={() => goToSection(item.href)}
                                        className={[
                                            'w-full py-3 text-left font-montserrat text-base',
                                            index === 0
                                                ? 'font-extrabold text-[#6E8C47]'
                                                : 'font-medium text-body hover:text-heading',
                                        ].join(' ')}
                                    >
                                        {item.label}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </nav>
                ) : null}
            </header>

            <section
                aria-label="Hero"
                className="flex min-h-[calc(100svh-4.75rem)] items-center bg-[linear-gradient(148deg,rgba(110,140,71,0.12)_0%,#ffffff_72%)]"
            >
                <div className="mx-auto grid w-full max-w-[1100px] items-center gap-12 px-6 py-20 lg:grid-cols-[1.15fr_0.85fr] lg:gap-16 lg:px-10 lg:py-28">
                    <div>
                        <h1 className="font-montserrat text-5xl font-bold leading-[1.05] tracking-tight sm:text-6xl lg:text-7xl">
                            <span className="block text-heading">Healing Through</span>
                            <span className="mt-1 block text-[#6E8C47]">Whole Foods</span>
                        </h1>
                        <p className="mt-6 max-w-xl text-lg leading-relaxed text-body sm:text-xl">
                            Create personalized anti-inflammatory meal plans from whole foods with our clean food
                            philosophy and zero tolerance for harmful additives.
                        </p>
                        <div className="mt-10 flex flex-wrap items-center gap-4">
                            <Button
                                label="Get Started"
                                variant="primary"
                                size="md"
                                onClick={() => {
                                    if (onStartNow) {
                                        onStartNow();
                                        return;
                                    }
                                    window.location.assign(joinHref);
                                }}
                            />
                        </div>
                    </div>
                    <div className="flex justify-center">
                        <MealCraftLogo
                            variant="vertical-marketing"
                            width={400}
                            className="h-auto max-w-full"
                            alt="Meal Craft"
                        />
                    </div>
                </div>
            </section>

            <section
                id="meal-plans"
                className="flex min-h-[calc(100svh-4.75rem)] scroll-mt-28 items-start bg-surface-soft px-5 py-20 lg:items-center lg:py-28"
            >
                <div className="mx-auto w-full max-w-[1100px]">
                    <h2 className="text-center font-montserrat text-3xl font-bold tracking-tight text-heading sm:text-4xl lg:text-5xl">
                        Four Specialized Meal Plans
                    </h2>
                    <p className="mx-auto mt-5 max-w-2xl text-center text-lg text-body">
                        Each plan is carefully crafted to address specific health needs while maintaining our
                        zero-tolerance standard for harmful additives.
                    </p>
                    <div className="mt-14 grid gap-6 sm:grid-cols-2 xl:grid-cols-4 xl:gap-8">
                        {PLANS.map((plan) => {
                            const PlanIcon = plan.Icon;

                            return (
                                <article
                                    key={plan.name}
                                    className="flex flex-col overflow-hidden rounded-2xl bg-white shadow-[0_12px_30px_-18px_rgba(16,24,40,0.45)]"
                                >
                                    <div className={['h-1.5', plan.bar].join(' ')} />
                                    <div className="flex flex-1 flex-col p-7 lg:p-8">
                                        <div
                                            className={[
                                                'flex h-14 w-14 items-center justify-center rounded-xl text-white [&>svg]:h-8 [&>svg]:w-8',
                                                plan.icon,
                                            ].join(' ')}
                                        >
                                            <PlanIcon className="text-white" />
                                        </div>
                                        <h3 className="mt-6 font-montserrat text-xl font-bold lg:text-2xl">{plan.name}</h3>
                                        <p className="mt-3 text-sm leading-relaxed text-body lg:text-base">{plan.description}</p>
                                        <ul className="mt-5 flex flex-col gap-2.5 lg:mt-6">
                                            {plan.points.map((point) => (
                                                <li key={point} className="flex items-start gap-2 text-sm text-body">
                                                    <IconCheckCircle className={['mt-0.5 shrink-0', plan.check].join(' ')} />
                                                    <span>{point}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                </div>
            </section>

            <section
                id="journey"
                className="flex min-h-[calc(100svh-4.75rem)] scroll-mt-28 items-start bg-white px-5 py-20 lg:items-center lg:py-28"
            >
                <div className="mx-auto grid w-full max-w-[1100px] items-center gap-12 lg:grid-cols-2">
                    <div className="relative">
                        <img
                            src="/images/landing/founder-kitchen.jpg"
                            alt="Meal Craft founder preparing fresh whole foods in the kitchen"
                            className="aspect-[5/4] w-full rounded-3xl object-cover object-center shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)]"
                        />
                        <img
                            src="/images/landing/journey-meal.jpg"
                            alt="Colorful whole food meal"
                            className="absolute -bottom-6 right-6 h-28 w-28 rounded-2xl border-4 border-white object-cover shadow-lg sm:h-32 sm:w-32"
                        />
                    </div>
                    <div>
                        <p className="inline-flex rounded-full bg-[#F8E8EA] px-3 py-1 font-montserrat text-sm font-semibold text-[#C45C6A]">
                            My Journey
                        </p>
                        <h2 className="mt-5 font-montserrat text-4xl font-bold leading-tight tracking-tight sm:text-5xl">
                            From Pain to Empowerment: My life-long Journey
                        </h2>
                        <p className="mt-6 leading-relaxed text-body">
                            For those living with Sickle Cell, a &quot;normal life&quot; often feels out of reach. I
                            know because I lived it. Today, I share a different reality:{' '}
                            <strong className="font-semibold text-[#6E8C47]">
                                I have been free of sickle cell crises for over 19 years.
                            </strong>
                        </p>
                        <p className="mt-4 leading-relaxed text-body">
                            My secret wasn&apos;t found in a pharmacy. It was found in the balance of nature. Meal
                            Craft was born from my fight to reclaim my health through functional nutrition and mindful
                            movement. I discovered that many modern ailments, from metabolic struggles and obesity to
                            hormonal imbalances, are often{' '}
                            <strong className="font-semibold text-[#D8A933]">&quot;diseases of deficiency.&quot;</strong>
                        </p>
                        <p className="mt-4 leading-relaxed text-body">
                            This isn&apos;t just an app; it is your guide to a clean, vibrant life that honors the
                            human body.
                        </p>
                    </div>
                </div>
            </section>

            <section id="philosophy" className="scroll-mt-28 bg-surface-soft px-5 py-20">
                <div className="mx-auto grid w-full max-w-[1100px] items-center gap-10 lg:grid-cols-2">
                    <div className="min-w-0">
                        <h2 className="font-montserrat text-4xl font-bold leading-tight tracking-tight sm:text-5xl">
                            Food the way it was 100 years ago.
                        </h2>
                        <p className="mt-6 w-full text-xl leading-relaxed text-body">
                            Returning to our roots with simple ingredients, traditional wisdom, and real nourishment
                            from grandmother&apos;s kitchen and time-tested recipes.
                        </p>
                    </div>
                    <div className="min-w-0">
                        <img
                            src="/images/landing/heritage-stew.jpg"
                            alt="Traditional hearty stew with whole ingredients"
                            className="aspect-[4/3] w-full rounded-2xl object-cover shadow-lg"
                        />
                    </div>
                </div>
            </section>

            <section className="bg-brand-primary/10 px-5 py-20 text-heading" aria-label="Zero tolerance standard">
                <div className="mx-auto w-full max-w-[1100px]">
                    <h2 className="text-center font-montserrat text-3xl font-bold sm:text-4xl">
                        Our Zero Tolerance Standard
                        <span className="mx-auto mt-3 block h-1 w-16 rounded-full bg-[#D8A933]" />
                    </h2>
                    <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            ['ZERO', 'Trans-fats', 'text-[#E07A86]', 'bg-[#E07A86]'],
                            ['ZERO', 'Refined Sugar', 'text-[#E07A86]', 'bg-[#E07A86]'],
                            ['ZERO', 'Processed Ingredients', 'text-[#E07A86]', 'bg-[#E07A86]'],
                            ['100%', 'Bioavailable Nutrition', 'text-[#6E8C47]', 'bg-[#6E8C47]'],
                        ].map(([value, label, color, line]) => (
                            <div key={label} className="rounded-2xl border border-brand-primary/15 bg-white px-4 py-8 text-center shadow-sm">
                                <p className={['font-montserrat text-4xl font-bold', color].join(' ')}>{value}</p>
                                <p className="mt-2 text-sm text-body">{label}</p>
                                <span className={['mx-auto mt-4 block h-1 w-12 rounded-full', line].join(' ')} />
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section id="contact" className="scroll-mt-28">
                <div className="bg-[#2F4C9B] px-5 py-20 text-center text-white">
                    <div className="mx-auto w-full max-w-3xl">
                        <h2 className="font-montserrat text-4xl font-bold tracking-tight sm:text-5xl">
                            Start Your Healing Journey Today
                        </h2>
                        <p className="mx-auto mt-4 max-w-xl text-lg text-white/90">
                            Join thousands who have transformed their health through whole foods and clean eating.
                        </p>
                        <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                            <Button
                                label="Chat with AI Agent ›"
                                variant="reversed"
                                size="md"
                                onClick={() => {
                                    if (onStartNow) {
                                        onStartNow();
                                        return;
                                    }
                                    window.location.assign(joinHref);
                                }}
                            />
                            <Button
                                label="Chat with a Customer Service Agent"
                                variant="reversed"
                                size="md"
                                onClick={() => {
                                    window.location.assign(loginHref);
                                }}
                            />
                        </div>
                    </div>
                </div>

                <div className="bg-surface-soft px-5 py-14 lg:py-16">
                    <div className="mx-auto w-full max-w-[1100px]">
                        <h3 className="text-center font-montserrat text-2xl font-bold sm:text-3xl">
                            Smart Kitchen Technology
                        </h3>
                        <p className="mx-auto mt-3 max-w-xl text-center text-body">
                            Advanced tools to make anti-inflammatory eating effortless and enjoyable.
                        </p>
                        <div className="mt-10 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
                            {SMART_KITCHEN.map((item) => (
                                <article key={item.title} className="rounded-xl bg-white p-4 shadow-sm lg:p-5">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-tag-magnesium text-white">
                                        <SmartKitchenGlyph name={item.glyph} />
                                    </div>
                                    <h4 className="mt-4 font-montserrat text-sm font-bold leading-snug lg:text-base">
                                        {item.title}
                                    </h4>
                                    <p className="mt-2 text-xs leading-relaxed text-body lg:text-sm">{item.body}</p>
                                    {item.link ? (
                                        <a
                                            href={loginHref}
                                            className="mt-3 inline-flex font-montserrat text-xs font-semibold text-tag-magnesium lg:text-sm"
                                        >
                                            {item.link} ›
                                        </a>
                                    ) : null}
                                </article>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <footer className="bg-surface-dark px-5 py-16 text-white">
                <div className="mx-auto grid w-full max-w-[1100px] gap-10 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <MealCraftLogo
                            variant="vertical-marketing"
                            width={160}
                            alt="Meal Craft"
                            className="h-auto max-w-full"
                        />
                    </div>
                    <div>
                        <h2 className="font-montserrat text-base font-bold">Meal Plans</h2>
                        <ul className="mt-4 space-y-2 text-sm text-white/70">
                            {PLANS.map((plan) => (
                                <li key={plan.name}>
                                    <button type="button" onClick={() => scrollToSection('#meal-plans')} className="hover:text-white">
                                        {plan.name}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <h2 className="font-montserrat text-base font-bold">Company</h2>
                        <ul className="mt-4 space-y-2 text-sm text-white/70">
                            <li><button type="button" onClick={() => scrollToSection('#philosophy')} className="hover:text-white">Philosophy</button></li>
                            <li><button type="button" onClick={() => scrollToSection('#journey')} className="hover:text-white">Blog</button></li>
                            <li>
                                <button type="button" onClick={() => scrollToSection('#contact')} className="hover:text-white">
                                    Contact
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <p className="mx-auto mt-12 max-w-[1100px] border-t border-white/10 pt-6 text-center text-sm text-white/50">
                    © 2026 Meal Craft. All rights reserved. | Zero tolerance for harmful additives.
                </p>
            </footer>
        </div>
    );
}
