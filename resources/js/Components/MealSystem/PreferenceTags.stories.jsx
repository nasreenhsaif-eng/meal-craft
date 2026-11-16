import PreferenceTags from './PreferenceTags.jsx';

export default {
    title: 'Design System/02. Atoms/Badge/PreferenceTags',
    component: PreferenceTags,
    parameters: {
        layout: 'padded',
        canvasBackground: 'white',
    },
};

export const Dislikes = {
    name: 'Dislikes',
    render: () => (
        <div className="bg-white p-8">
            <PreferenceTags tags={['Dairy', 'Mushrooms', 'Cilantro']} />
        </div>
    ),
};
