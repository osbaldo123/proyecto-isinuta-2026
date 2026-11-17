<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Info, X, XCircle } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useClientToastQueue } from '@/composables/useFlashToast';

type FlashType = 'success' | 'error' | 'info';

const FIELD_LABELS: Record<string, string> = {
    nombres: 'Nombres',
    apellidos: 'Apellidos',
    ci: 'Número de CI',
    telefono: 'Teléfono',
    email: 'Correo electrónico',
    direccion: 'Dirección',
    otb_id: 'OTB',
    fecha_afiliacion: 'Fecha de afiliación',
    forma_pago_afiliacion: 'Forma de pago',
    monto_afiliacion: 'Monto de afiliación',
    saldo_afiliacion: 'Saldo pendiente',
    estado: 'Estado',
    observaciones: 'Observaciones',
    servicios: 'Servicios',
    password: 'Contraseña',
    name: 'Nombre',
    code: 'Código',
};

const page = usePage();
const visible = ref(false);
const message = ref('');
const details = ref<string[]>([]);
const type = ref<FlashType>('success');
let hideTimer: ReturnType<typeof setTimeout> | null = null;
let lastShownKey = '';
let removeErrorListener: (() => void) | null = null;

const flash = computed(() => page.props.flash as {
    success?: string | null;
    error?: string | null;
    info?: string | null;
} | undefined);

const normalizeErrorValue = (value: unknown): string => {
    if (typeof value === 'string') {
        return value;
    }

    if (Array.isArray(value) && value.length > 0) {
        return String(value[0]);
    }

    if (value && typeof value === 'object' && 'message' in value) {
        return String((value as { message: unknown }).message);
    }

    return '';
};

const formatValidationErrors = (errors: Record<string, unknown>): { summary: string; lines: string[] } => {
    const lines = Object.entries(errors)
        .map(([field, value]) => {
            const text = normalizeErrorValue(value);
            if (!text) {
                return null;
            }

            const label = FIELD_LABELS[field] ?? field.replace(/_/g, ' ');

            return `${label}: ${text}`;
        })
        .filter((line): line is string => Boolean(line));

    if (lines.length === 0) {
        return {
            summary: 'Revisa los datos del formulario. Hay campos con error.',
            lines: [],
        };
    }

    if (lines.length === 1) {
        return { summary: lines[0], lines: [] };
    }

    return {
        summary: `Hay ${lines.length} campos con error:`,
        lines,
    };
};

const showFlash = (text: string, flashType: FlashType, detailLines: string[] = [], durationMs?: number) => {
    const key = `${flashType}:${text}:${detailLines.join('|')}`;
    if (key === lastShownKey && visible.value) {
        return;
    }

    lastShownKey = key;
    message.value = text;
    details.value = detailLines;
    type.value = flashType;
    visible.value = true;

    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    hideTimer = setTimeout(() => {
        visible.value = false;
        lastShownKey = '';
    }, durationMs ?? (detailLines.length > 0 ? 8000 : 5500));
};

watch(
    flash,
    (value) => {
        if (value?.success) {
            showFlash(value.success, 'success');
            return;
        }

        if (value?.error) {
            showFlash(value.error, 'error');
            return;
        }

        if (value?.info) {
            showFlash(value.info, 'info');
        }
    },
    { deep: true, immediate: true },
);

const clientToast = useClientToastQueue();

watch(clientToast, (toast) => {
    if (!toast) {
        return;
    }

    showFlash(toast.message, toast.type, [], toast.duration);
});

onMounted(() => {
    // router.on recibe un CustomEvent; los errores van en event.detail.errors
    removeErrorListener = router.on('error', (event) => {
        const payload = event as CustomEvent<{ errors?: Record<string, unknown> }> & {
            detail?: { errors?: Record<string, unknown> };
        };
        const errors =
            payload?.detail?.errors && typeof payload.detail.errors === 'object'
                ? payload.detail.errors
                : (event as unknown as Record<string, unknown>);

        const formatted = formatValidationErrors(errors ?? {});
        showFlash(formatted.summary, 'error', formatted.lines);
    });
});

const dismiss = () => {
    visible.value = false;
    lastShownKey = '';
    details.value = [];
    if (hideTimer) {
        clearTimeout(hideTimer);
    }
};

onUnmounted(() => {
    if (hideTimer) {
        clearTimeout(hideTimer);
    }
    removeErrorListener?.();
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="-translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="visible && message"
                class="fixed left-1/2 top-4 z-[200] w-[min(94vw,32rem)] -translate-x-1/2"
                role="status"
                aria-live="polite"
            >
                <div
                    class="flex items-start gap-3 rounded-2xl border px-4 py-3.5 shadow-2xl backdrop-blur-sm"
                    :class="{
                        'border-emerald-300 bg-emerald-50 text-emerald-900 shadow-emerald-900/10': type === 'success',
                        'border-red-300 bg-red-50 text-red-900 shadow-red-900/10': type === 'error',
                        'border-sky-300 bg-sky-50 text-sky-900 shadow-sky-900/10': type === 'info',
                    }"
                >
                    <CheckCircle2 v-if="type === 'success'" class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                    <XCircle v-else-if="type === 'error'" class="mt-0.5 size-5 shrink-0 text-red-600" />
                    <Info v-else class="mt-0.5 size-5 shrink-0 text-sky-600" />

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase tracking-wide opacity-70">
                            <template v-if="type === 'success'">Operación exitosa</template>
                            <template v-else-if="type === 'error'">Error en el formulario</template>
                            <template v-else>Aviso</template>
                        </p>
                        <p class="mt-0.5 text-sm font-medium leading-relaxed">{{ message }}</p>
                        <ul v-if="details.length" class="mt-2 list-disc space-y-1 pl-4 text-sm font-medium">
                            <li v-for="(line, index) in details" :key="index">{{ line }}</li>
                        </ul>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-1.5 opacity-60 transition hover:bg-black/5 hover:opacity-100"
                        aria-label="Cerrar"
                        @click="dismiss"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
