import { createRoot } from 'react-dom/client';
import { StrictMode, useCallback, useEffect, useState } from 'react';
import { AnimatedIntro } from './Components/AnimatedIntro.jsx';

const TAB_SID_KEY = 'mealcraft_tab_sid';

function introDoneKey(sid) {
    return `mealcraft_intro_done:${sid}`;
}

function getTabSessionId() {
    let sid = sessionStorage.getItem(TAB_SID_KEY);
    if (!sid) {
        sid = crypto.randomUUID();
        sessionStorage.setItem(TAB_SID_KEY, sid);
    }
    return sid;
}

function hasCompletedIntro() {
    return localStorage.getItem(introDoneKey(getTabSessionId())) === '1';
}

function markIntroComplete() {
    try {
        localStorage.setItem(introDoneKey(getTabSessionId()), '1');
    } catch (_) {
        /* ignore */
    }
}

function revealAdminShell() {
    document.documentElement.classList.remove('mc-intro-pending');
}

async function waitForIntroFonts() {
    if (!document.fonts?.load) {
        return;
    }
    try {
        await document.fonts.load('700 81px "Baloo 2"');
        await document.fonts.load('600 15px "Baloo 2"');
        await document.fonts.load('600 14px "Baloo 2"');
        await document.fonts.ready;
    } catch (_) {
        /* continue; avoid blocking intro indefinitely */
    }
}

const DARK_BG = '#1C2416';

function AdminIntroRoot() {
    const [phase, setPhase] = useState(() => (hasCompletedIntro() ? 'done' : 'fonts'));

    const handleComplete = useCallback(() => {
        markIntroComplete();
        revealAdminShell();
        setPhase('done');
    }, []);

    useEffect(() => {
        if (phase === 'done') {
            revealAdminShell();
            return;
        }

        if (phase !== 'fonts') {
            return;
        }

        let cancelled = false;

        (async () => {
            await waitForIntroFonts();
            if (cancelled) {
                return;
            }
            setPhase('intro');
        })();

        return () => {
            cancelled = true;
        };
    }, [phase]);

    if (phase === 'done') {
        return null;
    }

    if (phase === 'fonts') {
        return (
            <div
                className="fixed inset-0 z-50 select-none"
                style={{ background: DARK_BG, cursor: 'wait' }}
                aria-hidden
            />
        );
    }

    return <AnimatedIntro onComplete={handleComplete} />;
}

const mountEl = document.getElementById('mc-intro-root');
if (mountEl) {
    createRoot(mountEl).render(
        <StrictMode>
            <AdminIntroRoot />
        </StrictMode>,
    );
}
