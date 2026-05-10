import { useId, useState } from 'react';
import { IconEye, IconEyeOff } from '../Icons.jsx';

/**
 * Single-line text field — fixed `h-[49px]` box with optional `prefixIcon` / `suffixIcon`
 * absolutely positioned at `left-[20px]` / `right-[20px]`; input padding clears the icons.
 *
 * @param {{
 *   label: string;
 *   placeholder?: string;
 *   type?: string;
 *   value: string;
 *   onChange: (event: import('react').ChangeEvent<HTMLInputElement>) => void;
 *   error?: string;
 *   prefixIcon?: import('react').ReactNode;
 *   suffixIcon?: import('react').ReactNode;
 *   suffixButton?: { icon: import('react').ReactNode; ariaLabel: string; onClick: () => void };
 *   revealPassword?: boolean;
 *   className?: string;
 *   id?: string;
 * }} props
 */
function TextInput({
    label,
    placeholder,
    type = 'text',
    value,
    onChange,
    error,
    prefixIcon,
    suffixIcon,
    suffixButton,
    revealPassword = false,
    className = '',
    id: idProp,
    ...props
}) {
    const generatedId = useId();
    const inputId = idProp ?? generatedId;
    const errorId = `${inputId}-error`;

    /** `overflow-hidden` + matching radius keeps the inner input’s focus paint inside the pill (no square corners). */
    const boxNormal =
        'relative w-full h-[49px] flex items-center overflow-hidden bg-white ' +
        'rounded-[12px] border border-[#E5E7EB] shadow-sm ' +
        'transition-[border-color,box-shadow] duration-200 ' +
        'focus-within:border-[#6E8C47] ' +
        'focus-within:shadow-[0_1px_2px_rgba(0,0,0,0.05),inset_0_0_0_1px_rgba(110,140,71,0.18)]';

    const boxError =
        'relative w-full h-[49px] flex items-center overflow-hidden bg-white ' +
        'rounded-[12px] border border-status-error shadow-sm ' +
        'transition-[border-color,box-shadow] duration-200 ' +
        'focus-within:border-status-error ' +
        'focus-within:shadow-[0_1px_2px_rgba(0,0,0,0.05),inset_0_0_0_1px_rgba(196,79,93,0.2)]';

    const boxClassName = error ? boxError : boxNormal;

    const shouldReveal = revealPassword && type === 'password';
    const [isVisible, setIsVisible] = useState(false);

    const computedType = shouldReveal ? (isVisible ? 'text' : 'password') : type;

    const effectiveSuffixButton =
        suffixButton ??
        (shouldReveal
            ? {
                  // Explicit mapping:
                  // - isVisible === false => password + open eye (click to show)
                  // - isVisible === true  => text + slashed eye (click to hide)
                  icon: isVisible ? <IconEyeOff /> : <IconEye />,
                  ariaLabel: isVisible ? 'Hide password' : 'Show password',
                  onClick: () => setIsVisible((v) => !v),
              }
            : null);

    let inputPad = 'px-[20px]';

    if (prefixIcon && (suffixIcon || effectiveSuffixButton)) {
        inputPad = 'pl-[50px] pr-[50px]';
    } else if (prefixIcon) {
        inputPad = 'pl-[50px] pr-[20px]';
    } else if (suffixIcon || effectiveSuffixButton) {
        inputPad = 'pl-[20px] pr-[50px]';
    }

    const inputClassName = [
        'h-full w-full min-w-0 flex-1 appearance-none border-none bg-transparent font-body text-[16px] tracking-tight',
        'text-[#364153] placeholder:text-[#364153]/50',
        /** Match pill radius so focus/autofill never draws a square over the wrapper */
        'rounded-[12px] outline-none ring-0',
        'focus:outline-none focus-visible:outline-none focus:ring-0 focus-visible:ring-0',
        'disabled:cursor-not-allowed disabled:text-[#364153]/40 disabled:placeholder:text-[#364153]/25',
        /** Neutralize Chrome/Safari autofill (default + focus — avoids gray tile on tab/focus) */
        '[&:-webkit-autofill]:[-webkit-box-shadow:0_0_0px_1000px_white_inset]',
        '[&:-webkit-autofill]:[-webkit-text-fill-color:inherit]',
        '[&:-webkit-autofill:hover]:[-webkit-box-shadow:0_0_0px_1000px_white_inset]',
        '[&:-webkit-autofill:focus]:[-webkit-box-shadow:0_0_0px_1000px_white_inset]',
        inputPad,
    ].join(' ');

    const iconWrap =
        'pointer-events-none absolute top-1/2 z-10 -translate-y-1/2 text-[#364153] [&_svg]:block [&_svg]:shrink-0';

    return (
        <div className={`block w-full max-w-[492px] text-left ${className}`.trim()}>
            <label
                htmlFor={inputId}
                className="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94 dark:text-zinc-300"
            >
                {label}
            </label>
            <div className={boxClassName}>
                {prefixIcon ? (
                    <span className={`${iconWrap} left-[20px]`} aria-hidden="true">
                        {prefixIcon}
                    </span>
                ) : null}
                <input
                    id={inputId}
                    type={computedType}
                    placeholder={placeholder}
                    value={value}
                    onChange={onChange}
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={error ? errorId : undefined}
                    className={inputClassName}
                    {...props}
                />
                {suffixIcon ? (
                    <span className={`${iconWrap} right-[20px]`} aria-hidden="true">
                        {suffixIcon}
                    </span>
                ) : null}
                {effectiveSuffixButton ? (
                    <button
                        type="button"
                        onClick={effectiveSuffixButton.onClick}
                        aria-label={effectiveSuffixButton.ariaLabel}
                        className="absolute right-[12px] top-1/2 z-20 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-[12px] text-[#5A6B44] transition-colors hover:bg-[#F9FAFB] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                    >
                        <span className="[&_svg]:h-4 [&_svg]:w-4">{effectiveSuffixButton.icon}</span>
                    </button>
                ) : null}
            </div>
            {error ? (
                <p id={errorId} className="mt-1.5 text-sm text-status-error" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

export default TextInput;
