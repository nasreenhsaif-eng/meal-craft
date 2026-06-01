/**
 * Storybook-only mobile device frame (390px logical width).
 *
 * @param {{ children: import('react').ReactNode; className?: string }} props
 */
export function MobileStoryViewport({ children, className = '' }) {
    return (
        <div className="flex min-h-[100dvh] justify-center bg-[#D1D5DB] p-4 sm:p-8">
            <div
                className={[
                    'relative mx-auto flex w-full max-w-[390px] flex-col overflow-hidden',
                    'rounded-[2.5rem] border-[10px] border-[#111827] bg-[#F8F9F6] shadow-2xl',
                    className,
                ].join(' ')}
                style={{ minHeight: 'min(844px, calc(100dvh - 2rem))' }}
            >
                <div
                    className="pointer-events-none absolute left-1/2 top-0 z-10 h-6 w-[7.5rem] -translate-x-1/2 rounded-b-2xl bg-[#111827]"
                    aria-hidden
                />
                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain pt-3 [&_.max-w-4xl]:max-w-none [&_header>div]:px-4 [&_main]:px-4 [&_main]:py-6">
                    {children}
                </div>
            </div>
        </div>
    );
}

export default MobileStoryViewport;
