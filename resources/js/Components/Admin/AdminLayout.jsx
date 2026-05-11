import { useCallback, useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import TextInput from '../Atoms/TextInput/TextInput.jsx';
import AdminSidebar from './AdminSidebar.jsx';

const SIDEBAR_W = 280;
const MQ = '(min-width: 768px)';

function useMatchMedia(query) {
    const [matches, setMatches] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }
        return window.matchMedia(query).matches;
    });

    useEffect(() => {
        const mq = window.matchMedia(query);
        const onChange = () => setMatches(mq.matches);
        onChange();
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, [query]);

    return matches;
}

function IconMenu({ className }) {
    return (
        <svg className={className} width={24} height={24} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

function IconClose({ className }) {
    return (
        <svg className={className} width={24} height={24} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

function IconSearchPrefix() {
    return (
        <svg width={18} height={18} viewBox="0 0 24 24" fill="none" aria-hidden>
            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="2" />
            <path d="M20 20l-3.5-3.5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

/**
 * @param {object} props
 * @param {string} props.pageTitle
 * @param {import('react').ReactNode} [props.children]
 * @param {string} [props.activePath]
 * @param {(path: string) => void} [props.onNavigate]
 * @param {import('react').ReactNode} [props.userAvatar] — Shown inside the account control (e.g. image or initials).
 * @param {() => void} [props.onAccountSettingsClick]
 * @param {string} [props.searchLabel] — Passed to `TextInput` (default: “Search”).
 * @param {string} [props.searchPlaceholder]
 * @param {string} [props.searchValue] — Controlled search; omit for internal state (Storybook default).
 * @param {(event: import('react').ChangeEvent<HTMLInputElement>) => void} [props.onSearchChange]
 * @param {boolean} [props.showSearch] — Render global search row (default: true).
 * @param {string} [props.contentWrapperClassName] — Class for the inner wrapper around `children` (default: centered max-w-6xl).
 */
export function AdminLayout({
    pageTitle,
    children,
    activePath = '',
    onNavigate,
    userAvatar,
    onAccountSettingsClick,
    showSearch = true,
    searchLabel = 'Search',
    searchPlaceholder = 'Search meals, ingredients, profiles…',
    searchValue: searchValueProp,
    onSearchChange: onSearchChangeProp,
    contentWrapperClassName = 'mx-auto w-full max-w-6xl',
}) {
    const isDesktop = useMatchMedia(MQ);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [searchInternal, setSearchInternal] = useState('');

    const searchControlled = searchValueProp !== undefined;
    const searchValue = searchControlled ? searchValueProp : searchInternal;
    const setSearch = useCallback(
        (e) => {
            if (searchControlled) {
                onSearchChangeProp?.(e);
            } else {
                setSearchInternal(e.target.value);
            }
        },
        [searchControlled, onSearchChangeProp],
    );

    const closeMobileNav = useCallback(() => setMobileNavOpen(false), []);

    const handleNavigate = useCallback(
        (path) => {
            onNavigate?.(path);
            if (!isDesktop) {
                closeMobileNav();
            }
        },
        [isDesktop, onNavigate, closeMobileNav],
    );

    useEffect(() => {
        if (isDesktop) {
            setMobileNavOpen(false);
        }
    }, [isDesktop]);

    useEffect(() => {
        if (!mobileNavOpen || typeof document === 'undefined') {
            return undefined;
        }
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = prev;
        };
    }, [mobileNavOpen]);

    const sidebarX = isDesktop ? 0 : mobileNavOpen ? 0 : -SIDEBAR_W;

    const defaultAvatarVisual = (
        <span
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#556C37] text-xs font-bold text-white"
            aria-hidden
        >
            AD
        </span>
    );

    return (
        <div className="min-h-screen font-sans">
            <AnimatePresence>
                {!isDesktop && mobileNavOpen ? (
                    <motion.button
                        type="button"
                        key="admin-nav-backdrop"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="fixed inset-0 z-30 bg-[#1C2416]/50 md:hidden"
                        aria-label="Close navigation menu"
                        onClick={closeMobileNav}
                    />
                ) : null}
            </AnimatePresence>

            <motion.aside
                id="admin-sidebar-panel"
                initial={false}
                animate={{ x: sidebarX }}
                transition={{ type: 'spring', stiffness: 380, damping: 34 }}
                className="fixed bottom-0 left-0 top-0 z-40 w-[280px] border-r border-gray-200 bg-white"
                aria-hidden={!isDesktop && !mobileNavOpen}
                inert={!isDesktop && !mobileNavOpen}
            >
                <AdminSidebar activePath={activePath} onNavigate={handleNavigate} />
            </motion.aside>

            <div className="flex min-h-screen flex-col md:pl-[280px]">
                <header className="sticky top-0 z-20 shrink-0 border-b border-gray-200 bg-white">
                    <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-3 px-4 py-3 md:px-6 md:py-3">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                <button
                                    type="button"
                                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-[#262A22] md:hidden hover:bg-[#556C37]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
                                    aria-expanded={mobileNavOpen}
                                    aria-controls="admin-sidebar-panel"
                                    onClick={() => setMobileNavOpen((o) => !o)}
                                >
                                    {mobileNavOpen ? (
                                        <IconClose className="block" />
                                    ) : (
                                        <IconMenu className="block" />
                                    )}
                                    <span className="sr-only">{mobileNavOpen ? 'Close menu' : 'Open menu'}</span>
                                </button>
                                <h1 className="m-0 min-w-0 flex-1 truncate font-sans text-lg font-bold tracking-tight text-[#262A22]">
                                    {pageTitle}
                                </h1>
                            </div>
                            <button
                                type="button"
                                onClick={onAccountSettingsClick}
                                className="flex shrink-0 items-center justify-center rounded-full border-2 border-transparent p-0.5 transition-colors hover:border-[#556C37]/35 hover:bg-[#556C37]/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
                            >
                                <span className="sr-only">Account Settings</span>
                                {userAvatar ?? defaultAvatarVisual}
                            </button>
                        </div>
                        {showSearch ? (
                            <TextInput
                                label={searchLabel}
                                placeholder={searchPlaceholder}
                                value={searchValue}
                                onChange={setSearch}
                                prefixIcon={<IconSearchPrefix />}
                                className="w-full max-w-full min-w-0 md:max-w-xl lg:max-w-2xl"
                            />
                        ) : null}
                    </div>
                </header>

                <main
                    id="admin-main"
                    className="flex-1 bg-[#F8F9F6] px-4 py-6 md:px-6"
                    style={{ fontFamily: 'Montserrat, ui-sans-serif, system-ui, sans-serif' }}
                >
                    <div className={contentWrapperClassName}>{children}</div>
                </main>
            </div>
        </div>
    );
}

export default AdminLayout;
