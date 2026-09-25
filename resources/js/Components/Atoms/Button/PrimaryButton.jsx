import Button from './Button.jsx';

/**
 * Primary action — thin wrapper around {@link Button} `primary`.
 * Optional admin-token tint overrides via `className`.
 */
export default function PrimaryButton({ className = '', size, label, type, ...rest }) {
    return (
        <Button
            {...rest}
            type={type}
            size={size}
            label={label}
            variant="primary"
            className={className}
        />
    );
}
