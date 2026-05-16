/**
 * Client-side mirror of {@see App\Support\MealInstructionsText} for detail views and forms.
 */

function normalizeLineEndings(text: string): string {
    return text
        .replace(/\r\n/g, '\n')
        .replace(/\r/g, '\n')
        .replace(/\\n/g, '\n')
        .replace(/\u2028/g, '\n');
}

function trimTrailingSpacesPerLine(text: string): string {
    return text
        .split('\n')
        .map((line) => line.replace(/\s+$/u, ''))
        .join('\n')
        .trim();
}

/** @returns {string[]} */
function splitNumberedStepsInChunk(chunk: string) {
    const trimmed = chunk.trim();
    if (trimmed === '') {
        return [];
    }

    const parts = trimmed.split(/\s+(?=\d{1,2}[\.\)]\s+)/u).filter(Boolean);
    const segments = parts.length > 0 ? parts : [trimmed];

    const steps = [];
    for (const part of segments) {
        let line = part.trim().replace(/^\d{1,2}[\.\)]\s*/u, '').trim();
        if (line !== '') {
            steps.push(line);
        }
    }

    return steps.length > 0 ? steps : [trimmed.replace(/^\d{1,2}[\.\)]\s*/u, '').trim()].filter(Boolean);
}

/** @param {string | null | undefined} raw */
export function mealInstructionLinesFromRaw(raw) {
    if (raw == null) {
        return [];
    }

    const text = trimTrailingSpacesPerLine(normalizeLineEndings(String(raw).trim()));
    if (text === '') {
        return [];
    }

    const paragraphs = text.split(/\n+/).filter((p) => p.trim() !== '');
    /** @type {string[]} */
    const steps = [];

    for (const paragraph of paragraphs) {
        const cleaned = trimTrailingSpacesPerLine(paragraph);
        if (cleaned === '') {
            continue;
        }
        for (const step of splitNumberedStepsInChunk(cleaned)) {
            steps.push(step);
        }
    }

    return steps;
}

/**
 * @param {string | string[] | null | undefined} instructions
 * @param {string} [emptyLabel]
 * @returns {string[]}
 */
export function mealInstructionStepsForDisplay(instructions, emptyLabel = 'No written instructions on file.') {
    if (Array.isArray(instructions)) {
        if (instructions.length === 0) {
            return [emptyLabel];
        }

        if (instructions.length === 1 && /\d{1,2}[\.\)]\s/.test(instructions[0])) {
            const split = mealInstructionLinesFromRaw(instructions[0]);
            return split.length > 0 ? split : instructions;
        }

        return instructions.map((s) => String(s).trim()).filter((s) => s !== '');
    }

    const lines = mealInstructionLinesFromRaw(instructions);
    return lines.length > 0 ? lines : [emptyLabel];
}
