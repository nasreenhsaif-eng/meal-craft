import ProtocolTags, { ProtocolTag } from './ProtocolTags.jsx';

export default {
    title: 'Design System/02. Atoms/Badge/ProtocolTags',
    component: ProtocolTags,
    parameters: {
        layout: 'padded',
        canvasBackground: 'white',
    },
};

export const Set = {
    name: 'Set',
    render: () => (
        <div className="bg-white p-8">
            <ProtocolTags tags={['Balanced', 'Sickle Cell', 'Cycle Sync']} />
        </div>
    ),
};

export const OnCard = {
    name: 'On card',
    render: () => (
        <div className="relative h-32 w-64 overflow-hidden rounded-[12px] bg-[#F8F9F6] p-8">
            <div className="absolute right-3 top-3">
                <ProtocolTag label="Sickle Cell" className="bg-white/90 shadow-sm backdrop-blur-sm" />
            </div>
        </div>
    ),
};
