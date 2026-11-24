const FRAME = 'box-border min-h-screen w-full bg-white p-6 text-heading sm:p-8';

/**
 * @param {{ name: string; token: string; hex: string; fill?: string; onDark?: boolean }} props
 */
function Swatch({ name, token, hex, fill, onDark = false }) {
    return (
        <div className="min-w-0">
            <div
                className={`h-20 w-full rounded-card border border-border-light shadow-sm ${onDark ? 'ring-1 ring-black/10' : ''}`}
                style={{ backgroundColor: fill ?? hex }}
            />
            <p className="mt-2 font-montserrat text-sm font-bold tracking-tight text-heading">{name}</p>
            <p className="font-body text-xs text-body">{token}</p>
            <p className="font-body text-xs uppercase tracking-wide text-form-label">{hex}</p>
        </div>
    );
}

/**
 * @param {{ title: string; children: import('react').ReactNode }} props
 */
function Section({ title, children }) {
    return (
        <section className="mt-10 first:mt-0">
            <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-heading">{title}</h2>
            <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">{children}</div>
        </section>
    );
}

export default {
    title: 'Design System/01. Tokens/Colors',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
};

export const Palette = {
    render: () => (
        <div className={FRAME}>
            <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-brand-primary">Colors</h1>
            <p className="mt-2 max-w-2xl font-body text-sm text-body">
                Tokens from <code className="font-medium">resources/css/app.css</code>. Nutrient badges reuse brand
                colors (B12 = Primary, Zinc = Secondary, Folate = Accent, Iron = Error).
            </p>

            <Section title="Brand">
                <Swatch name="Primary" token="--brand-primary-default" hex="#6E8C47" />
                <Swatch name="Primary hover" token="--brand-primary-hover" hex="#617B3E" />
                <Swatch name="Primary pressed" token="--brand-primary-pressed" hex="#5A6B44" />
                <Swatch name="Secondary" token="--brand-secondary" hex="#D8A933" />
                <Swatch name="Accent" token="--brand-accent" hex="#8F55A8" />
                <Swatch name="Magnesium" token="--tags-magnesium" hex="#2F4C9B" />
            </Section>

            <Section title="Selection washes">
                <Swatch
                    name="Default"
                    token="bg-brand-primary/10"
                    hex="#6E8C47 @ 10%"
                    fill="rgba(110, 140, 71, 0.1)"
                />
                <Swatch
                    name="Hover"
                    token="bg-brand-primary/20"
                    hex="#6E8C47 @ 20%"
                    fill="rgba(110, 140, 71, 0.2)"
                />
                <Swatch
                    name="Selected"
                    token="bg-brand-primary/30"
                    hex="#6E8C47 @ 30%"
                    fill="rgba(110, 140, 71, 0.3)"
                />
            </Section>

            <Section title="Neutral">
                <Swatch name="Canvas" token="--color-white-solid" hex="#FFFFFF" />
                <Swatch name="Soft surface" token="--color-surface-soft · --color-grey-96" hex="#F6F6F4" />
                <Swatch name="Hairline" token="--color-border-light · --color-grey-91" hex="#E5E7EB" />
                <Swatch name="Heading text" token="--color-heading" hex="#262A22" />
                <Swatch name="Body text" token="--color-body · --color-grey-33" hex="#555555" />
                <Swatch name="Form label" token="--color-form-label · --color-grey-94" hex="#616a75" />
                <Swatch name="Dark background" token="--color-surface-dark" hex="#0E1420" onDark />
            </Section>

            <Section title="Status">
                <Swatch name="Error" token="--status-error" hex="#C44F5D" />
            </Section>
        </div>
    ),
};
