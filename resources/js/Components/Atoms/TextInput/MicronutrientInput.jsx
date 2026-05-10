import { useId } from 'react';

/**
 * Single multi-line field for micronutrient data (matches TextInput chrome; Smart Kitchen radius + focus ring).
 *
 * @param {{
 *   label: string;
 *   value: string;
 *   onChange: (event: import('react').ChangeEvent<HTMLTextAreaElement>) => void;
 *   placeholder?: string;
 *   error?: string;
 *   rows?: number;
 *   hint?: string;
 *   className?: string;
 * }} props
 */
export default function MicronutrientInput({
    label,
    value,
    onChange,
    placeholder,
    error,
    rows = 5,
    hint,
    className = '',
}) {
    const generatedId = useId();
    const inputId = generatedId;
    const errorId = `${inputId}-error`;
    const hintDomId = hint ? `${inputId}-hint` : undefined;

    const boxNormal =
        'relative flex w-full min-h-[120px] bg-white ' +
        'rounded-[12px] border border-[#E5E7EB] shadow-sm ' +
        'transition-[border-color,box-shadow] duration-200 ' +
        'focus-within:border-[#6E8C47] ' +
        'focus-within:shadow-[0_1px_2px_rgba(0,0,0,0.05),inset_0_0_0_1px_rgba(110,140,71,0.18)]';

    const boxError =
        'relative flex w-full min-h-[120px] bg-white ' +
        'rounded-[12px] border border-status-error shadow-sm ' +
        'transition-[border-color,box-shadow] duration-200 ' +
        'focus-within:border-status-error ' +
        'focus-within:shadow-[0_1px_2px_rgba(0,0,0,0.05),inset_0_0_0_1px_rgba(196,79,93,0.2)]';

    const boxClassName = error ? boxError : boxNormal;

    const textareaClassName = [
        'min-h-[120px] w-full resize-y border-none bg-transparent font-body text-[16px] tracking-tight',
        'text-[#364153] placeholder:text-[#364153]/50',
        'rounded-[12px] px-[20px] py-[14px] outline-none ring-0',
        'focus:outline-none focus-visible:outline-none focus:ring-0 focus-visible:ring-0',
        'disabled:cursor-not-allowed disabled:text-[#364153]/40 disabled:placeholder:text-[#364153]/25',
    ].join(' ');

    const describedBy = [error ? errorId : null, hintDomId].filter(Boolean).join(' ') || undefined;

    return (
        <div className={`block w-full max-w-[492px] text-left ${className}`.trim()}>
            <label
                htmlFor={inputId}
                className="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94 dark:text-zinc-300"
            >
                {label}
            </label>
            {hint ? (
                <p id={hintDomId} className="mb-2 text-sm font-medium text-[#555555]">
                    {hint}
                </p>
            ) : null}
            <div className={boxClassName}>
                <textarea
                    id={inputId}
                    name="micronutrients"
                    rows={rows}
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={describedBy}
                    className={textareaClassName}
                />
            </div>
            {error ? (
                <p id={errorId} className="mt-1.5 text-sm text-status-error" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
