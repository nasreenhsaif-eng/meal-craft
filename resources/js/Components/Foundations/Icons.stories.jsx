import {
    IconEdit,
    IconDelete,
    IconClock,
    IconEye,
    IconEyeOff,
    IconChevronDown,
    IconLayoutGrid,
    IconLayoutList,
    IconMenu,
    IconClose,
    IconSearch,
    IconPerson,
    IconCheckCircle,
    IconHome,
    IconMeals,
    IconFavourites,
    IconShop,
    IconTools,
    IconProfile,
    IconCart,
    IconDragHandle,
    IconInfo,
    IconMale,
    IconFemale,
    IconCalendar,
    IconShield,
    IconHeart,
    IconTag,
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
import {
    IconBalanced,
    IconKetobiotic,
    IconCycleSync,
    IconSickleCellWarrior,
    IconThyroid,
    IconNutrientDense,
} from '../Molecules/Onboarding/DietProtocolIcons.jsx';

const FRAME = 'box-border min-h-screen w-full bg-white p-6 text-body sm:p-8';

const UI_ICONS = [
    { name: 'Edit', Icon: IconEdit },
    { name: 'Delete', Icon: IconDelete },
    { name: 'Clock', Icon: IconClock },
    { name: 'Eye', Icon: IconEye },
    { name: 'Eye off', Icon: IconEyeOff },
    { name: 'Chevron down', Icon: IconChevronDown },
    { name: 'Layout grid', Icon: IconLayoutGrid },
    { name: 'Layout list', Icon: IconLayoutList },
    { name: 'Menu', Icon: IconMenu },
    { name: 'Close', Icon: IconClose },
    { name: 'Search', Icon: IconSearch },
    { name: 'Person', Icon: IconPerson },
    { name: 'Check circle', Icon: IconCheckCircle },
    { name: 'Cart', Icon: IconCart },
    { name: 'Drag handle', Icon: IconDragHandle },
    { name: 'Info', Icon: IconInfo },
    { name: 'Male', Icon: IconMale },
    { name: 'Female', Icon: IconFemale },
];

const APP_NAV_ICONS = [
    { name: 'Home', Icon: IconHome },
    { name: 'Favourites', Icon: IconFavourites },
    { name: 'Meals', Icon: IconMeals },
    { name: 'Shop', Icon: IconShop },
    { name: 'Tools', Icon: IconTools },
    { name: 'Profile', Icon: IconProfile },
];

const LANDING_ICONS = [
    { name: 'Calendar', Icon: IconCalendar },
    { name: 'Shield', Icon: IconShield },
    { name: 'Heart', Icon: IconHeart },
    { name: 'Tag', Icon: IconTag },
];

/** Onboarding diet protocols and landing meal-plan cards share these glyphs. */
const DIET_PROTOCOL_ICONS = [
    { name: 'Balanced', Icon: IconBalanced },
    { name: 'Ketobiotic', Icon: IconKetobiotic },
    { name: 'Cycle sync', Icon: IconCycleSync },
    { name: 'Sickle cell', Icon: IconSickleCellWarrior },
    { name: 'Thyroid', Icon: IconThyroid },
    { name: 'Nutrient dense', Icon: IconNutrientDense },
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
 * @param {{ name: string; Icon: import('react').ComponentType<{ className?: string }>; showName?: boolean }} props
 */
function IconTile({ name, Icon, showName = true }) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-card border border-border-light bg-white p-6 shadow-sm">
            <span className="inline-flex h-14 w-14 items-center justify-center text-brand-primary [&>*]:!h-14 [&>*]:!w-14 [&_svg]:h-14 [&_svg]:w-14">
                <Icon />
            </span>
            {showName ? (
                <p className="m-0 text-center font-montserrat text-xs font-bold tracking-tight text-heading">{name}</p>
            ) : (
                <span className="sr-only">{name}</span>
            )}
        </div>
    );
}

/**
 * @param {{ title: string; icons: Array<{ name: string; Icon: import('react').ComponentType<{ className?: string }> }>; showNames?: boolean }} props
 */
function IconSection({ title, icons, showNames = true }) {
    return (
        <>
            <h2 className="mt-10 font-montserrat text-lg font-bold tracking-tight text-heading">{title}</h2>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                {icons.map((item) => (
                    <IconTile key={item.name} {...item} showName={showNames} />
                ))}
            </div>
        </>
    );
}

export default {
    title: 'Design System/01. Tokens/Icons',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
};

export const Gallery = {
    render: () => (
        <div className={FRAME}>
            <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-brand-primary">Icons</h1>
            <p className="mt-2 max-w-2xl font-body text-sm text-body">
                Shared glyphs from the live app and landing. Diet protocol icons are also used on landing meal-plan
                cards. Icon buttons live under Atoms / Button.
            </p>

            <IconSection title="UI" icons={UI_ICONS} />
            <IconSection title="App nav" icons={APP_NAV_ICONS} />
            <IconSection title="Landing" icons={LANDING_ICONS} />
            <IconSection title="Diet protocols" icons={DIET_PROTOCOL_ICONS} />
            <IconSection title="Food filters" icons={FILTER_ICONS} />
        </div>
    ),
};
