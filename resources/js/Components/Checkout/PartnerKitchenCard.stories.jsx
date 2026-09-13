import PartnerKitchenCard from './PartnerKitchenCard.jsx';

export default {
    title: 'MealCraft/Components/Checkout/PartnerKitchenCard',
    component: PartnerKitchenCard,
    parameters: {
        layout: 'padded',
        docs: {
            description: {
                component:
                    'Horizontal partner-kitchen card for checkout — logo, star rating, reviews link, plan price, and primary select action.',
            },
        },
    },
    argTypes: {
        onReviewsClick: { action: 'reviews' },
        onSelect: { action: 'select' },
    },
    args: {
        name: 'Picnic',
        logoUrl: '/images/partners/picniq-gourmet.png',
        rating: 4.5,
        reviewCount: 128,
        price: 'BHD 385',
        billingCadence: 'week',
    },
};

export const Default = {
    name: 'Picnic',
    render: (args) => (
        <div className="w-full max-w-3xl bg-bg-page p-6">
            <PartnerKitchenCard {...args} />
        </div>
    ),
};

export const WithoutLogo = {
    name: 'Without logo',
    args: {
        logoUrl: null,
    },
    render: (args) => (
        <div className="w-full max-w-3xl bg-bg-page p-6">
            <PartnerKitchenCard {...args} />
        </div>
    ),
};

export const Mobile = {
    name: 'Mobile stack',
    render: (args) => (
        <div className="w-full max-w-sm bg-bg-page p-4">
            <PartnerKitchenCard {...args} />
        </div>
    ),
};
