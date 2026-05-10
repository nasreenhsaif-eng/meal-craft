import TextLink from './TextLink.jsx';

export default {
    title: 'MealCraft/Atoms/Buttons & Links/TextLink',
    component: TextLink,
    parameters: { layout: 'padded' },
    argTypes: {
        href: { control: 'text' },
    },
};

export const Default = {
    args: {
        href: '#',
    },
    render: (args) => (
        <div className="space-y-3 bg-white p-8">
            <TextLink {...args}>Forgot Password?</TextLink>
            <div className="text-sm text-[#364153]">
                <span className="font-medium">New here?</span>{' '}
                <TextLink {...args} className="font-bold">
                    Sign up
                </TextLink>
            </div>
        </div>
    ),
};

