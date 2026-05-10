/** Shared Storybook canvas for sprite-based MealCraftLogo stories (full bleed, horizontal scroll if needed). */
export function mealCraftLogoPageDecorator(Story) {
    return (
        <div className="box-border min-h-screen w-full min-w-0 max-w-none overflow-x-auto overflow-y-visible">
            <Story />
        </div>
    );
}

/** Horizontal lockup suite — narrow column so logos are not swimming in excess horizontal canvas. */
export function mealCraftLogoHorizontalStoryDecorator(Story) {
    return (
        <div className="box-border flex min-h-screen w-full min-w-0 justify-center overflow-x-hidden px-4 py-10">
            <div className="w-full max-w-xs">
                <Story />
            </div>
        </div>
    );
}
