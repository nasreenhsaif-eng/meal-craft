import { useState } from 'react';
import { AdminLayout } from '../../Components/Admin/AdminLayout.jsx';
import { ADMIN_NAV_PATHS } from '../../Components/Admin/AdminSidebar.jsx';
import CustomerProfileShow from './CustomerProfileShow.jsx';

const WIDE = 'mx-auto w-full max-w-[1400px]';

const STORY_FORM = {
    first_name: 'Nasreen',
    last_name: 'Saif',
    email: 'nasreen@example.com',
    phone: '+973 3317 7718',
    contact_preference: 'whatsapp',
    date_of_birth: '1994-03-15',
    age: '32',
    gender: 'female',
    height_cm: '165',
    weight_kg: '68',
    target_weight_kg: '65',
    activity_level: 'lightly_active',
    daily_calorie_target: '1700',
    daily_calories_min: '1575',
    daily_calories_max: '1825',
    macro_split_style: 'balanced',
    delivery_time: 'morning',
    dislikes_and_allergies: 'Dairy',
    diet_protocol: 'balanced',
    plan_type: 'full',
    plan_days: '28',
    area: 'Adliya',
    block: '318',
    road: '1801',
    house_number: '12',
    gate_flat_number: '4',
    country: 'Bahrain',
    planned_start_date: '2026-11-15',
    follow_instagram: true,
    customer_question: '',
};

const STORY_OPTIONS = {
    contactPreferences: [
        { value: 'whatsapp', label: 'WhatsApp' },
        { value: 'email', label: 'Email' },
    ],
    deliveryTimes: [{ value: 'morning', label: 'Morning' }],
    genders: [
        { value: 'female', label: 'Female' },
        { value: 'male', label: 'Male' },
    ],
    activityLevels: [{ value: 'lightly_active', label: 'Lightly active' }],
    planTypes: [{ value: 'full', label: 'Full Craft' }],
    planDays: [{ value: '28', label: '28 days' }],
    dietProtocols: [{ value: 'balanced', label: 'Balanced' }],
};

const STORY_SUBSCRIPTION = {
    statusLabel: 'Paid',
    mealPlanName: 'Full Craft',
    days: 28,
    startDate: '2026-11-15',
    expiryDate: '2026-12-12',
    deliveries: 28,
    fulfillmentChoice: 'Partner kitchen',
    paidAt: '2026-11-14',
    frozenDays: [],
};

function CustomerProfileShowStoryShell({ children }) {
    const [activePath, setActivePath] = useState(ADMIN_NAV_PATHS.customerProfiles);
    return (
        <AdminLayout
            pageTitle="Customer profile"
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
    title: 'Design System/06. Pages/Admin/CustomerProfileShow',
    component: CustomerProfileShow,
    parameters: { layout: 'fullscreen' },
};

export const ViewOnly = {
    name: 'View only',
    render: () => (
        <CustomerProfileShowStoryShell>
            <CustomerProfileShow
                form={STORY_FORM}
                options={STORY_OPTIONS}
                uniqueCode="MC-1001"
                customerName="Nasreen Saif"
                subscription={STORY_SUBSCRIPTION}
                canEdit={false}
            />
        </CustomerProfileShowStoryShell>
    ),
};

export const Editable = {
    name: 'Editable',
    render: () => (
        <CustomerProfileShowStoryShell>
            <CustomerProfileShow
                form={STORY_FORM}
                options={STORY_OPTIONS}
                uniqueCode="MC-1001"
                customerName="Nasreen Saif"
                subscription={STORY_SUBSCRIPTION}
                canEdit
                submitUrl="/admin/customers/1"
            />
        </CustomerProfileShowStoryShell>
    ),
};