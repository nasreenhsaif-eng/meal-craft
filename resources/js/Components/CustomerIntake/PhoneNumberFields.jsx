import { useEffect, useMemo, useState } from 'react';
import DropdownTextInput from '../Atoms/TextInput/DropdownTextInput.jsx';
import TextInput from '../Atoms/TextInput/TextInput.jsx';
import {
    dialForCountryAbbr,
    phoneCountryCodes,
    phoneCountryOptions,
    preferredAbbrForDial,
} from './phoneCountryOptions.js';

export const PHONE_COUNTRY_OPTIONS = phoneCountryOptions.map(({ value, label }) => ({ value, label }));
export const PHONE_COUNTRY_CODES = phoneCountryCodes;

/**
 * @param {string | null | undefined} phone
 * @returns {{ countryAbbr: string; countryCode: string; nationalNumber: string }}
 */
export function splitPhone(phone) {
    const trimmed = String(phone ?? '').trim();

    if (trimmed === '') {
        return { countryAbbr: 'BH', countryCode: '+973', nationalNumber: '' };
    }

    const digitsWithPlus = trimmed.startsWith('+')
        ? `+${trimmed.slice(1).replace(/\D/g, '')}`
        : `+${trimmed.replace(/\D/g, '')}`;

    for (const code of phoneCountryCodes) {
        if (digitsWithPlus.startsWith(code)) {
            return {
                countryAbbr: preferredAbbrForDial(code),
                countryCode: code,
                nationalNumber: digitsWithPlus.slice(code.length),
            };
        }
    }

    return {
        countryAbbr: 'BH',
        countryCode: '+973',
        nationalNumber: digitsWithPlus.replace(/^\+/, ''),
    };
}

/**
 * @param {string} countryCode
 * @param {string} nationalNumber
 * @returns {string}
 */
export function combinePhone(countryCode, nationalNumber) {
    const code = String(countryCode || '+973').startsWith('+')
        ? String(countryCode || '+973')
        : `+${String(countryCode || '973').replace(/\D/g, '')}`;
    const national = String(nationalNumber ?? '').replace(/\D/g, '');

    if (national === '') {
        return '';
    }

    return `${code}${national}`;
}

/**
 * Country code + national number fields that write a combined E.164 `phone` value.
 *
 * @param {{
 *   phone: string;
 *   onPhoneChange: (phone: string) => void;
 *   error?: string;
 *   label?: string;
 *   disabled?: boolean;
 *   name?: string;
 *   required?: boolean;
 * }} props
 */
export default function PhoneNumberFields({
    phone,
    onPhoneChange,
    error,
    label = 'Mobile number',
    disabled = false,
    name,
    required = false,
}) {
    const split = useMemo(() => splitPhone(phone), [phone]);
    const [countryAbbr, setCountryAbbr] = useState(split.countryAbbr);
    const [nationalNumber, setNationalNumber] = useState(split.nationalNumber);

    useEffect(() => {
        setCountryAbbr(split.countryAbbr);
        setNationalNumber(split.nationalNumber);
    }, [split.countryAbbr, split.nationalNumber]);

    /**
     * @param {string} nextAbbr
     * @param {string} nextNational
     */
    const commit = (nextAbbr, nextNational) => {
        setCountryAbbr(nextAbbr);
        setNationalNumber(nextNational);
        onPhoneChange(combinePhone(dialForCountryAbbr(nextAbbr), nextNational));
    };

    const dropdownOptions = useMemo(
        () => phoneCountryOptions.map(({ value, label: optionLabel }) => ({ value, label: optionLabel })),
        [],
    );

    return (
        <div className="w-full min-w-0">
            <p
                className={[
                    'mb-2 font-montserrat text-sm font-bold leading-snug tracking-tight',
                    error ? 'text-status-error' : 'text-grey-94',
                ].join(' ')}
            >
                {label}
            </p>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(10rem,1.2fr)_minmax(0,1fr)] sm:gap-3">
                <DropdownTextInput
                    label="Country"
                    hideLabel
                    listboxAriaLabel="Country and dial code"
                    typeaheadJump
                    value={countryAbbr}
                    options={dropdownOptions}
                    onChange={(nextAbbr) => {
                        if (disabled) {
                            return;
                        }
                        commit(nextAbbr, nationalNumber);
                    }}
                    className="!max-w-none"
                />
                <TextInput
                    label="National number"
                    className="!max-w-none [&_label]:sr-only"
                    type="tel"
                    inputMode="tel"
                    placeholder="XXXX XXXX"
                    value={nationalNumber}
                    onChange={(event) => {
                        if (disabled) {
                            return;
                        }
                        commit(countryAbbr, event.target.value);
                    }}
                    autoComplete="tel-national"
                    disabled={disabled}
                    required={required}
                />
            </div>
            {name ? <input type="hidden" name={name} value={phone} required={required} /> : null}
            {error ? (
                <p className="mt-1.5 text-sm text-status-error" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
