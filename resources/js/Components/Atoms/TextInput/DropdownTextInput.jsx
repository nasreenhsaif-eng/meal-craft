import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { IconChevronDown } from '../Icons.jsx';

/**
 * Minimalist dropdown styled like TextInput (but without native select blue highlights).
 *
 * @param {{
 *   label: string;
 *   value: string;
 *   options: string[];
 *   onChange: (value: string) => void;
 *   className?: string;
 *   hideLabel?: boolean;
 *   listboxAriaLabel?: string — optional `aria-label` for the listbox when `hideLabel` is true (defaults to `label`).
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
}) {
    const id = useId();
    const rootRef = useRef(null);
    const triggerRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [menuRect, setMenuRect] = useState(null);

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const onDocMouseDown = (event) => {
            if (!rootRef.current) {
                return;
            }
            if (!rootRef.current.contains(event.target)) {
                setOpen(false);
            }
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
                    aria-label={hideLabel ? `${label}: open category menu` : 'Open dropdown'}
                    onClick={() => setOpen((o) => !o)}
                    className="relative flex h-[49px] w-full appearance-none items-center justify-between gap-3 overflow-hidden rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] px-[20px] text-left font-montserrat text-[16px] font-medium tracking-tight text-[#364153] shadow-sm outline-none transition-[border-color,box-shadow,background-color] duration-200 hover:bg-[#F8F9F6] focus-visible:border-[#5A6B44] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                >
                    <span className="min-w-0 truncate">{value || 'Select…'}</span>
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
                          className="fixed z-50"
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
                                  className="m-0 max-h-[220px] list-none overflow-auto p-0"
                              >
                                  {options.map((opt) => {
                                      const selected = opt === value;
                                      return (
                                          <li key={opt} role="presentation">
                                              <button
                                                  type="button"
                                                  role="option"
                                                  aria-selected={selected}
                                                  onClick={() => {
                                                      onChange(opt);
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
                                                  <span className="min-w-0 truncate">{opt}</span>
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

