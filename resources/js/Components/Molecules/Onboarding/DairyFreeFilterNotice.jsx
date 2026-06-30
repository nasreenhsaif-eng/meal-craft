import Button from '../../Atoms/Button/Button.jsx';

/**
 * Balanced guidance when a customer opts to avoid dairy during onboarding.
 *
 * @param {{ onDismiss: () => void }} props
 */
export default function DairyFreeFilterNotice({ onDismiss }) {
    return (
        <div
            role="status"
            className="mt-4 w-full rounded-[12px] border border-amber-200 bg-amber-50/90 px-4 py-4 sm:px-5"
        >
            <h3 className="font-montserrat text-sm font-bold text-[#262A22]">
                A Note on Going Dairy-Free
            </h3>
            <div className="mt-2 space-y-2 font-body text-sm leading-relaxed text-[#374151]">
                <p>
                    Unless you have a medical condition like lactose intolerance or an allergy, we do
                    not generally advise going completely dairy-free.
                </p>
                <p>
                    Dairy provides a unique, highly bioavailable matrix of Calcium, Vitamin K2, Iodine,
                    and B vitamins working together to protect your bones, heart, and thyroid—a
                    combination that is remarkably difficult to replace through other foods alone.
                </p>
                <p>
                    If you are avoiding dairy due to quality concerns, rest assured that we only use
                    premium dairy products completely free from preservatives, emulsifiers, and
                    additives.
                </p>
                <p>
                    <span className="font-medium text-[#262A22]">Avoiding for health/medical reasons?</span>{' '}
                    Your plan will remain strictly dairy-free.
                </p>
                <p>
                    <span className="font-medium text-[#262A22]">Choosing by preference?</span> We highly
                    encourage keeping our clean dairy options for optimal nutrition. If you still wish
                    to exclude it, we will prioritize alternative whole-food sources (like leafy greens,
                    tahini, and sardines) to help bridge the nutritional gap.
                </p>
            </div>
            <div className="mt-4">
                <Button
                    type="button"
                    label="Got it"
                    size="sm"
                    className="w-full uppercase tracking-[0.08em] sm:w-auto sm:min-w-[140px]"
                    onClick={onDismiss}
                />
            </div>
        </div>
    );
}
