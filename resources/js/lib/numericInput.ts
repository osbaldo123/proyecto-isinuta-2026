/** Utilidades para entradas que solo aceptan números. */

export type NumericMode = 'integer' | 'decimal';

/** Deja solo dígitos (enteros: CI, meses, teléfono, lote). */
export function sanitizeInteger(raw: string): string {
    return String(raw ?? '').replace(/\D/g, '');
}

/**
 * Deja dígitos y un solo separador decimal (punto o coma → punto).
 * Acepta vacío o solo "." mientras se escribe.
 */
export function sanitizeDecimal(raw: string): string {
    let cleaned = String(raw ?? '')
        .replace(/[^\d.,]/g, '')
        .replace(/,/g, '.');

    const firstDot = cleaned.indexOf('.');
    if (firstDot !== -1) {
        cleaned =
            cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
    }

    return cleaned;
}

export function sanitizeNumeric(raw: string, mode: NumericMode = 'integer'): string {
    return mode === 'decimal' ? sanitizeDecimal(raw) : sanitizeInteger(raw);
}

/** Parsea a número; vacío o inválido → 0. */
export function parseNumeric(raw: string | number, mode: NumericMode = 'decimal'): number {
    const cleaned = sanitizeNumeric(String(raw ?? ''), mode);
    if (cleaned === '' || cleaned === '.') {
        return 0;
    }

    const n = Number(cleaned);

    return Number.isFinite(n) ? n : 0;
}

const CONTROL_KEYS = new Set([
    'Backspace',
    'Delete',
    'Tab',
    'Escape',
    'Enter',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Home',
    'End',
]);

/** true si la tecla debe bloquearse en un campo numérico. */
export function shouldBlockNumericKey(event: KeyboardEvent, mode: NumericMode): boolean {
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return false;
    }

    if (CONTROL_KEYS.has(event.key)) {
        return false;
    }

    if (/^\d$/.test(event.key)) {
        return false;
    }

    if (mode === 'decimal' && (event.key === '.' || event.key === ',')) {
        const target = event.target as HTMLInputElement | null;
        const value = target?.value ?? '';
        // Un solo separador decimal.
        if (value.includes('.') || value.includes(',')) {
            return true;
        }

        return false;
    }

    // type=number aún permite e/E/+/- en varios navegadores.
    return true;
}
