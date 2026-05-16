/**
 * Ingredient quantity strings (aligned with {@see App\Support\IngredientQuantityStringParser}).
 */

export type ParsedIngredientSegment = {
    name: string;
    amount: number;
    unit: string;
};

/** Longer tokens first — keep in sync with PHP `IngredientQuantityStringParser::UNIT_SUFFIX_PATTERN`. */
const UNIT_SUFFIX_PATTERN =
    'milliliters?|millilitres?|kilograms?|teaspoons?|tablespoons?|liters?|litres?|cups?|grams?|milliliter|millilitre|kilogram|teaspoon|tablespoon|liter|litre|cup|gr|kg|ml|ltr|tsp|tbsp|g|\\bl\\b';

/** Amount: digits with optional `.` or `,` decimal separator. */
const AMOUNT_CAPTURE = '(\\d+(?:[.,]\\d+)?)';

const COLON_SEGMENT = new RegExp(`^(.+?):${AMOUNT_CAPTURE}\\s*(?:(${UNIT_SUFFIX_PATTERN}))?\\s*$`, 'iu');

const PAREN_SEGMENT = new RegExp(`^(.+?)\\s*\\(\\s*${AMOUNT_CAPTURE}\\s*(${UNIT_SUFFIX_PATTERN})\\s*\\)\\s*$`, 'iu');

const SPACE_SEGMENT = new RegExp(`^(.+?)\\s+${AMOUNT_CAPTURE}\\s*(${UNIT_SUFFIX_PATTERN})\\s*$`, 'iu');

const WEIGHT_GROUP_PATTERN = new RegExp(
    `\\(\\s*${AMOUNT_CAPTURE}\\s*(?:${UNIT_SUFFIX_PATTERN})\\s*\\)`,
    'giu',
);

const MLS_PER_TSP = 5;

const MLS_PER_TBSP = 15;

const MLS_PER_CUP = 240;

function parseAmountToken(raw: string): number {
    const n = parseFloat(String(raw).trim().replace(',', '.'));
    return Number.isFinite(n) ? n : NaN;
}

export function normalizeIngredientQuantityUnit(raw: string): string {
    const t = raw.trim().toLowerCase();
    if (t === '' || t === 'g' || t === 'gram' || t === 'grams' || t === 'gr') {
        return 'g';
    }
    if (t === 'kg' || t === 'kilogram' || t === 'kilograms' || t === 'kgs') {
        return 'kg';
    }
    if (t === 'ml' || t === 'milliliter' || t === 'milliliters' || t === 'millilitre' || t === 'millilitres') {
        return 'ml';
    }
    if (t === 'ltr' || t === 'l' || t === 'liter' || t === 'liters' || t === 'litre' || t === 'litres') {
        return 'ltr';
    }
    if (t === 'tsp' || t === 'teaspoon' || t === 'teaspoons') {
        return 'tsp';
    }
    if (t === 'tbsp' || t === 'tablespoon' || t === 'tablespoons') {
        return 'tbsp';
    }
    if (t === 'cup' || t === 'cups' || t === 'c') {
        return 'cup';
    }
    return 'g';
}

/**
 * Converts an amount + unit to grams using ingredient density (g/ml) for volume units.
 * Matches {@see App\Services\RecipeIngredientUnitConverter::toGrams} (US tsp/tbsp/cup ml factors).
 */
export function gramsFromIngredientAmountAndUnit(amount: number, unit: string, densityGramsPerMl: number): number {
    if (!Number.isFinite(amount) || amount <= 0) {
        return 0;
    }
    const density = Number.isFinite(densityGramsPerMl) && densityGramsPerMl > 0 ? densityGramsPerMl : 1;
    const u = normalizeIngredientQuantityUnit(unit);

    switch (u) {
        case 'g':
            return amount;
        case 'kg':
            return amount * 1000;
        case 'ml':
            return amount * density;
        case 'ltr':
            return amount * 1000 * density;
        case 'tsp':
            return amount * MLS_PER_TSP * density;
        case 'tbsp':
            return amount * MLS_PER_TBSP * density;
        case 'cup':
            return amount * MLS_PER_CUP * density;
        default:
            return amount;
    }
}

function trimSegment(part: string): string {
    return part
        .trim()
        .replace(/\s+/gu, ' ')
        .replace(/^[\s,，、;﹔；]+|[\s,，、;﹔；]+$/gu, '');
}

export function sanitizeIngredientQuantityCell(cell: string): string {
    let out = cell.replace(/\r\n/g, '\n');
    out = out.replace(/\\\|/g, '|').replace(/&#124;|&vert;|&#x7C;/gi, '|');
    out = out.replace(/[│┃┆┇┊┋╎╏▕▏▐║‖∣｜¦]/gu, '|');
    out = out.replace(/（/gu, '(').replace(/）/gu, ')');
    out = out.replace(/[，、﹔；\uFF0C\u3001\uFE50\uFE51\uFE54]/gu, ',');
    out = out.replace(/[\u00A0\u1680\u2000-\u200B\u202F\u205F\u3000\uFEFF]/gu, ' ');
    out = out.replace(/\(\s*(\d+(?:[.,]\d+)?)\s*ng\s*\)/giu, '($1g)');

    return out.trim();
}

function countWeightGroupsInLine(line: string): number {
    const matches = line.match(WEIGHT_GROUP_PATTERN);
    return matches ? matches.length : 0;
}

function extractSegmentsByWeightParentheses(line: string): string[] {
    const pattern = new RegExp(WEIGHT_GROUP_PATTERN.source, 'giu');
    const parts: string[] = [];
    let offset = 0;
    let match: RegExpExecArray | null;

    while ((match = pattern.exec(line)) !== null) {
        const end = match.index + match[0].length;
        const segment = trimSegment(line.slice(offset, end));
        if (segment) {
            parts.push(segment);
        }
        offset = end;
        const remainder = line.slice(offset);
        const delimiter = remainder.match(/^\s*[,，、;﹔；\t]\s*/u);
        if (delimiter) {
            offset += delimiter[0].length;
        }
    }

    const tail = trimSegment(line.slice(offset));
    if (tail && !/^\(\s*\d/i.test(tail)) {
        parts.push(tail);
    }

    return parts;
}

function splitCommaSeparatedIngredientLine(line: string): string[] {
    if (!/[,，、;﹔；\t]/u.test(line) && countWeightGroupsInLine(line) < 2) {
        return [line];
    }

    if (/\t/u.test(line) && countWeightGroupsInLine(line) >= 2) {
        const tabParts = line
            .split(/\t+/u)
            .map((p) => trimSegment(p))
            .filter((p) => p.length > 0);
        if (tabParts.length > 1) {
            return tabParts;
        }
    }

    const byWeight = extractSegmentsByWeightParentheses(line);
    if (byWeight.length > 1) {
        return byWeight;
    }

    if (!/[,，、;﹔；\t]/u.test(line)) {
        return [line];
    }

    if (countWeightGroupsInLine(line) < 1) {
        return [line];
    }

    const segmentPattern = new RegExp(
        `([^,，、;﹔；\t]*\\(\\s*${AMOUNT_CAPTURE}\\s*(?:${UNIT_SUFFIX_PATTERN})\\s*\\))`,
        'giu',
    );
    const matches = [...line.matchAll(segmentPattern)].map((m) => trimSegment(m[1])).filter(Boolean);

    return matches.length > 1 ? matches : [line];
}

/**
 * Split a cell on `|`, line breaks, or comma/tab-separated {@code Name (Weightg)} groups.
 */
export function splitIngredientQuantityCellParts(cell: string): string[] {
    const sanitized = sanitizeIngredientQuantityCell(cell);
    if (!sanitized) {
        return [];
    }

    const segments: string[] = [];

    for (const pipePart of sanitized.split('|')) {
        for (const line of pipePart.split(/\n/u)) {
            const trimmedLine = trimSegment(line);
            if (!trimmedLine) {
                continue;
            }
            for (const piece of splitCommaSeparatedIngredientLine(trimmedLine)) {
                const trimmed = trimSegment(piece);
                if (trimmed) {
                    segments.push(trimmed);
                }
            }
        }
    }

    return segments;
}

export function cellLooksLikeMultiIngredientQuantityCell(cell: string): boolean {
    const sanitized = sanitizeIngredientQuantityCell(cell);
    if (!sanitized) {
        return false;
    }
    if (countWeightGroupsInLine(sanitized) >= 2) {
        return true;
    }
    return /[,，、;﹔；\t]/u.test(sanitized);
}

/**
 * Ingredient display names from a quantity cell (for missing-ingredient CSV expansion).
 */
export function ingredientNamesFromQuantityCell(cell: string): string[] {
    const names: string[] = [];

    for (const part of splitIngredientQuantityCellParts(cell)) {
        const parsed = parseSingleIngredientQuantitySegment(part);
        if (parsed?.name) {
            names.push(parsed.name);
            continue;
        }
        const fallback = trimSegment(part);
        if (fallback) {
            names.push(fallback);
        }
    }

    return [...new Set(names)];
}

/**
 * Expands composite pending labels (comma-separated spreadsheet cells) into individual names.
 */
export function expandMissingIngredientNames(names: string[]): string[] {
    const out: string[] = [];
    const seen = new Set<string>();

    for (const raw of names) {
        const label = typeof raw === 'string' ? raw.trim() : String(raw).trim();
        if (!label) {
            continue;
        }

        const labels = cellLooksLikeMultiIngredientQuantityCell(label)
            ? ingredientNamesFromQuantityCell(label)
            : [label];

        for (const name of labels) {
            const key = name.trim().toLowerCase();
            if (!key || seen.has(key)) {
                continue;
            }
            seen.add(key);
            out.push(name.trim());
        }
    }

    return out;
}

/**
 * Parse one trimmed segment (one of colon, parenthesis, or space-separated quantity forms).
 */
export function parseSingleIngredientQuantitySegment(part: string): ParsedIngredientSegment | null {
    const p = part.trim();
    if (!p) {
        return null;
    }

    let m = COLON_SEGMENT.exec(p);
    if (m) {
        const name = m[1].trim();
        const amount = parseAmountToken(m[2]);
        const unitRaw = (m[3] ?? '').trim();
        if (name && Number.isFinite(amount) && amount > 0) {
            return { name, amount, unit: normalizeIngredientQuantityUnit(unitRaw) };
        }

        return null;
    }

    m = PAREN_SEGMENT.exec(p);
    if (m) {
        const name = m[1].trim();
        const amount = parseAmountToken(m[2]);
        const unitRaw = m[3].trim();
        if (name && Number.isFinite(amount) && amount > 0) {
            return { name, amount, unit: normalizeIngredientQuantityUnit(unitRaw) };
        }

        return null;
    }

    m = SPACE_SEGMENT.exec(p);
    if (m) {
        const name = m[1].trim();
        const amount = parseAmountToken(m[2]);
        const unitRaw = m[3].trim();
        if (name && Number.isFinite(amount) && amount > 0) {
            return { name, amount, unit: normalizeIngredientQuantityUnit(unitRaw) };
        }
    }

    return null;
}

/**
 * @returns Parsed segments; invalid / empty parts are skipped.
 */
export function parseIngredientQuantityString(cell: string): ParsedIngredientSegment[] {
    const out: ParsedIngredientSegment[] = [];

    for (const part of splitIngredientQuantityCellParts(cell)) {
        const parsed = parseSingleIngredientQuantitySegment(part);
        if (parsed) {
            out.push(parsed);
        }
    }

    return out;
}
