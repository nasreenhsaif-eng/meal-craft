/**
 * Client-side fuzzy search for verified ingredient names (combobox).
 * - Multi-word: every token must appear as a substring (any order).
 * - Single token: substring match, else ordered subsequence (e.g. "br" → "brown rice").
 */

export type NamedIngredientProfile = {
    id?: number;
    name: string;
};

function isSubsequence(query: string, nameLower: string): boolean {
    let qi = 0;
    for (let i = 0; i < nameLower.length && qi < query.length; i += 1) {
        if (nameLower[i] === query[qi]) {
            qi += 1;
        }
    }
    return qi === query.length;
}

/** Higher score sorts first. Zero = no match. */
export function ingredientNameSearchScore(name: string, rawQuery: string): number {
    const n = name.trim().toLowerCase();
    const q = rawQuery.trim().toLowerCase();
    if (!q) {
        return 0;
    }
    const tokens = q.split(/\s+/).filter(Boolean);
    if (tokens.length > 1) {
        return tokens.every((t) => n.includes(t)) ? 10 + tokens.length : 0;
    }
    const t = tokens[0] ?? '';
    if (!t) {
        return 0;
    }
    if (n.includes(t)) {
        return 8;
    }
    if (isSubsequence(t, n)) {
        return 5;
    }
    return 0;
}

export function filterIngredientsForCombobox<T extends NamedIngredientProfile>(
    profiles: readonly T[],
    rawQuery: string,
    limit = 15,
): T[] {
    const q = rawQuery.trim();
    if (!q) {
        return [];
    }
    return profiles
        .map((p) => ({ p, score: ingredientNameSearchScore(p.name, q) }))
        .filter((x) => x.score > 0)
        .sort((a, b) => b.score - a.score || a.p.name.localeCompare(b.p.name))
        .slice(0, limit)
        .map((x) => x.p);
}
