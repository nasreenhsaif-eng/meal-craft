import { useState } from 'react';
import { AdminLayout } from '../../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../../Components/Admin/AdminSidebar.jsx';
import LibraryCardMock from './LibraryCardMock.jsx';
import ProtocolEditorMock from './ProtocolEditorMock.jsx';

export default {
    title: 'Design System/05. Templates & Pages/Mocks/Balanced Anti-inflammatory',
    component: ProtocolEditorMock,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Visual mock only. Title and description on the left; one-line Plan week field on the right that opens the calendar dropdown. The library card shows the published date range under the title.',
            },
        },
    },
};

export const ProtocolEditor = {
    name: 'Protocol editor',
    render: function Render() {
        const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.mealPlans);

        return (
            <AdminLayout
                pageTitle="Meal Plan Library"
                activePath={activePath}
                onNavigate={setActivePath}
                showSearch={false}
            >
                <ProtocolEditorMock />
            </AdminLayout>
        );
    },
};

export const LibraryCard = {
    name: 'Library card',
    parameters: { layout: 'padded' },
    render: () => (
        <div className="box-border flex w-full justify-center bg-[#F8F9F6] p-6">
            <div className="w-full max-w-[310px]">
                <LibraryCardMock />
            </div>
        </div>
    ),
};
