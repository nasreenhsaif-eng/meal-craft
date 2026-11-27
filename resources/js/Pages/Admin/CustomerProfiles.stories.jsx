import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import CustomerProfiles from './CustomerProfiles.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

const STORY_OPTIONS = {
    contactPreferences: [
        { value: 'whatsapp', label: 'WhatsApp' },
        { value: 'email', label: 'Email' },
    ],
    deliveryTimes: [
        { value: 'morning', label: 'Morning' },
        { value: 'afternoon', label: 'Afternoon' },
    ],
    genders: [
        { value: 'female', label: 'Female' },
        { value: 'male', label: 'Male' },
    ],
    activityLevels: [
        { value: 'lightly_active', label: 'Lightly active' },
        { value: 'moderately_active', label: 'Moderately active' },
    ],
    planTypes: [{ value: 'full', label: 'Full Craft' }],
    planDays: [
        { value: '20', label: '20 days' },
        { value: '28', label: '28 days' },
    ],
    dietProtocols: [{ value: 'balanced', label: 'Balanced' }],
};

const STORY_CUSTOMERS = [
    {
        id: 1,
        name: 'Nasreen Saif',
        uniqueCode: 'MC-1001',
        showUrl: '/admin/customers/1',
        updateUrl: '/admin/customers/1',
        onboardingCompletedAt: '2026-10-01T00:00:00.000Z',
        fields: {
            first_name: 'Nasreen',
            last_name: 'Saif',
            email: 'nasreen@example.com',
            phone: '+973 3317 7718',
            contact_preference: 'whatsapp',
            delivery_time: 'morning',
            planned_start_date: '2026-11-15',
            area: 'Adliya',
            block: '318',
            road: '1801',
            house_number: '12',
            gate_flat_number: '4',
            country: 'Bahrain',
            gender: 'female',
            height_cm: 165,
            weight_kg: 68,
            target_weight_kg: 65,
            activity_level: 'lightly_active',
            plan_type: 'full',
            plan_days: 28,
            diet_protocol: 'balanced',
            daily_calorie_target: 1700,
            follow_instagram: true,
            customer_question: '',
        },
        subscription: {
            statusLabel: 'Paid',
            mealPlanName: 'Full Craft',
            days: 28,
            startDate: '2026-11-15',
            expiryDate: '2026-12-12',
            deliveries: 28,
            fulfillmentChoice: 'Partner kitchen',
            paidAt: '2026-11-14',
            frozenDays: [],
        },
    },
    {
        id: 2,
        name: 'Ishaq Ibrahim',
        uniqueCode: 'MC-1002',
        showUrl: '/admin/customers/2',
        updateUrl: '/admin/customers/2',
        onboardingStep: 'daily_targets',
        fields: {
            first_name: 'Ishaq',
            last_name: 'Ibrahim',
            email: 'ishaq@example.com',
            phone: '+973 3900 1122',
            contact_preference: 'email',
            planned_start_date: '2026-12-01',
            country: 'Bahrain',
        },
        subscription: null,
    },
];

function CustomerProfilesStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.customerProfiles);
    return (
        <AdminLayout
            pageTitle="Customer Profiles"
            activePath={activePath}
            onNavigate={setActivePath}
            showSearch={false}
            contentWrapperClassName={WIDE}
        >
            {children}
        </AdminLayout>
    );
}

export default {
    title: 'Design System/06. Pages/Admin/CustomerProfiles',
    component: CustomerProfiles,
    parameters: { layout: 'fullscreen' },
};

export const Default = {
    name: 'Customer sheet',
    render: () => (
        <CustomerProfilesStoryShell>
            <CustomerProfiles customers={STORY_CUSTOMERS} canEdit options={STORY_OPTIONS} />
        </CustomerProfilesStoryShell>
    ),
};