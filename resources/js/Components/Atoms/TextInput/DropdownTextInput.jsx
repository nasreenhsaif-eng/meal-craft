import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { IconChevronDown } from '../SvgIcons.jsx';
import { portalMenuRectFromTrigger, TEXT_INPUT_ROOT } from './fieldLayout.js';

/**
 * @param {string | { value: string; label: string }} option
 * @returns {{ value: string; label: string }}
 */
function normalizeOption(option) {
    if (typeof option === 'string') {
        return { value: option, label: option };
    }

    return {
        value: String(option?.value ?? ''),
        label: String(option?.label ?? option?.value ?? ''),
    };
}

/**
 * Accent-insensitive lowercase label for prefix matching.
 *
 * @param {string} label
 * @returns {string}
 */
function normalizeForSearch(label) {
    return String(label)
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase();
}

/**
 * Minimalist dropdown styled like TextInput (but without native select blue highlights).
 *
 * @param {{
 *   label: string;
 *   value: string;
 *   options: Array<string | { value: string; label: string }>;
 *   onChange: (value: string) => void;
 *   className?: string;
 *   hideLabel?: boolean;
 *   listboxAriaLabel?: string — optional `aria-label` for the listbox when `hideLabel` is true (defaults to `label`).
 *   typeaheadJump?: boolean — typing while open scrolls to the first matching option label.
 * }} props
 */
export default function DropdownTextInput({
    label,
    value,
    options,
    onChange,
    className = '',
    hideLabel = false,
    listboxAriaLabel,
    typeaheadJump = false,
}) {
    const id = useId();
    const rootRef = useRef(null);
    const triggerRef = useRef(null);
    const listRef = useRef(null);
    const typeaheadBufferRef = useRef('');
    const typeaheadTimerRef = useRef(/** @type {ReturnType<typeof setTimeout> | null} */ (null));
    const [open, setOpen] = useState(false);
    const [menuRect, setMenuRect] = useState(null);
    const normalizedOptions = useMemo(
        () => (Array.isArray(options) ? options.map(normalizeOption) : []),
        [options],
    );
    const selectedLabel =
        normalizedOptions.find((option) => option.value === value)?.label ?? value;

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const onDocMouseDown = (event) => {
            if (!rootRef.current) {
                return;
            }
            const t = event.target;
            if (!(t instanceof Node)) {
                return;
            }
            if (rootRef.current.contains(t)) {
                return;
            }
            if (t.closest('[data-dropdown-text-input-portal]')) {
                return;
            }
            setOpen(false);
        };
        document.addEventListener('mousedown', onDocMouseDown);
        return () => document.removeEventListener('mousedown', onDocMouseDown);
    }, [open]);

    const listLabel = listboxAriaLabel ?? label;
    const menuId = useMemo(() => `${id}-listbox`, [id]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const updateRect = () => {
            const el = triggerRef.current;
            if (!el) {
                return;
            }
            setMenuRect(portalMenuRectFromTrigger(el.getBoundingClientRect()));
        };

        updateRect();

        window.addEventListener('resize', updateRect);
        window.addEventListener('scroll', updateRect, true);

        return () => {
            window.removeEventListener('resize', updateRect);
            window.removeEventListener('scroll', updateRect, true);
        };
    }, [open]);

    useEffect(() => {
        if (!open || !typeaheadJump) {
            typeaheadBufferRef.current = '';
            if (typeaheadTimerRef.current) {
                clearTimeout(typeaheadTimerRef.current);
                typeaheadTimerRef.current = null;
            }
            return undefined;
        }

        /**
         * @param {string} buffer
         */
        const scrollToPrefix = (buffer) => {
            if (!buffer || !listRef.current) {
                return;
            }

            const needle = normalizeForSearch(buffer);
            const match = normalizedOptions.find((option) =>
                normalizeForSearch(option.label).startsWith(needle),
            );

            if (!match) {
                return;
            }

            const item = listRef.current.querySelector(
                `[data-option-value="${CSS.escape(match.value)}"]`,
            );
            if (item instanceof HTMLElement) {
                item.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }
        };

        const resetBufferSoon = () => {
            if (typeaheadTimerRef.current) {
                clearTimeout(typeaheadTimerRef.current);
            }
            typeaheadTimerRef.current = setTimeout(() => {
                typeaheadBufferRef.current = '';
                typeaheadTimerRef.current = null;
            }, 750);
        };

        /**
         * @param {KeyboardEvent} event
         */
        const onKeyDown = (event) => {
            const target = event.target;
            if (
                target instanceof HTMLElement &&
                (target.tagName === 'INPUT' ||
                    target.tagName === 'TEXTAREA' ||
                    target.isContentEditable) &&
                !target.closest('[data-dropdown-text-input-portal]') &&
                !rootRef.current?.contains(target)
            ) {
                return;
            }

            if (event.key === 'Escape') {
                typeaheadBufferRef.current = '';
                setOpen(false);
                return;
            }

            if (event.key === 'Backspace') {
                if (typeaheadBufferRef.current.length === 0) {
                    return;
                }
                event.preventDefault();
                typeaheadBufferRef.current = typeaheadBufferRef.current.slice(0, -1);
                resetBufferSoon();
                scrollToPrefix(typeaheadBufferRef.current);
                return;
            }

            if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            if (!/^[a-z0-9 ]$/i.test(event.key)) {
                return;
            }

            event.preventDefault();
            typeaheadBufferRef.current += event.key;
            resetBufferSoon();
            scrollToPrefix(typeaheadBufferRef.current);
        };

        window.addEventListener('keydown', onKeyDown);
        return () => {
            window.removeEventListener('keydown', onKeyDown);
            if (typeaheadTimerRef.current) {
                clearTimeout(typeaheadTimerRef.current);
                typeaheadTimerRef.current = null;
            }
        };
    }, [open, typeaheadJump, normalizedOptions]);

    return (
        <div ref={rootRef} className={`${TEXT_INPUT_ROOT} ${className}`.trim()}>
            <label
                htmlFor={id}
                className={
                    hideLabel
                        ? 'sr-only'
                        : 'mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94'
                }
            >
                {label}
            </label>

            <div className="relative">
                <button
                    id={id}
                    ref={triggerRef}
                    type="button"
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-controls={open ? menuId : undefined}
                    aria-label={hideLabel ? `${label}: open category menu` : 'Open dropdown'}
                    onClick={() => setOpen((o) => !o)}
                    className="relative box-border flex h-[49px] w-full min-w-0 max-w-full appearance-none items-center justify-between gap-3 overflow-hidden rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] px-4 text-left font-montserrat text-[16px] font-medium tracking-tight text-[#364153] shadow-sm outline-none transition-[border-color,box-shadow,background-color] duration-200 hover:bg-[#F8F9F6] focus-visible:border-[#5A6B44] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 sm:px-5"
                >
                    <span className="min-w-0 truncate">{selectedLabel || 'Select…'}</span>
                    <div
                        className="flex h-5 w-5 shrink-0 items-center justify-center bg-[#FFFFFF] text-[#5A6B44]"
                        aria-hidden="true"
                    >
                        <IconChevronDown className="h-4 w-4 text-[#5A6B44]" />
                    </div>
                </button>
            </div>

            {open && menuRect
                ? createPortal(
                      <div
                          data-dropdown-text-input-portal
                          className="fixed z-[9999] max-w-[calc(100vw-16px)]"
                          style={{
                              left: `${menuRect.left}px`,
                              top: `${menuRect.top}px`,
                              width: `${Math.max(menuRect.width, 220)}px`,
                          }}
                      >
                          <div className="w-full rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] p-2 shadow-2xl">
                              <ul
                                  id={menuId}
                                  ref={listRef}
                                  role="listbox"
                                  aria-label={listLabel}
                                  className="m-0 max-h-[280px] list-none overflow-auto p-0"
                              >
                                  {normalizedOptions.map((opt) => {
                                      const selected = opt.value === value;
                                      return (
                                          <li
                                              key={opt.value}
                                              role="presentation"
                                              data-option-value={opt.value}
                                          >
                                              <button
                                                  type="button"
                                                  role="option"
                                                  aria-selected={selected}
                                                  onClick={() => {
                                                      onChange(opt.value);
                                                      setOpen(false);
                                                  }}
                                                  className={[
                                                      'flex w-full appearance-none items-center justify-between gap-3 rounded-[12px] border-0 px-3 py-2 text-left font-montserrat text-sm font-bold',
                                                      'bg-transparent text-[#262A22] shadow-none outline-none',
                                                      'transition-[background-color,color] duration-200 ease-in-out',
                                                      'hover:bg-[#F8F9F6] hover:text-[#5A6B44]',
                                                      'focus-visible:bg-[#F8F9F6] focus-visible:text-[#5A6B44] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-inset',
                                                  ].join(' ')}
                                              >
                                                  <span className="min-w-0 truncate">{opt.label}</span>
                                                  <span
                                                      className={selected ? 'text-[#5A6B44]' : 'opacity-0'}
                                                      aria-hidden
                                                  >
                                                      ✓
                                                  </span>
                                              </button>
                                          </li>
                                      );
                                  })}
                              </ul>
                          </div>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}
