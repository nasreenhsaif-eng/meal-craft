import PrimaryButton from './PrimaryButton.jsx';

export default {
    title: 'Design System/02. Atoms/Button/PrimaryButton',
    component: PrimaryButton,
    parameters: {
        canvasBackground: 'white',
        docs: {
            description: {
                component:
                    'Kitchen logistics alias of `Button` with `variant="primary"`. Prefer importing `Button` for new work. ' +
                    'Used only on Kitchen Shopping List / Daily Sheet CSV actions.',
            },
        },
    },
};

/** Kitchen sheet CSV action — same styles as Button primary. */
export const Default = {
    name: 'Kitchen logistics alias',
    render: () => (
        <div className="min-h-screen w-full bg-white p-6">
            <PrimaryButton type="button" label="Generate CSV for Google Drive" size="sm" />
        </div>
    ),
};
