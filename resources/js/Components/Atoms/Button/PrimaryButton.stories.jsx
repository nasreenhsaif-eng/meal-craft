import PrimaryButton from './PrimaryButton.jsx';

export default {
    title: 'Design System/02. Atoms/Button/PrimaryButton',
    component: PrimaryButton,
    parameters: {
        canvasBackground: 'white',
    },
};

/** Kitchen sheet CSV action — `#556C37` overrides on Pill primary. */
export const Default = {
    render: () => (
        <div className="min-h-screen w-full bg-white p-6">
            <PrimaryButton type="button" label="Generate CSV for Google Drive" size="sm" />
        </div>
    ),
};
