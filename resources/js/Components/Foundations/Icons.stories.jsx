import {
    IconEdit,
    IconDelete,
    IconClock,
    IconEye,
    IconEyeOff,
    IconChevronDown,
    IconLayoutGrid,
    IconLayoutList,
} from '../Atoms/SvgIcons.jsx';
import {
    IconDairy,
    IconGluten,
    IconEggs,
    IconSoy,
    IconNightshades,
    IconBeans,
    IconNuts,
    IconSpicy,
    IconShellfish,
    IconFish,
    IconSesame,
    IconOther,
} from '../MealSystem/FoodFilterIcons.jsx';

const FRAME = 'box-border min-h-screen w-full bg-white p-6 text-[#364153] sm:p-8';

const UI_ICONS = [
    { name: 'Edit', Icon: IconEdit },
    { name: 'Delete', Icon: IconDelete },
    { name: 'Clock', Icon: IconClock },
    { name: 'Eye', Icon: IconEye },
    { name: 'Eye off', Icon: IconEyeOff },
    { name: 'Chevron down', Icon: IconChevronDown },
    { name: 'Layout grid', Icon: IconLayoutGrid },
    { name: 'Layout list', Icon: IconLayoutList },
];

const FILTER_ICONS = [
    { name: 'Dairy', Icon: IconDairy },
    { name: 'Gluten', Icon: IconGluten },
    { name: 'Eggs', Icon: IconEggs },
    { name: 'Soy', Icon: IconSoy },
    { name: 'Nightshades', Icon: IconNightshades },
    { name: 'Beans', Icon: IconBeans },
    { name: 'Nuts', Icon: IconNuts },
    { name: 'Spicy', Icon: IconSpicy },
    { name: 'Shellfish', Icon: IconShellfish },
    { name: 'Fish', Icon: IconFish },
    { name: 'Sesame', Icon: IconSesame },
    { name: 'Other', Icon: IconOther },
];

/**
 * @param {{ name: string; Icon: import('react').ComponentType<{ className?: string }> }} props
 */
function IconTile({ name, Icon }) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-[12px] border border-[#E5E7EB] bg-white p-4 shadow-sm">
            <span className="text-[#5A6B44] [&_svg]:h-6 [&_svg]:w-6">
                <Icon />
            </span>
            <p className="m-0 text-center font-montserrat text-xs font-bold tracking-tight">{name}</p>
        </div>
    );
}

export default {
    title: 'Design System/01. Foundations/Icons',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
};

export const Gallery = {
    render: () => (
        <div className={FRAME}>
            <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-[#6E8C47]">Icons</h1>
            <p className="mt-2 max-w-2xl font-body text-sm text-[#555555]">
                Glyph-only SVGs. Icon buttons live under Atoms / Button.
            </p>

            <h2 className="mt-10 font-montserrat text-lg font-bold tracking-tight text-[#262A22]">UI</h2>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                {UI_ICONS.map((item) => (
                    <IconTile key={item.name} {...item} />
                ))}
            </div>

            <h2 className="mt-10 font-montserrat text-lg font-bold tracking-tight text-[#262A22]">Food filters</h2>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                {FILTER_ICONS.map((item) => (
                    <IconTile key={item.name} {...item} />
                ))}
            </div>
        </div>
    ),
};
