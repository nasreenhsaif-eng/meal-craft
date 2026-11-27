const FRAME = 'box-border min-h-screen w-full bg-white p-6 text-body sm:p-8';

/**
 * @param {{ role: string; utility: string; detail: string; sampleClass: string; sample: string }} props
 */
function TypeRow({ role, utility, detail, sampleClass, sample }) {
    return (
        <div className="border-b border-border-light py-6 last:border-b-0">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="m-0 font-montserrat text-xs font-bold uppercase tracking-wider text-form-label">{role}</p>
                <p className="m-0 font-body text-xs text-body">
                    <code className="font-medium text-heading">{utility}</code>
                    <span className="mx-2 text-form-label">·</span>
                    {detail}
                </p>
            </div>
            <p className={`mt-3 m-0 ${sampleClass}`}>{sample}</p>
        </div>
    );
}

export default {
    title: 'Design System/01. Tokens/Typography',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Type tokens from `resources/css/app.css`. UI copy is Montserrat. Baloo 2 is logo wordmarks only. `text-body` is a **color** (Body text); body **size** is `text-copy` / `text-base` (16px).',
            },
        },
    },
};

export const Scale = {
    name: 'Type scale',
    render: () => (
        <div className={FRAME}>
            <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-brand-primary">Typography</h1>
            <p className="mt-2 max-w-2xl font-body text-sm text-body">
                One UI font for landing and app. Prefer semantic size utilities (`text-h1`, `text-h2`, `text-copy`,
                `text-caption`) with color roles (`text-heading`, `text-body`, `text-form-label`).
            </p>

            <section className="mt-10">
                <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-heading">Families</h2>
                <div className="mt-4 grid gap-6 sm:grid-cols-2">
                    <div className="rounded-card border border-border-light bg-surface-soft p-5">
                        <p className="m-0 font-montserrat text-xs font-bold uppercase tracking-wider text-form-label">
                            UI — Montserrat
                        </p>
                        <p className="mt-3 font-montserrat text-2xl font-bold tracking-tight text-heading">Meal Craft</p>
                        <p className="mt-2 font-body text-sm text-body">
                            Utilities: <code className="font-medium">font-montserrat</code>,{' '}
                            <code className="font-medium">font-body</code>, <code className="font-medium">font-sans</code>
                        </p>
                        <p className="mt-1 font-body text-xs text-form-label">
                            Tokens: --font-family-font-1 / font-2 · weights 400–700 loaded
                        </p>
                    </div>
                    <div className="rounded-card border border-border-light bg-surface-soft p-5">
                        <p className="m-0 font-montserrat text-xs font-bold uppercase tracking-wider text-form-label">
                            Display — Baloo 2 (logos only)
                        </p>
                        <p
                            className="mt-3 text-2xl font-bold tracking-tight text-heading"
                            style={{ fontFamily: 'var(--font-family-display)' }}
                        >
                            Meal Craft
                        </p>
                        <p className="mt-2 font-body text-sm text-body">
                            Utility: <code className="font-medium">font-display</code> · not for page UI copy
                        </p>
                        <p className="mt-1 font-body text-xs text-form-label">
                            Used in logo / intro animation wordmarks only
                        </p>
                    </div>
                </div>
            </section>

            <section className="mt-10">
                <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-heading">Scale</h2>
                <div className="mt-2 rounded-card border border-border-light bg-white px-5">
                    <TypeRow
                        role="H1"
                        utility="text-h1"
                        detail="32px · bold · heading color"
                        sampleClass="font-montserrat text-h1 font-bold leading-tight tracking-tight text-heading"
                        sample="Healing Through Food"
                    />
                    <TypeRow
                        role="H2"
                        utility="text-h2"
                        detail="24px · bold · heading color"
                        sampleClass="font-montserrat text-h2 font-bold leading-snug tracking-tight text-heading"
                        sample="Choose your meals"
                    />
                    <TypeRow
                        role="Copy (body size)"
                        utility="text-copy · text-base"
                        detail="16px · medium · body color (not text-body for size)"
                        sampleClass="max-w-xl font-body text-copy font-medium leading-relaxed text-body"
                        sample="Confirm how we should reach you, where to deliver, and the plan you want to start."
                    />
                    <TypeRow
                        role="Caption / chrome"
                        utility="text-caption · text-xs"
                        detail="12px · medium · body or form-label color"
                        sampleClass="font-montserrat text-caption font-medium leading-snug text-body"
                        sample="You can return to your dashboard anytime from Home."
                    />
                    <TypeRow
                        role="Form label"
                        utility="text-sm + text-form-label"
                        detail="14px · bold · form-label color"
                        sampleClass="font-montserrat text-sm font-bold leading-snug tracking-tight text-form-label"
                        sample="DAILY CALORIES"
                    />
                </div>
            </section>

            <section className="mt-10">
                <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-heading">Weights</h2>
                <div className="mt-4 space-y-2 font-montserrat text-copy text-heading">
                    <p className="m-0 font-normal">Regular 400 — ingredient notes, quiet UI</p>
                    <p className="m-0 font-medium">Medium 500 — body copy, supporting text</p>
                    <p className="m-0 font-semibold">Semibold 600 — nav labels, emphasis</p>
                    <p className="m-0 font-bold">Bold 700 — headings, buttons, section titles</p>
                </div>
            </section>

            <section className="mt-10">
                <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-heading">Color pairing</h2>
                <ul className="mt-3 list-disc space-y-1 pl-5 font-body text-sm text-body">
                    <li>
                        Titles → <code className="font-medium">text-heading</code> (#262A22)
                    </li>
                    <li>
                        Supporting copy → <code className="font-medium">text-body</code> (#555555)
                    </li>
                    <li>
                        Field / chrome labels → <code className="font-medium">text-form-label</code> (#616a75)
                    </li>
                    <li>
                        Brand accent in marketing titles → <code className="font-medium">text-brand-primary</code>
                    </li>
                </ul>
            </section>
        </div>
    ),
};
