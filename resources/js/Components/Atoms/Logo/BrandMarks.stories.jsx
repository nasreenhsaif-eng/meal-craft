import MealCraftLogo from './MealCraftLogo.jsx';
import { mealCraftLogoPageDecorator } from './logoStoryDecorators.jsx';

/** Seals (XS–XL) and leaf marks only — no horizontal/vertical lockups. */
const sealMeta = [
    { variant: 'seal-xs', label: 'Extra Small', width: 84 },
    { variant: 'seal-sm', label: 'Small', width: 152 },
    { variant: 'seal-md', label: 'Medium', width: 168 },
    { variant: 'seal-lg', label: 'Large', width: 252 },
    { variant: 'seal-xl', label: 'Extra Large', width: 400 },
];

const leafColors = ['gold', 'red', 'purple', 'green', 'blue'];

/** @type {import('@storybook/react-vite').Meta} */
const meta = {
    title: 'Design System/01. Tokens/Logos/Brand Marks',
    component: MealCraftLogo,
    decorators: [mealCraftLogoPageDecorator],
    parameters: {
        controls: { disable: true },
    },
};

export default meta;

/** @type {import('@storybook/react-vite').StoryObj} */
export const Seals = {
    name: 'Seals',
    render: () => (
        <div className="box-border w-full min-w-0 max-w-none self-stretch">
            <div className="box-border flex w-full min-w-0 max-w-none flex-col items-start gap-10 py-10 pl-0 pr-8 sm:pr-10">
                {sealMeta.map(({ variant, label, width }) => (
                    <div
                        key={variant}
                        className="flex w-full min-w-0 flex-col items-start gap-2 overflow-visible"
                    >
                        <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            Seal · {label} ({width}px)
                        </p>
                        <div
                            className="shrink-0 overflow-visible"
                            style={
                                variant === 'seal-xl'
                                    ? {
                                          width: `${width}px`,
                                          minWidth: `${width}px`,
                                          minHeight: '420px',
                                          maxWidth: 'none',
                                      }
                                    : {
                                          width: `${width}px`,
                                          minWidth: `${width}px`,
                                          maxWidth: 'none',
                                      }
                            }
                        >
                            <MealCraftLogo variant={variant} width={width} />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    ),
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const LeafVariants = {
    name: 'Leaf variants',
    render: () => (
        <div className="flex flex-wrap items-end gap-8">
            {leafColors.map((token) => (
                <div key={token} className="flex flex-col items-center gap-2">
                    <MealCraftLogo variant="leaf" color={token} width={33} />
                    <span className="text-xs uppercase tracking-wide text-neutral-500">{token}</span>
                </div>
            ))}
        </div>
    ),
};

/** Browser tab / PWA icons — Meal Craft seal (public/favicon.*). */
const faviconAssets = [
    { src: '/favicon.svg', label: 'favicon.svg', caption: 'Primary tab icon · transparent', size: 64 },
    { src: '/favicon-32x32.png', label: 'favicon-32x32.png', caption: 'PNG tab icon · 32×32', size: 64 },
    { src: '/favicon-16x16.png', label: 'favicon-16x16.png', caption: 'PNG tab icon · 16×16', size: 48 },
    { src: '/favicon.ico', label: 'favicon.ico', caption: 'Fallback tab icon', size: 64 },
    {
        src: '/apple-touch-icon.png',
        label: 'apple-touch-icon.png',
        caption: 'iOS home screen · 180×180',
        size: 96,
    },
    {
        src: '/images/branding/seal-favicon-512.png',
        label: 'seal-favicon-512.png',
        caption: 'Cropped seal source · transparent',
        size: 128,
    },
];

/** @type {import('@storybook/react-vite').StoryObj} */
export const Favicon = {
    name: 'Favicon',
    render: () => (
        <div className="box-border w-full min-w-0 max-w-none self-stretch">
            <div className="box-border flex w-full min-w-0 max-w-none flex-col items-start gap-8 py-10 pl-0 pr-8 sm:pr-10">
                <p className="max-w-xl text-sm text-neutral-600">
                    Browser tab and touch icons use the Meal Craft seal. Landing title:{' '}
                    <span className="font-medium text-neutral-900">Home - mealcraft</span>
                </p>
                <div className="flex flex-wrap items-end gap-10">
                    {faviconAssets.map(({ src, label, caption, size }) => (
                        <div key={src} className="flex flex-col items-start gap-2">
                            <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                {label}
                            </p>
                            <div
                                className="flex items-center justify-center rounded-lg border border-neutral-200 bg-white p-3"
                                style={{ width: size + 24, height: size + 24 }}
                            >
                                <img
                                    src={src}
                                    alt={label}
                                    width={size}
                                    height={size}
                                    className="block object-contain"
                                />
                            </div>
                            <span className="text-xs text-neutral-500">{caption}</span>
                        </div>
                    ))}
                </div>
                <div className="flex items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3">
                    <img
                        src="/favicon.svg"
                        alt=""
                        width={16}
                        height={16}
                        className="block"
                        aria-hidden="true"
                    />
                    <span className="font-montserrat text-sm text-neutral-800">Home - mealcraft</span>
                </div>
            </div>
        </div>
    ),
};
