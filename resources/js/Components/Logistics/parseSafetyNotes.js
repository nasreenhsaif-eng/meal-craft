const NONE = new Set(['', '—', '-', 'none', 'none declared', 'n/a', 'na']);

/**
 * @param {string} s
 */
function isNone(s) {
    return NONE.has(String(s).trim().toLowerCase());
}

/**
 * @param {string} blob
 * @returns {string[]}
 */
export function splitNoteSegments(blob) {
    if (!blob || isNone(blob)) {
        return [];
    }
    return String(blob)
        .split(/[;·\n|]+/)
        .map((p) => p.trim())
        .filter((p) => p.length > 0 && !isNone(p));
}

/**
 * Classify a single note fragment (after splitting).
 * @param {string} text
 * @returns {'allergy' | 'dislike' | null}
 */
export function classifySafetySegment(text) {
    const t = String(text).trim();
    if (!t || isNone(t)) {
        return null;
    }
    const L = t.toLowerCase();

    if (/^(no |without |omit |hold |exclude |minus )/i.test(t)) {
        return 'dislike';
    }
    if (/\b(extra ice|ice pack|am delivery|pm delivery|delivery window)\b/i.test(L)) {
        return 'dislike';
    }
    if (/\b(no cilantro|hate cilantro|pick out|don't like|dont like|do not like|not a fan)\b/i.test(L)) {
        return 'dislike';
    }

    if (/\b(anaphylaxis|epipen|epi-pen|cross-?contam|cross contamination)\b/i.test(L)) {
        return 'allergy';
    }
    if (/\b(allergic to|allergy to|food allergy|severe reaction)\b/i.test(L)) {
        return 'allergy';
    }
    if (/\b(celiac|coeliac|histamine|sulfite|lactose intolerance)\b/i.test(L)) {
        return 'allergy';
    }
    if (/\b(shellfish|crustacean|mollusk|tree nuts?|peanuts?|sesame|gluten|dairy|egg|fish|salmon|tuna|soy|mustard)\b/i.test(L)) {
        return 'allergy';
    }
    if (/\bavoid\b/i.test(L) && /\b(whey|dairy|milk|lactose|casein)\b/i.test(L)) {
        return 'allergy';
    }
    if (/\bavoid\b/i.test(L)) {
        return 'dislike';
    }
    if (/\b(intolerance|sensitive to|sensitivity)\b/i.test(L)) {
        return 'allergy';
    }

    if (/\b(nut|nuts)\b/i.test(L) && !/^no /i.test(t)) {
        return 'allergy';
    }

    return 'dislike';
}

/**
 * @param {string[]} items
 * @returns {string[]}
 */
function dedupePreserveOrder(items) {
    const seen = new Set();
    const out = [];
    for (const x of items) {
        const k = x.trim().toLowerCase();
        if (!k || seen.has(k)) {
            continue;
        }
        seen.add(k);
        out.push(x.trim());
    }
    return out;
}

/**
 * Scan allergy + special-request copy and split into allergy vs dislike lists for tagging.
 *
 * @param {{ allergyNotes?: string; allergies?: string; specialRequests?: string }} fields
 * @returns {{ allergies: string[]; dislikes: string[] }}
 */
export function partitionSafetyNotes(fields) {
    const allergyBlob = fields.allergyNotes ?? fields.allergies ?? '';
    const specialBlob = fields.specialRequests ?? '';

    /** @type {string[]} */
    const allergyOut = [];
    /** @type {string[]} */
    const dislikeOut = [];

    for (const seg of splitNoteSegments(allergyBlob)) {
        const kind = classifySafetySegment(seg);
        if (kind === 'allergy') {
            allergyOut.push(seg);
        } else if (kind === 'dislike') {
            dislikeOut.push(seg);
        }
    }
    for (const seg of splitNoteSegments(specialBlob)) {
        const kind = classifySafetySegment(seg);
        if (kind === 'allergy') {
            allergyOut.push(seg);
        } else if (kind === 'dislike') {
            dislikeOut.push(seg);
        }
    }

    return {
        allergies: dedupePreserveOrder(allergyOut),
        dislikes: dedupePreserveOrder(dislikeOut),
    };
}
