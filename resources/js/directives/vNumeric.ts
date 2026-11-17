import type { Directive, DirectiveBinding } from 'vue';
import { sanitizeNumeric, shouldBlockNumericKey, type NumericMode } from '@/lib/numericInput';

type NumericEl = HTMLElement & {
    __vNumericCleanup?: () => void;
};

function resolveMode(binding: DirectiveBinding): NumericMode {
    return binding.modifiers.decimal ? 'decimal' : 'integer';
}

function resolveInput(el: HTMLElement): HTMLInputElement | null {
    if (el instanceof HTMLInputElement) {
        return el;
    }

    return el.querySelector('input');
}

/**
 * v-numeric → solo dígitos
 * v-numeric.decimal → dígitos y un decimal
 *
 * Bloquea teclas no numéricas y limpia pegado / entrada (capture, antes de v-model).
 */
export const vNumeric: Directive<HTMLElement, undefined> = {
    mounted(el, binding) {
        const input = resolveInput(el);
        if (!input) {
            return;
        }

        const mode = resolveMode(binding);

        if (!input.getAttribute('inputmode')) {
            input.setAttribute('inputmode', mode === 'decimal' ? 'decimal' : 'numeric');
        }

        const onKeydown = (event: KeyboardEvent) => {
            if (shouldBlockNumericKey(event, mode)) {
                event.preventDefault();
            }
        };

        const onPaste = (event: ClipboardEvent) => {
            event.preventDefault();
            const pasted = event.clipboardData?.getData('text') ?? '';
            const insert = sanitizeNumeric(pasted, mode);
            const start = input.selectionStart ?? input.value.length;
            const end = input.selectionEnd ?? input.value.length;
            const merged = sanitizeNumeric(
                input.value.slice(0, start) + insert + input.value.slice(end),
                mode,
            );
            input.value = merged;
            input.setSelectionRange(merged.length, merged.length);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };

        // Capture: corrige el valor antes de que v-model lea el evento.
        const onInput = () => {
            const next = sanitizeNumeric(input.value, mode);
            if (next !== input.value) {
                const pos = input.selectionStart ?? next.length;
                input.value = next;
                const caret = Math.min(pos, next.length);
                try {
                    input.setSelectionRange(caret, caret);
                } catch {
                    // type=number no siempre soporta setSelectionRange
                }
            }
        };

        input.addEventListener('keydown', onKeydown);
        input.addEventListener('paste', onPaste);
        input.addEventListener('input', onInput, true);

        (el as NumericEl).__vNumericCleanup = () => {
            input.removeEventListener('keydown', onKeydown);
            input.removeEventListener('paste', onPaste);
            input.removeEventListener('input', onInput, true);
        };
    },

    unmounted(el) {
        const cleanup = (el as NumericEl).__vNumericCleanup;
        cleanup?.();
        delete (el as NumericEl).__vNumericCleanup;
    },
};
