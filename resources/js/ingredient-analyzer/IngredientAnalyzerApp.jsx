import { AnimatePresence, motion } from "framer-motion";
import { useCallback, useEffect, useState } from "react";

const THINKING_MESSAGES = [
    "Your request is queued—this avoids gateway timeouts (can take a few minutes)…",
    "Gemini is analyzing health benefits and standardizing your portion…",
    "Edamam is parsing natural-language quantities…",
    "Fetching USDA Foundation / SR Legacy nutritional data…",
];

const POLL_INTERVAL_MS = 1500;

const ANALYSIS_MAX_WAIT_MS = 15 * 60 * 1000;

function sleep(ms) {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

/**
 * @param {unknown} result
 * @returns {Record<string, unknown>}
 */
function normalizeAnalysisResult(result) {
    if (result == null) {
        throw new Error("Analysis returned no result.");
    }
    if (typeof result === "string") {
        const trimmed = result.trim();
        if (trimmed === "") {
            throw new Error("Analysis returned an empty result.");
        }
        try {
            const parsed = JSON.parse(trimmed);
            if (typeof parsed === "object" && parsed !== null && !Array.isArray(parsed)) {
                return parsed;
            }
        } catch {
            throw new Error("Analysis result was not valid JSON.");
        }
        throw new Error("Analysis result was not a JSON object.");
    }
    if (typeof result === "object" && !Array.isArray(result)) {
        return /** @type {Record<string, unknown>} */ (result);
    }
    throw new Error("Unexpected analysis result shape.");
}

/**
 * Opens Advanced → local USDA index and sets the library table search (Livewire).
 *
 * @param {string} standardizedName
 */
function scrollToEnrichIngredient(standardizedName) {
    const q = typeof standardizedName === "string" ? standardizedName.trim() : "";
    if (q === "") {
        return;
    }
    const details = document.getElementById("ingredients-advanced");
    if (details instanceof HTMLDetailsElement) {
        details.open = true;
        details.scrollIntoView({ behavior: "smooth", block: "start" });
    }
    const lw = window.Livewire;
    if (lw && typeof lw.dispatch === "function") {
        lw.dispatch("focus-ingredient-library-search", { q });
    }
}

async function pollUntilAnalysisComplete(statusUrl) {
    const started = Date.now();
    let firstPoll = true;

    while (Date.now() - started < ANALYSIS_MAX_WAIT_MS) {
        if (!firstPoll) {
            await sleep(POLL_INTERVAL_MS);
        }
        firstPoll = false;

        const res = await fetch(statusUrl, {
            method: "GET",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
            redirect: "manual",
        });

        const json = await parseJsonResponseBody(res);

        if (res.status === 403 || res.status === 404) {
            throw new Error(
                typeof json.message === "string"
                    ? json.message
                    : "Could not load analysis status. Refresh and try again."
            );
        }

        const jobStatus = json.job_status;

        if (jobStatus === "queued" || jobStatus === "processing") {
            continue;
        }

        if (jobStatus === "failed") {
            throw new Error(typeof json.error === "string" ? json.error : "Analysis failed.");
        }

        if (jobStatus === "complete" && json.result != null) {
            const result = normalizeAnalysisResult(json.result);
            if (result.success === false) {
                const msg =
                    typeof result.error === "string" ? result.error : "Analysis failed.";
                const err = new Error(msg);
                throw err;
            }
            return result;
        }

        throw new Error("Unexpected response while waiting for analysis.");
    }

    throw new Error(
        "Analysis is taking too long (15+ minutes). Ensure a queue worker is running: php artisan queue:work — then try again."
    );
}

function formatNum(n, digits = 1) {
    if (n === null || n === undefined || Number.isNaN(n)) {
        return "—";
    }
    return Number(n).toFixed(digits);
}

/**
 * Prefer the layout meta tag so the token stays current after Livewire updates; fall back to the root data attribute from first paint.
 *
 * @param {string} fallback
 * @returns {string}
 */
function resolveCsrfToken(fallback) {
    const fromMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
    return fromMeta && fromMeta.trim() !== "" ? fromMeta.trim() : fallback;
}

/**
 * Laravel may return HTML (login, 419 CSRF, error page) while this UI expects JSON.
 *
 * @param {Response} res
 * @returns {Promise<Record<string, unknown>>}
 */
async function parseJsonResponseBody(res) {
    if (res.status === 419) {
        throw new Error(
            "This page has been open a long time and the security token expired. Refresh the page, then try again."
        );
    }

    if (res.status === 401) {
        throw new Error("You are not signed in. Open /login in this same browser tab, sign in, then return to Ingredients.");
    }

    if (res.status === 301 || res.status === 302 || res.status === 303 || res.status === 307 || res.status === 308) {
        throw new Error(
            "Your session may have expired (redirect from server). Do a full refresh (Cmd+Shift+R), open /login if needed, then try again."
        );
    }

    if (res.status === 504 || res.status === 502) {
        throw new Error(
            "Gateway timeout (HTTP 504/502): Meal Craft Analysis calls Gemini, Edamam, and USDA and can take longer than your host allows (many proxies stop around 60 seconds). Try Analyze again; if it keeps happening, raise the timeout in front of PHP (e.g. nginx proxy_read_timeout / Laravel Cloud or load balancer limits). This is usually not a login problem."
        );
    }

    if (res.status === 503) {
        throw new Error(
            "Service unavailable (HTTP 503). The app or an upstream API may be overloaded. Wait a minute and try again."
        );
    }

    const contentType = (res.headers.get("content-type") || "").toLowerCase();
    if (contentType.includes("text/html")) {
        throw new Error(
            `The server returned HTML (HTTP ${res.status}) instead of JSON—often not logged in, expired session, or an old cached script. Fix: hard-refresh this page, sign in at /login on the same site, run "npm run build" if you deploy without "npm run dev", then try Analyze again.`
        );
    }

    const raw = await res.text();
    const trimmed = raw.trim();

    if (trimmed.startsWith("<") || trimmed.startsWith("<!")) {
        throw new Error(
            `The response looks like a web page (HTTP ${res.status}), not JSON. Hard-refresh (Cmd+Shift+R), sign in at /login, and ensure the latest JS is loaded (npm run dev locally or npm run build for production).`
        );
    }

    if (trimmed === "") {
        throw new Error("The server returned an empty response.");
    }

    try {
        return JSON.parse(raw);
    } catch {
        throw new Error("The server response was not valid JSON.");
    }
}

export function IngredientAnalyzerApp({ csrfToken, endpoint, statusBase = "", saveEndpoint = "" }) {
    const [input, setInput] = useState("");
    const [loading, setLoading] = useState(false);
    const [thinkingIdx, setThinkingIdx] = useState(0);
    const [error, setError] = useState(null);
    const [data, setData] = useState(null);
    const [saving, setSaving] = useState(false);
    const [saveMessage, setSaveMessage] = useState(null);
    const [saveError, setSaveError] = useState(null);

    useEffect(() => {
        if (!loading || data) {
            return undefined;
        }
        const id = setInterval(() => {
            setThinkingIdx((i) => (i + 1) % THINKING_MESSAGES.length);
        }, 2000);
        return () => clearInterval(id);
    }, [loading, data]);

    useEffect(() => {
        if (data?.success === false || !data?.usda?.for_portion) {
            return;
        }
        const haystack = `${String(data.standardized_name ?? "")} ${String(data.original_input ?? "")} ${String(data.usda?.description ?? "")}`.toLowerCase();
        const isMeat = /\b(chicken|beef|pork|lamb|fish|turkey|veal)\b/i.test(haystack);
        const isLeafy =
            /\b(spinach|kale|lettuce|arugula|chard|collard|collards|cabbage|broccoli|turnip greens|mustard greens)\b/i.test(
                haystack,
            );
        if (!isMeat && !isLeafy) {
            return;
        }
        const p = data.usda.for_portion;
        const b12 = Number(p.vitamin_b12_mcg);
        const folate = Number(p.folate_mcg);
        const b12Bad = !Number.isFinite(b12) || b12 <= 0;
        const folateBad = !Number.isFinite(folate) || folate <= 0;
        const gap = (isMeat && (b12Bad || folateBad)) || (isLeafy && folateBad);
        if (gap) {
            console.warn("Micronutrient Gap Detected - Check Fallback Logic");
        }
    }, [data]);

    const runAnalysis = useCallback(async () => {
        const trimmed = input.trim();
        if (trimmed.length < 2) {
            setError("Please enter at least 2 characters.");
            setData(null);
            return;
        }
        setLoading(true);
        setError(null);
        setSaveMessage(null);
        setSaveError(null);
        setThinkingIdx(0);

        try {
            const token = resolveCsrfToken(csrfToken);
            const res = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": token,
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                redirect: "manual",
                body: JSON.stringify({ input: trimmed }),
            });

            const json = await parseJsonResponseBody(res);

            /** @type {Record<string, unknown>} */
            let payload;

            if (res.status === 202 && json.accepted === true && typeof json.job_id === "string") {
                const statusUrl =
                    (typeof json.status_url === "string" && json.status_url.trim() !== ""
                        ? json.status_url
                        : `${String(statusBase || endpoint).replace(/\/$/, "")}/${json.job_id}`);
                try {
                    payload = await pollUntilAnalysisComplete(statusUrl);
                } catch (pollErr) {
                    setError(pollErr instanceof Error ? pollErr.message : "Analysis failed.");
                    return;
                }
            } else {
                if (!res.ok) {
                    setError(
                        typeof json.error === "string"
                            ? json.error
                            : typeof json.message === "string"
                              ? json.message
                              : "Request failed."
                    );
                    return;
                }

                if (json.success === false) {
                    setError(typeof json.error === "string" ? json.error : "Analysis failed.");
                    return;
                }

                payload = json;
            }

            if (payload.success === false) {
                setError(typeof payload.error === "string" ? payload.error : "Analysis failed.");
                return;
            }

            setData(payload);
        } catch (e) {
            setError(e instanceof Error ? e.message : "Network error.");
        } finally {
            setLoading(false);
        }
    }, [input, endpoint, statusBase, csrfToken]);

    const saveToLibrary = useCallback(async () => {
        if (!saveEndpoint || !data?.success || !data?.usda?.fdc_id) {
            return;
        }
        const fdcMap = data.usda.fdc_key_nutrients_per_100g;
        const per100 = data.usda.per_100g;
        if (!fdcMap || !per100) {
            setSaveError("USDA nutrient data is incomplete — run analysis again with a matched food.");
            return;
        }
        setSaving(true);
        setSaveError(null);
        setSaveMessage(null);
        try {
            const token = resolveCsrfToken(csrfToken);
            const res = await fetch(saveEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": token,
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                redirect: "manual",
                body: JSON.stringify({
                    standardized_name: data.standardized_name,
                    fdc_id: data.usda.fdc_id,
                    functional_tip: data.functional_tip ?? "",
                    portion_grams:
                        typeof data.quantity_g === "number" && !Number.isNaN(data.quantity_g)
                            ? data.quantity_g
                            : null,
                    sickle_cell_support_message:
                        data.usda?.sickle_cell_support?.message?.trim() || null,
                    usda_description: data.usda?.description?.trim() || null,
                    usda_data_type: data.usda?.data_type?.trim() || null,
                    usda_food_category: data.usda?.food_category?.trim() || null,
                    per_100g: per100,
                    fdc_key_nutrients: fdcMap,
                }),
            });
            const json = await parseJsonResponseBody(res);
            if (!res.ok) {
                const fromErrors =
                    json.errors && typeof json.errors === "object"
                        ? Object.values(json.errors)
                              .flat()
                              .filter((x) => typeof x === "string")
                              .join(" ")
                        : "";
                setSaveError(
                    (typeof json.message === "string" && json.message) ||
                        (typeof json.error === "string" && json.error) ||
                        fromErrors ||
                        "Could not save to library.",
                );
                return;
            }
            setSaveMessage(json.message ?? "Saved to Meal Craft Library.");
            window.setTimeout(() => window.location.reload(), 800);
        } catch (e) {
            setSaveError(e instanceof Error ? e.message : "Save failed.");
        } finally {
            setSaving(false);
        }
    }, [saveEndpoint, data, csrfToken]);

    const portion = data?.usda?.for_portion;

    return (
        <div className="mx-auto max-w-3xl space-y-8">
            <header>
                <h1 className="text-2xl font-semibold tracking-tight text-emerald-950 dark:text-emerald-50">
                    Meal Craft Analysis
                </h1>
                <p className="mt-2 text-sm text-emerald-800/80 dark:text-emerald-200/70">
                    Gemini + Edamam parser + USDA (Foundation &amp; SR Legacy) — describe any ingredient in plain language.
                </p>
            </header>

            <div className="rounded-2xl border border-emerald-200/60 bg-white/90 p-6 shadow-sm backdrop-blur-sm dark:border-emerald-900/50 dark:bg-emerald-950/40">
                <label htmlFor="mc-ingredient-input" className="sr-only">
                    Ingredient description
                </label>
                <textarea
                    id="mc-ingredient-input"
                    rows={3}
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) {
                            e.preventDefault();
                            runAnalysis();
                        }
                    }}
                    placeholder='e.g. A handful of soaked walnuts'
                    className="w-full resize-y rounded-xl border border-emerald-200/80 bg-emerald-50/30 px-4 py-3 text-emerald-950 placeholder:text-emerald-600/50 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/40 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-50 dark:placeholder:text-emerald-400/40"
                />
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        onClick={runAnalysis}
                        disabled={loading}
                        className="inline-flex items-center justify-center rounded-xl bg-[var(--color-brand-green)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {loading ? "Analyzing…" : "Analyze"}
                    </button>
                    <span className="text-xs text-emerald-700/70 dark:text-emerald-300/60">
                        Live search as you type (1s debounce) — or press Analyze / ⌘↵
                    </span>
                </div>

                <DebouncedSearchTrigger input={input} onSearch={runAnalysis} />
            </div>

            <AnimatePresence mode="wait">
                {loading && !data && (
                    <motion.div
                        key="thinking"
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -6 }}
                        className="flex items-start gap-3 rounded-2xl border border-amber-200/70 bg-amber-50/80 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/30"
                    >
                        <span
                            className="mt-0.5 inline-block size-4 shrink-0 animate-spin rounded-full border-2 border-amber-300 border-t-amber-700 dark:border-amber-800 dark:border-t-amber-400"
                            aria-hidden
                        />
                        <div>
                            <p className="text-sm font-medium text-amber-950 dark:text-amber-100">
                                Thinking…
                            </p>
                            <p className="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                                {THINKING_MESSAGES[thinkingIdx]}
                            </p>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            {loading && data && (
                <div className="flex items-center gap-2 rounded-xl border border-emerald-200/70 bg-emerald-50/90 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100">
                    <span
                        className="inline-block size-3.5 shrink-0 animate-spin rounded-full border-2 border-emerald-400 border-t-emerald-800 dark:border-emerald-700 dark:border-t-emerald-200"
                        aria-hidden
                    />
                    Updating analysis…
                </div>
            )}

            {error && (
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-100"
                    role="alert"
                >
                    {error}
                </motion.div>
            )}

            <AnimatePresence>
                {data && (
                    <motion.article
                        key="card"
                        initial={{ opacity: 0, y: 12 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.35, ease: "easeOut" }}
                        className="overflow-hidden rounded-2xl border border-emerald-200/70 bg-gradient-to-b from-white to-emerald-50/50 shadow-md dark:border-emerald-900/50 dark:from-emerald-950 dark:to-emerald-950/80"
                    >
                        <div className="border-b border-emerald-200/60 bg-emerald-600/10 px-6 py-4 dark:border-emerald-800 dark:bg-emerald-900/30">
                            <h2 className="text-lg font-semibold text-emerald-950 dark:text-emerald-50">
                                Meal Craft Analysis
                            </h2>
                            <p className="mt-1 text-xs text-emerald-800/70 dark:text-emerald-200/60">
                                Original:{" "}
                                <span className="font-medium text-emerald-900 dark:text-emerald-100">
                                    {data.original_input}
                                </span>
                            </p>
                            {data.local_library?.hit === true && (
                                <p className="mt-2 inline-flex flex-wrap items-center gap-2 rounded-lg border border-violet-300/60 bg-violet-50/90 px-3 py-1.5 text-xs font-medium text-violet-950 dark:border-violet-800/60 dark:bg-violet-950/40 dark:text-violet-100">
                                    <span className="font-semibold uppercase tracking-wide">
                                        Instant · verified library
                                    </span>
                                    <span className="text-violet-900/90 dark:text-violet-200/90">
                                        No Gemini, Edamam, or USDA quota used — data from your saved ingredients
                                        {data.local_library?.source === "json_export"
                                            ? " (JSON export fallback)."
                                            : "."}
                                    </span>
                                </p>
                            )}
                            {((saveEndpoint &&
                                data.usda?.fdc_id &&
                                data.usda?.fdc_key_nutrients_per_100g) ||
                                (data.success !== false &&
                                    typeof data.standardized_name === "string" &&
                                    data.standardized_name.trim() !== "")) && (
                                <div className="mt-4 flex flex-wrap items-center gap-3">
                                    {saveEndpoint &&
                                        data.usda?.fdc_id &&
                                        data.usda?.fdc_key_nutrients_per_100g && (
                                            <button
                                                type="button"
                                                onClick={saveToLibrary}
                                                disabled={saving || loading}
                                                className="inline-flex items-center justify-center rounded-xl bg-violet-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-violet-600 dark:hover:bg-violet-500"
                                            >
                                                {saving ? "Saving…" : "Save to Meal Craft Library"}
                                            </button>
                                        )}
                                    {data.success !== false &&
                                        typeof data.standardized_name === "string" &&
                                        data.standardized_name.trim() !== "" && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    scrollToEnrichIngredient(String(data.standardized_name))
                                                }
                                                disabled={loading}
                                                className="inline-flex items-center justify-center rounded-xl border border-emerald-600/40 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-950 shadow-sm transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-500/40 dark:bg-emerald-900/40 dark:text-emerald-50 dark:hover:bg-emerald-900/70"
                                            >
                                                Enrich ingredient
                                            </button>
                                        )}
                                    {saveMessage && (
                                        <span className="text-sm font-medium text-emerald-800 dark:text-emerald-200">
                                            {saveMessage}
                                        </span>
                                    )}
                                    {saveError && (
                                        <span className="text-sm font-medium text-red-700 dark:text-red-300">
                                            {saveError}
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="space-y-6 px-6 py-5">
                            {data.functional_tip && (
                                <motion.div
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    transition={{ delay: 0.05 }}
                                    className="rounded-xl border border-emerald-300/40 bg-emerald-100/40 px-4 py-3 dark:border-emerald-700 dark:bg-emerald-900/40"
                                >
                                    <p className="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">
                                        Functional tip
                                    </p>
                                    <p className="mt-2 text-base font-medium leading-relaxed text-emerald-950 dark:text-emerald-50">
                                        {data.functional_tip}
                                    </p>
                                </motion.div>
                            )}

                            {data.usda?.sickle_cell_support?.show && data.usda?.sickle_cell_support?.message && (
                                <motion.div
                                    initial={{ opacity: 0, y: 6 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: 0.08 }}
                                    className="rounded-xl border border-rose-200/70 bg-rose-50/90 px-4 py-3 dark:border-rose-900/50 dark:bg-rose-950/40"
                                >
                                    <p className="text-xs font-semibold uppercase tracking-wide text-rose-900 dark:text-rose-200">
                                        {data.usda.sickle_cell_support.badge ?? "Sickle Cell Support"}
                                    </p>
                                    <p className="mt-2 text-sm leading-relaxed text-rose-950 dark:text-rose-50">
                                        {data.usda.sickle_cell_support.message}
                                    </p>
                                </motion.div>
                            )}

                            {data.usda?.processing_nudge?.show && data.usda?.processing_nudge?.message && (
                                <motion.div
                                    initial={{ opacity: 0, y: 6 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: 0.1 }}
                                    className="rounded-xl border border-amber-300/80 bg-amber-50/95 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-950/50"
                                >
                                    <p className="text-xs font-semibold uppercase tracking-wide text-amber-950 dark:text-amber-200">
                                        {data.usda.processing_nudge.badge ?? "High Processing Detected"}
                                    </p>
                                    <p className="mt-2 text-sm leading-relaxed text-amber-950 dark:text-amber-50">
                                        {data.usda.processing_nudge.message}
                                    </p>
                                </motion.div>
                            )}

                            <div className="grid gap-2 text-sm text-emerald-900 dark:text-emerald-100">
                                <div className="flex flex-wrap justify-between gap-2 border-b border-emerald-200/50 pb-2 dark:border-emerald-800/50">
                                    <span className="text-emerald-700/90 dark:text-emerald-300/80">
                                        Standardized name
                                    </span>
                                    <span className="font-semibold">{data.standardized_name}</span>
                                </div>
                                <div className="flex flex-wrap justify-between gap-2 border-b border-emerald-200/50 pb-2 dark:border-emerald-800/50">
                                    <span className="text-emerald-700/90 dark:text-emerald-300/80">
                                        Portion
                                    </span>
                                    <span className="font-semibold">
                                        {formatNum(data.quantity_g, 1)} g
                                        <span className="ml-2 text-xs font-normal text-emerald-600 dark:text-emerald-400">
                                            (source: {data.quantity_source})
                                        </span>
                                    </span>
                                </div>
                            </div>

                            {data.soaking?.mentioned && data.soaking?.note && (
                                <motion.div
                                    initial={{ opacity: 0, x: -6 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    className="rounded-xl border border-teal-200/60 bg-teal-50/80 px-4 py-3 dark:border-teal-900 dark:bg-teal-950/40"
                                >
                                    <p className="text-xs font-semibold uppercase tracking-wide text-teal-800 dark:text-teal-300">
                                        Why soaking matters
                                    </p>
                                    <p className="mt-2 text-sm leading-relaxed text-teal-950 dark:text-teal-100">
                                        {data.soaking.note}
                                    </p>
                                </motion.div>
                            )}

                            {data.edamam?.quantity_g != null && (
                                <p className="text-xs text-emerald-700/80 dark:text-emerald-300/60">
                                    Edamam parser estimate: {formatNum(data.edamam.quantity_g, 1)} g
                                    {data.edamam.measure_label
                                        ? ` (${data.edamam.measure_label})`
                                        : ""}
                                </p>
                            )}

                            {portion && (
                                <div>
                                    <div className="mb-3 flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">
                                            USDA — for your portion
                                        </h3>
                                        {data.usda.whole_food_match_badge && (
                                            <span
                                                className="inline-flex items-center rounded-full border border-emerald-400/60 bg-emerald-100/90 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-900 dark:border-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-200"
                                                title="Matched via whole-food search (including fuzzy protein fallback)"
                                            >
                                                Whole Food Match
                                            </span>
                                        )}
                                        {data.usda.sickle_cell_approved?.show && (
                                            <span
                                                className="inline-flex items-center rounded-full border border-emerald-500/70 bg-emerald-200/80 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-950 dark:border-emerald-500/50 dark:bg-emerald-900/60 dark:text-emerald-100"
                                                title={
                                                    typeof data.usda.sickle_cell_approved?.note === "string"
                                                        ? data.usda.sickle_cell_approved.note
                                                        : "Per 100 g: folate > 40 µg or B12 > 1 µg"
                                                }
                                            >
                                                {data.usda.sickle_cell_approved.badge ?? "Sickle Cell Approved"}
                                            </span>
                                        )}
                                        {data.usda.verified_functional_sr_legacy && (
                                            <span
                                                className="inline-flex items-center rounded-full border border-sky-400/60 bg-sky-100/90 px-2.5 py-0.5 text-[10px] font-semibold text-sky-950 dark:border-sky-700 dark:bg-sky-950/50 dark:text-sky-100"
                                                title="Foundation micronutrient data was incomplete (B12 1178 or folate 1177); SR Legacy was used for analysis"
                                            >
                                                Verified Functional Data (SR Legacy)
                                            </span>
                                        )}
                                    </div>
                                    <p className="mb-3 text-xs text-emerald-700/80 dark:text-emerald-400/70">
                                        {data.usda.description} · FDC {data.usda.fdc_id} ·{" "}
                                        {data.usda.data_type}
                                        {" · "}
                                        {data.usda.food_category}
                                    </p>

                                    {data.usda.key_nutrients?.show &&
                                        Array.isArray(data.usda.key_nutrients.items) &&
                                        data.usda.key_nutrients.items.length > 0 && (
                                            <div className="mb-4 rounded-xl border border-violet-300/60 bg-violet-50/90 px-4 py-3 dark:border-violet-800/60 dark:bg-violet-950/50">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-violet-900 dark:text-violet-200">
                                                    Key nutrients
                                                </p>
                                                {data.usda.key_nutrients.note && (
                                                    <p className="mt-1 text-xs leading-relaxed text-violet-950/85 dark:text-violet-100/80">
                                                        {data.usda.key_nutrients.note}
                                                    </p>
                                                )}
                                                <ul className="mt-3 space-y-2">
                                                    {data.usda.key_nutrients.items.map((item) => (
                                                        <li
                                                            key={item.fdc_nutrient_id}
                                                            className="flex justify-between gap-4 text-sm text-violet-950 dark:text-violet-50"
                                                        >
                                                            <span>
                                                                {item.label}
                                                                <span className="ml-1 text-xs font-normal text-violet-800/80 dark:text-violet-300/70">
                                                                    · FDC {item.fdc_nutrient_id}
                                                                </span>
                                                            </span>
                                                            <span className="font-medium tabular-nums">
                                                                {formatNum(
                                                                    item.amount,
                                                                    typeof item.digits === "number"
                                                                        ? item.digits
                                                                        : 2
                                                                )}
                                                                {item.unit ?? ""}
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )}

                                    <ul className="divide-y divide-emerald-200/60 rounded-xl border border-emerald-200/50 dark:divide-emerald-800/50 dark:border-emerald-800/50">
                                        <NutrientRow label="Calories" value={portion.calories} unit=" kcal" digits={0} />
                                        <NutrientRow label="Protein" value={portion.protein_g} unit=" g" />
                                        <NutrientRow label="Total fat" value={portion.fat_g} unit=" g" />
                                        <NutrientRow label="Carbohydrate" value={portion.carbs_g} unit=" g" />
                                        <NutrientRow label="Fiber" value={portion.fiber_g} unit=" g" />
                                        <NutrientRow label="Omega-3 (total)" value={portion.omega3_g} unit=" g" />
                                        <NutrientRow
                                            label="Vitamin A (RAE) · FDC 1106"
                                            value={portion.vitamin_a_rae_mcg}
                                            unit=" µg RAE"
                                            digits={1}
                                        />
                                        <NutrientRow
                                            label="Vitamin B6 · FDC 1175 · mg"
                                            value={portion.vitamin_b6_mg}
                                            unit=" mg"
                                        />
                                        <NutrientRow
                                            label="Vitamin B12 · FDC 1178 · µg"
                                            value={portion.vitamin_b12_mcg}
                                            unit=" µg"
                                            digits={2}
                                        />
                                        <NutrientRow
                                            label="Folate (B9) · FDC 1177 · µg — Sickle Cell plans"
                                            value={portion.folate_mcg}
                                            unit=" µg"
                                            digits={1}
                                        />
                                        <NutrientRow label="Vitamin C" value={portion.vitamin_c_mg} unit=" mg" />
                                        <NutrientRow
                                            label="Calcium · FDC 1087 · mg"
                                            value={portion.calcium_mg}
                                            unit=" mg"
                                        />
                                        <NutrientRow label="Iron" value={portion.iron_mg} unit=" mg" />
                                        <NutrientRow label="Potassium" value={portion.potassium_mg} unit=" mg" />
                                        <NutrientRow label="Magnesium" value={portion.magnesium_mg} unit=" mg" />
                                    </ul>
                                </div>
                            )}

                            {data.warnings?.length > 0 && (
                                <ul className="list-inside list-disc text-xs text-amber-800 dark:text-amber-200/80">
                                    {data.warnings.map((w) => (
                                        <li key={w}>{w}</li>
                                    ))}
                                </ul>
                            )}

                            {!portion && data.success && (
                                <p className="text-sm text-emerald-800/70 dark:text-emerald-300/70">
                                    No USDA Foundation / SR Legacy match for this portion — tips and standardization
                                    still apply.
                                </p>
                            )}
                        </div>
                    </motion.article>
                )}
            </AnimatePresence>
        </div>
    );
}

function NutrientRow({ label, value, unit, digits = 2 }) {
    return (
        <li className="flex justify-between gap-4 px-4 py-2.5 text-sm">
            <span className="text-emerald-800/90 dark:text-emerald-200/80">{label}</span>
            <span className="font-medium tabular-nums text-emerald-950 dark:text-emerald-50">
                {formatNum(value, digits)}
                {unit}
            </span>
        </li>
    );
}

function DebouncedSearchTrigger({ input, onSearch }) {
    useEffect(() => {
        const t = input.trim();
        if (t.length < 3) {
            return undefined;
        }
        const id = setTimeout(() => {
            onSearch();
        }, 1000);
        return () => clearTimeout(id);
    }, [input, onSearch]);

    return null;
}
