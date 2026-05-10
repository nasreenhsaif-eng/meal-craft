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
    title: 'MealCraft/Identity/Brand Marks',
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
