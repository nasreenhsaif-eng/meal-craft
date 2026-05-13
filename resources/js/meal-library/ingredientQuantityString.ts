/**
 * Pipe-separated ingredient quantity strings (aligned with {@see App\Support\IngredientQuantityStringParser}).
 */

export type ParsedIngredientSegment = {
    name: string;
    amount: number;
    unit: string;
};

/** Longer tokens first — keep in sync with PHP `IngredientQuantityStringParser::UNIT_SUFFIX_PATTERN`. */
const UNIT_SUFFIX_PATTERN =
    'milliliters?|millilitres?|kilograms?|teaspoons?|tablespoons?|liters?|litres?|cups?|grams?|milliliter|millilitre|kilogram|teaspoon|tablespoon|liter|litre|cup|g|kg|ml|ltr|tsp|tbsp|\\bl\\b';

/** Amount: digits with optional `.` or `,` decimal separator. */
const AMOUNT_CAPTURE = '(\\d+(?:[.,]\\d+)?)';

const COLON_SEGMENT = new RegExp(`^(.+?):${AMOUNT_CAPTURE}\\s*(?:(${UNIT_SUFFIX_PATTERN}))?\\s*$`, 'iu');

/**
 * Name (amount + unit) with optional spaces before `(` and flexible spacing inside parens.
 * e.g. {@code Chicken Broth (710ml)}, {@code Chicken Broth(710ml)}, {@code Item (0.5 kg)}.
 */
const PAREN_SEGMENT = new RegExp(`^(.+?)\\s*\\(\\s*${AMOUNT_CAPTURE}\\s*(${UNIT_SUFFIX_PATTERN})\\s*\\)\\s*$`, 'iu');

const SPACE_SEGMENT = new RegExp(`^(.+?)\\s+${AMOUNT_CAPTURE}\\s*(${UNIT_SUFFIX_PATTERN})\\s*$`, 'iu');

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

/**
 * Split a paste cell on `|` or line breaks (CR / LF / CRLF). Trims each segment; skips empties.
 */
export function splitIngredientQuantityCellParts(cell: string): string[] {
    return cell
        .replace(/\r\n/g, '\n')
        .split(/(?:\||\n)+/)
        .map((p) => p.trim())
        .filter((p) => p.length > 0);
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
