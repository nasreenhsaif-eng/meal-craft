const FRAME = 'box-border min-h-screen w-full bg-white p-6 text-[#364153] sm:p-8';

/**
 * @param {{ name: string; token: string; hex: string; onDark?: boolean }} props
 */
function Swatch({ name, token, hex, onDark = false }) {
    return (
        <div className="min-w-0">
            <div
                className={`h-20 w-full rounded-[12px] border border-[#E5E7EB] shadow-sm ${onDark ? 'ring-1 ring-black/10' : ''}`}
                style={{ backgroundColor: hex }}
            />
            <p className="mt-2 font-montserrat text-sm font-bold tracking-tight">{name}</p>
            <p className="font-body text-xs text-[#555555]">{token}</p>
            <p className="font-body text-xs uppercase tracking-wide text-[#888888]">{hex}</p>
        </div>
    );
}

/**
 * @param {{ title: string; children: import('react').ReactNode }} props
 */
function Section({ title, children }) {
    return (
        <section className="mt-10 first:mt-0">
            <h2 className="m-0 font-montserrat text-lg font-bold tracking-tight text-[#262A22]">{title}</h2>
            <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">{children}</div>
        </section>
    );
}

export default {
    title: 'Design System/01. Foundations/Colors',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
};

export const Palette = {
    render: () => (
        <div className={FRAME}>
            <h1 className="m-0 font-montserrat text-2xl font-bold tracking-tight text-[#6E8C47]">Colors</h1>
            <p className="mt-2 max-w-2xl font-body text-sm text-[#555555]">
                Tokens from <code className="font-medium">resources/css/app.css</code>.
            </p>

            <Section title="Brand">
                <Swatch name="Primary" token="--brand-primary-default" hex="#6E8C47" />
                <Swatch name="Primary pressed" token="--brand-primary-pressed" hex="#5A6B44" />
                <Swatch name="Secondary" token="--brand-secondary" hex="#D8A933" />
                <Swatch name="Accent" token="--brand-accent" hex="#8F55A8" />
            </Section>

            <Section title="Protocol">
                <Swatch name="Selected" token="--brand-protocol-selected" hex="#536643" />
                <Swatch name="Selected hover" token="--brand-protocol-selected-hover" hex="#475538" />
                <Swatch name="Selected pressed" token="--brand-protocol-selected-pressed" hex="#3d4830" />
            </Section>

            <Section title="Neutral">
                <Swatch name="White" token="--color-white-solid" hex="#FFFFFF" />
                <Swatch name="Page" token="--brand-blue-page-background" hex="#F9FAFB" />
                <Swatch name="Grey 96" token="--color-grey-96" hex="#F6F6F4" />
                <Swatch name="Grey 95" token="--color-grey-95" hex="#F2F2F2" />
                <Swatch name="Grey 91 / border" token="--color-grey-91" hex="#E5E7EB" />
                <Swatch name="Grey 94 / labels" token="--color-grey-94" hex="#616a75" />
                <Swatch name="Grey 53" token="--color-grey-53" hex="#888888" />
                <Swatch name="Grey 33" token="--color-grey-33" hex="#555555" />
                <Swatch name="Ink 17" token="--color-chartreuse-green-17" hex="#2D3128" />
                <Swatch name="Ink 15" token="--color-chartreuse-green-15" hex="#262A22" />
            </Section>

            <Section title="Status">
                <Swatch name="Error" token="--status-error" hex="#C44F5D" />
            </Section>

            <Section title="Nutrient tags">
                <Swatch name="Folate" token="--tags-folate" hex="#8F55A8" />
                <Swatch name="Zinc" token="--tags-zink" hex="#D8A933" />
                <Swatch name="Iron" token="--tags-iron" hex="#C44F5D" />
                <Swatch name="B12" token="--tags-b12" hex="#6E8C47" />
                <Swatch name="Magnesium" token="--tags-magnesium" hex="#2F4C9B" />
            </Section>
        </div>
    ),
};
