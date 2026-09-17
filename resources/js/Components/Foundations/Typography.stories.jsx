const FRAME = 'box-border min-h-screen w-full bg-white p-6 text-[#364153] sm:p-8';

export default {
    title: 'Design System/01. Foundations/Typography',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
};

export const Scale = {
    render: () => (
        <div className={FRAME}>
            <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-[#6E8C47]">Typography</h1>
            <p className="mt-2 max-w-2xl font-body text-sm text-[#555555]">
                Montserrat for headers and body. Sizes from <code className="font-medium">:root</code> text tokens.
            </p>

            <div className="mt-10 space-y-8">
                <div>
                    <p className="mb-2 font-montserrat text-xs font-bold uppercase tracking-wider text-[#888888]">
                        H1 — 32px / bold
                    </p>
                    <p className="m-0 font-montserrat text-[32px] font-bold leading-tight tracking-tight text-[#262A22]">
                        Welcome to Meal Craft
                    </p>
                </div>
                <div>
                    <p className="mb-2 font-montserrat text-xs font-bold uppercase tracking-wider text-[#888888]">
                        H2 — 24px / bold
                    </p>
                    <p className="m-0 font-montserrat text-2xl font-bold leading-snug tracking-tight text-[#262A22]">
                        Choose your meals
                    </p>
                </div>
                <div>
                    <p className="mb-2 font-montserrat text-xs font-bold uppercase tracking-wider text-[#888888]">
                        Body — 16px / medium
                    </p>
                    <p className="m-0 max-w-xl font-body text-base font-medium leading-relaxed text-[#364153]">
                        Confirm how we should reach you, where to deliver, and the plan you want to start.
                    </p>
                </div>
                <div>
                    <p className="mb-2 font-montserrat text-xs font-bold uppercase tracking-wider text-[#888888]">
                        Caption — 12px
                    </p>
                    <p className="m-0 font-montserrat text-xs font-medium leading-snug text-[#555555]">
                        You can return to your dashboard anytime from Home.
                    </p>
                </div>
            </div>

            <div className="mt-12">
                <p className="mb-4 font-montserrat text-xs font-bold uppercase tracking-wider text-[#888888]">
                    Weights
                </p>
                <div className="space-y-2 font-montserrat text-base text-[#262A22]">
                    <p className="m-0 font-normal">Regular 400 — ingredient notes</p>
                    <p className="m-0 font-medium">Medium 500 — body copy</p>
                    <p className="m-0 font-semibold">Semibold 600 — nav labels</p>
                    <p className="m-0 font-bold">Bold 700 — buttons and headings</p>
                </div>
            </div>
        </div>
    ),
};
