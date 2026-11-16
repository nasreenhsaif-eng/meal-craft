const FRAME = 'box-border min-h-screen w-full bg-white p-6 text-[#364153] sm:p-8';

const SPACING = [
    { name: 'sm', token: '--spacing-sm', px: 8 },
    { name: 'md', token: '--spacing-md', px: 16 },
    { name: 'lg', token: '--spacing-lg', px: 24 },
];

export default {
    title: 'Design System/01. Foundations/Spacing & Elevation',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
};

export const Scale = {
    name: 'Spacing & elevation',
    render: () => (
        <div className={FRAME}>
            <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-[#6E8C47]">
                Spacing & Elevation
            </h1>
            <p className="mt-2 max-w-2xl font-body text-sm text-[#555555]">
                Spacing tokens from <code className="font-medium">app.css</code>. Shadows match buttons and cards in
                the product.
            </p>

            <h2 className="mt-10 font-montserrat text-lg font-bold tracking-tight text-[#262A22]">Spacing</h2>
            <div className="mt-4 space-y-4">
                {SPACING.map((item) => (
                    <div key={item.name} className="flex items-center gap-4">
                        <div className="h-8 rounded-sm bg-[#6E8C47]" style={{ width: item.px }} />
                        <div>
                            <p className="m-0 font-montserrat text-sm font-bold">{item.name}</p>
                            <p className="m-0 font-body text-xs text-[#555555]">
                                {item.token} · {item.px}px
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            <h2 className="mt-10 font-montserrat text-lg font-bold tracking-tight text-[#262A22]">Elevation</h2>
            <div className="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div className="rounded-[12px] border border-[#E5E7EB] bg-white p-6 shadow-sm">
                    <p className="m-0 font-montserrat text-sm font-bold">shadow-sm</p>
                    <p className="mt-1 font-body text-xs text-[#555555]">Inputs, default buttons, field chrome</p>
                </div>
                <div className="rounded-[12px] border border-[#E5E7EB] bg-white p-6 shadow-md">
                    <p className="m-0 font-montserrat text-sm font-bold">shadow-md</p>
                    <p className="mt-1 font-body text-xs text-[#555555]">Meal cards, hover lift</p>
                </div>
                <div className="rounded-[12px] border border-[#E5E7EB] bg-white p-6 shadow-2xl">
                    <p className="m-0 font-montserrat text-sm font-bold">shadow-2xl</p>
                    <p className="mt-1 font-body text-xs text-[#555555]">Portaled menus and sheets</p>
                </div>
            </div>
        </div>
    ),
};
