/**
 * Storybook canvas for onboarding — defers layout to {@link CustomerLayout} onboarding shell.
 * No extra max-width or padding so mobile previews use the full viewport width.
 *
 * @param {{ children: import('react').ReactNode; className?: string }} props
 */
export function MobileStoryViewport({ children, className = '' }) {
    return (
        <div
            className={[
                'min-h-screen w-full bg-white md:bg-gray-50',
                '[&_.min-h-screen]:min-h-0',
                '[&_.min-h-full]:min-h-0',
                className,
            ].join(' ')}
        >
            {children}
        </div>
    );
}

export default MobileStoryViewport;
