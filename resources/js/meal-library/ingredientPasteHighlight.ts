import { normalizeIngredientKey } from './aggregateIngredientNutrition';
import { parseSingleIngredientQuantitySegment } from './ingredientQuantityString';

export type PasteHighlightPart = { text: string; tone: 'plain' | 'ok' | 'bad' };

export function splitIngredientSegmentsWithIndices(normalizedCell: string): Array<{ raw: string; start: number; end: number }> {
    const segments: Array<{ raw: string; start: number; end: number }> = [];
    let segStart = 0;
    for (let i = 0; i <= normalizedCell.length; i += 1) {
        const atEnd = i === normalizedCell.length;
        const ch = normalizedCell[i];
        if (!atEnd && ch !== '|' && ch !== '\n') {
            continue;
        }

        const piece = normalizedCell.slice(segStart, i);
        const t = piece.trim();
        if (t.length > 0) {
            const lead = piece.length - piece.trimStart().length;
            const start = segStart + lead;
            segments.push({ raw: t, start, end: start + t.length });
        }
        segStart = i + 1;
    }

    return segments;
}

function mergeAdjacentHighlightParts(parts: PasteHighlightPart[]): PasteHighlightPart[] {
    const out: PasteHighlightPart[] = [];
    for (const p of parts) {
        if (!p.text) {
            continue;
        }
        const prev = out[out.length - 1];
        if (prev && prev.tone === p.tone) {
            prev.text += p.text;
        } else {
            out.push({ text: p.text, tone: p.tone });
        }
    }

    return out;
}

/**
 * Builds monospace-friendly spans for overlay highlighting: matched ingredient names green,
 * unmatched names red, parse failures red for the whole segment.
 */
export function buildIngredientPasteHighlightParts(
    cell: string,
    profiles: readonly { name: string }[],
): PasteHighlightPart[] {
    const normalized = cell.replace(/\r\n/g, '\n');
    const byNorm = new Set<string>();
    for (const p of profiles) {
        byNorm.add(normalizeIngredientKey(p.name));
    }

    const slices = splitIngredientSegmentsWithIndices(normalized);
    if (slices.length === 0) {
        if (normalized.trim() === '') {
            return [];
        }

        return [{ text: normalized, tone: 'plain' }];
    }

    const parts: PasteHighlightPart[] = [];
    let cursor = 0;
    for (const slice of slices) {
        if (cursor < slice.start) {
            parts.push({ text: normalized.slice(cursor, slice.start), tone: 'plain' });
        }

        const chunk = normalized.slice(slice.start, slice.end);
        const parsed = parseSingleIngredientQuantitySegment(slice.raw);

        if (!parsed) {
            parts.push({ text: chunk, tone: 'bad' });
        } else {
            const nameIdx = slice.raw.indexOf(parsed.name);
            if (nameIdx < 0) {
                parts.push({ text: chunk, tone: 'bad' });
            } else {
                const before = slice.raw.slice(0, nameIdx);
                const after = slice.raw.slice(nameIdx + parsed.name.length);
                const tone: 'ok' | 'bad' = byNorm.has(normalizeIngredientKey(parsed.name)) ? 'ok' : 'bad';
                if (before) {
                    parts.push({ text: before, tone: 'plain' });
                }
                parts.push({ text: parsed.name, tone });
                if (after) {
                    parts.push({ text: after, tone: 'plain' });
                }
            }
        }

        cursor = slice.end;
    }

    if (cursor < normalized.length) {
        parts.push({ text: normalized.slice(cursor), tone: 'plain' });
    }

    return mergeAdjacentHighlightParts(parts);
}
