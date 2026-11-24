const FRAME = 'box-border min-h-screen w-full bg-white p-6 text-body sm:p-8';

const SPACING = [
    {
        name: 'sm',
        token: '--spacing-sm',
        utility: 'p-sm · gap-sm · m-sm',
        px: 8,
        use: 'Tight gaps inside controls, icon padding',
    },
    {
        name: 'md',
        token: '--spacing-md',
        utility: 'p-md · gap-md · m-md',
        px: 16,
        use: 'Default stack rhythm, field spacing',
    },
    {
        name: 'lg',
        token: '--spacing-lg',
        utility: 'p-lg · gap-lg · m-lg',
        px: 24,
        use: 'Section padding, card internal breathing room',
    },
];

export default {
    title: 'Design System/01. Tokens/Spacing & Elevation',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Spacing, radius, and elevation from `resources/css/app.css`. Prefer spacing tokens for intentional rhythm; Tailwind scale (`p-4`, `gap-6`) is fine when matching existing layouts.',
            },
        },
    },
};

export const Scale = {
    name: 'Spacing, radius & elevation',
    render: () => (
        <div className={FRAME}>
            <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-brand-primary">
                Spacing & Elevation
            </h1>
            <p className="mt-2 max-w-2xl font-body text-sm text-body">
                Shared layout language for landing and product. Cards, inputs, and primary buttons use a 12px
                corner radius.
            </p>

            <section className="mt-10">
                <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-heading">Spacing</h2>
                <p className="mt-1 font-body text-sm text-body">
                    Tokens: <code className="font-medium">sm 8</code> · <code className="font-medium">md 16</code> ·{' '}
                    <code className="font-medium">lg 24</code>
                </p>
                <div className="mt-4 space-y-4">
                    {SPACING.map((item) => (
                        <div key={item.name} className="flex items-start gap-4">
                            <div
                                className="mt-1 h-8 shrink-0 rounded-sm bg-brand-primary"
                                style={{ width: item.px }}
                                aria-hidden
                            />
                            <div>
                                <p className="m-0 font-montserrat text-sm font-bold text-heading">{item.name}</p>
                                <p className="m-0 font-body text-xs text-body">
                                    {item.token} · {item.px}px · <code className="font-medium">{item.utility}</code>
                                </p>
                                <p className="m-0 mt-0.5 font-body text-xs text-form-label">{item.use}</p>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="mt-6 flex flex-wrap gap-md rounded-card border border-border-light bg-surface-soft p-lg">
                    <span className="rounded-card bg-white px-md py-sm font-montserrat text-caption font-bold text-heading shadow-sm">
                        gap-md + p-lg
                    </span>
                    <span className="rounded-card bg-white px-md py-sm font-montserrat text-caption font-bold text-heading shadow-sm">
                        Demo cluster
                    </span>
                    <span className="rounded-card bg-white px-md py-sm font-montserrat text-caption font-bold text-heading shadow-sm">
                        Soft surface
                    </span>
                </div>
            </section>

            <section className="mt-10">
                <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-heading">Radius</h2>
                <p className="mt-1 font-body text-sm text-body">
                    <code className="font-medium">--radius-card</code> · 12px · utility{' '}
                    <code className="font-medium">rounded-card</code> (same as product <code className="font-medium">rounded-[12px]</code>)
                </p>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-card border border-border-light bg-white p-md shadow-sm">
                        <p className="m-0 font-montserrat text-sm font-bold text-heading">rounded-card</p>
                        <p className="mt-1 font-body text-xs text-body">Cards, inputs, primary buttons, sheets</p>
                    </div>
                    <div className="rounded-full border border-border-light bg-white px-md py-sm shadow-sm">
                        <p className="m-0 font-montserrat text-sm font-bold text-heading">rounded-full</p>
                        <p className="mt-1 font-body text-xs text-body">Avatar / icon-only circular controls</p>
                    </div>
                    <div className="rounded-sm border border-border-light bg-white p-md shadow-sm">
                        <p className="m-0 font-montserrat text-sm font-bold text-heading">rounded-sm</p>
                        <p className="mt-1 font-body text-xs text-body">Checkboxes, tiny chrome only</p>
                    </div>
                </div>
            </section>

            <section className="mt-10">
                <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-heading">Elevation</h2>
                <div className="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div className="rounded-card border border-border-light bg-white p-lg shadow-sm">
                        <p className="m-0 font-montserrat text-sm font-bold text-heading">shadow-sm</p>
                        <p className="mt-1 font-body text-xs text-body">Inputs, default buttons, field chrome</p>
                    </div>
                    <div className="rounded-card border border-border-light bg-white p-lg shadow-md">
                        <p className="m-0 font-montserrat text-sm font-bold text-heading">shadow-md</p>
                        <p className="mt-1 font-body text-xs text-body">Meal cards, hover lift</p>
                    </div>
                    <div className="rounded-card border border-border-light bg-white p-lg shadow-2xl">
                        <p className="m-0 font-montserrat text-sm font-bold text-heading">shadow-2xl</p>
                        <p className="mt-1 font-body text-xs text-body">Portaled menus and sheets</p>
                    </div>
                </div>
            </section>
        </div>
    ),
};
