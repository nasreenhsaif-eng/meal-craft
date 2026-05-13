import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { IconChevronDown } from '../SvgIcons.jsx';

/**
 * Multi-select styled like {@link DropdownTextInput}: pills inside the trigger + portal listbox.
 *
 * @param {{
 *   label: string;
 *   options: { value: string; label: string }[];
 *   selectedValues: string[];
 *   onChange: (next: string[]) => void;
 *   className?: string;
 *   hideLabel?: boolean;
 *   listboxAriaLabel?: string;
 *   placeholder?: string;
 * }} props
 */
export default function MultiPillDropdown({
    label,
    options,
    selectedValues,
    onChange,
    className = '',
    hideLabel = false,
    listboxAriaLabel,
    placeholder = 'Select…',
}) {
    const id = useId();
    const rootRef = useRef(null);
    const triggerRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [menuRect, setMenuRect] = useState(null);

    const labelByValue = useMemo(() => {
        const m = new Map();
        for (const o of options) {
            m.set(o.value, o.label);
        }
        return m;
    }, [options]);

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
            if (t.closest('[data-multi-pill-dropdown-portal]')) {
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
            const r = el.getBoundingClientRect();
            setMenuRect({
                left: r.left,
                top: r.bottom,
                width: r.width,
            });
        };

        updateRect();

        window.addEventListener('resize', updateRect);
        window.addEventListener('scroll', updateRect, true);

        return () => {
            window.removeEventListener('resize', updateRect);
            window.removeEventListener('scroll', updateRect, true);
        };
    }, [open]);

    const orderedSelection = useMemo(
        () => options.map((o) => o.value).filter((v) => selectedValues.includes(v)),
        [options, selectedValues],
    );

    /**
     * @param {string} value
     */
    function toggleValue(value) {
        const set = new Set(selectedValues);
        if (set.has(value)) {
            set.delete(value);
        } else {
            set.add(value);
        }
        onChange(options.map((o) => o.value).filter((v) => set.has(v)));
    }

    /**
     * @param {string} value
     * @param {import('react').MouseEvent} e
     */
    function removePill(value, e) {
        e.stopPropagation();
        e.preventDefault();
        onChange(selectedValues.filter((v) => v !== value));
    }

    return (
        <div ref={rootRef} className={`block w-full max-w-[492px] text-left ${className}`.trim()}>
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
                    aria-multiselectable="true"
                    aria-label={hideLabel ? `${label}: open multi-select` : 'Open multi-select'}
                    onClick={() => setOpen((o) => !o)}
                    className="relative flex min-h-[49px] w-full appearance-none items-center justify-between gap-3 rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] px-[14px] py-2 text-left font-montserrat text-[16px] font-medium tracking-tight text-[#364153] shadow-sm outline-none transition-[border-color,box-shadow,background-color] duration-200 hover:bg-[#F8F9F6] focus-visible:border-[#5A6B44] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                >
                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        {orderedSelection.length === 0 ? (
                            <span className="truncate text-[#9CA3AF]">{placeholder}</span>
                        ) : (
                            orderedSelection.map((v) => (
                                <span
                                    key={v}
                                    className="inline-flex max-w-full items-center gap-1 rounded-full border border-[#D1DCC4] bg-[#F0F4E8] px-2.5 py-1 text-xs font-bold text-[#374151]"
                                >
                                    <span className="truncate">{labelByValue.get(v) ?? v}</span>
                                    <button
                                        type="button"
                                        className="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[#5A6B44] hover:bg-[#E3EAD8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]"
                                        aria-label={`Remove ${labelByValue.get(v) ?? v}`}
                                        onClick={(e) => removePill(v, e)}
                                    >
                                        ×
                                    </button>
                                </span>
                            ))
                        )}
                    </div>
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
                          data-multi-pill-dropdown-portal
                          className="fixed z-[9999]"
                          style={{
                              left: `${menuRect.left}px`,
                              top: `${menuRect.top + 8}px`,
                              width: `${menuRect.width}px`,
                          }}
                      >
                          <div className="w-full rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] p-2 shadow-2xl">
                              <ul
                                  id={menuId}
                                  role="listbox"
                                  aria-label={listLabel}
                                  aria-multiselectable="true"
                                  className="m-0 max-h-[220px] list-none overflow-auto p-0"
                              >
                                  {options.map((opt) => {
                                      const selected = selectedValues.includes(opt.value);
                                      return (
                                          <li key={opt.value} role="presentation">
                                              <button
                                                  type="button"
                                                  role="option"
                                                  aria-selected={selected}
                                                  onClick={() => {
                                                      toggleValue(opt.value);
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
                                                  <span className={selected ? 'text-[#5A6B44]' : 'opacity-0'} aria-hidden>
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
