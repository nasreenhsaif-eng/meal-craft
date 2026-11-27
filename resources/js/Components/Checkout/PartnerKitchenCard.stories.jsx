import PartnerKitchenCard from './PartnerKitchenCard.jsx';

export default {
    title: 'Design System/04. Organisms/DataCard/PartnerKitchenCard',
    component: PartnerKitchenCard,
    parameters: {
        layout: 'padded',
        docs: {
            description: {
                component:
                    'Vertical partner-kitchen card for checkout — logo on top, then rating, reviews, and select.',
            },
        },
    },
    argTypes: {
        onReviewsClick: { action: 'reviews' },
        onSelect: { action: 'select' },
    },
    args: {
        name: 'Picniq',
        logoUrl: '/images/partners/picniq-gourmet.png',
        rating: 4.5,
        reviewCount: 128,
    },
};

/** Vertical Picniq card with logo (matches checkout delivery). */
export const Default = {
    name: 'Picniq',
    render: (args) => (
        // Avoid max-w-sm/md/lg — theme --spacing-sm/md/lg collide with those utilities.
        <div className="w-full max-w-[24rem] p-6">
            <PartnerKitchenCard {...args} />
        </div>
    ),
};
