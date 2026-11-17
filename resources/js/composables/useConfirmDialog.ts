import { reactive } from 'vue';

export type ConfirmVariant = 'danger' | 'warning' | 'default';

export type ConfirmOptions = {
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: ConfirmVariant;
};

type ConfirmState = {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    cancelLabel: string;
    variant: ConfirmVariant;
};

const state = reactive<ConfirmState>({
    open: false,
    title: 'Confirmar acción',
    message: '',
    confirmLabel: 'Aceptar',
    cancelLabel: 'Cancelar',
    variant: 'danger',
});

let resolver: ((value: boolean) => void) | null = null;

function close(result: boolean) {
    state.open = false;
    resolver?.(result);
    resolver = null;
}

export function useConfirmDialog() {
    const confirm = (options: ConfirmOptions | string): Promise<boolean> => {
        const opts = typeof options === 'string' ? { message: options } : options;

        if (resolver) {
            resolver(false);
            resolver = null;
        }

        state.title = opts.title ?? 'Confirmar acción';
        state.message = opts.message;
        state.confirmLabel = opts.confirmLabel ?? 'Aceptar';
        state.cancelLabel = opts.cancelLabel ?? 'Cancelar';
        state.variant = opts.variant ?? 'danger';
        state.open = true;

        return new Promise<boolean>((resolve) => {
            resolver = resolve;
        });
    };

    return {
        state,
        confirm,
        accept: () => close(true),
        cancel: () => close(false),
    };
}
