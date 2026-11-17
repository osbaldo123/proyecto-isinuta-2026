<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { shouldBlockNumericKey } from '@/lib/numericInput';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Droplets, LoaderCircle, Mail, RefreshCw, ShieldCheck } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    status?: string;
    email?: string;
    error?: string | null;
}>();

const form = useForm({
    code: '',
});

const resendForm = useForm({});

const digits = ref<string[]>(['', '', '', '', '', '']);
const inputs = ref<Array<HTMLInputElement | null>>([]);

const codigoEnviado = computed(
    () => props.status === 'verification-code-sent' || props.status === 'verification-link-sent',
);

const syncCodeFromDigits = () => {
    form.code = digits.value.join('');
};

const setDigit = (index: number, raw: string) => {
    const clean = raw.replace(/\D/g, '');

    if (clean.length > 1) {
        const chars = clean.slice(0, 6 - index).split('');
        chars.forEach((char, offset) => {
            digits.value[index + offset] = char;
        });
        syncCodeFromDigits();
        const nextIndex = Math.min(index + chars.length, 5);
        nextTick(() => inputs.value[nextIndex]?.focus());
        return;
    }

    digits.value[index] = clean.slice(-1);
    syncCodeFromDigits();

    if (clean && index < 5) {
        nextTick(() => inputs.value[index + 1]?.focus());
    }
};

const onKeydown = (index: number, event: KeyboardEvent) => {
    if (shouldBlockNumericKey(event, 'integer')) {
        event.preventDefault();
        return;
    }

    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        digits.value[index - 1] = '';
        syncCodeFromDigits();
        nextTick(() => inputs.value[index - 1]?.focus());
    }
};

watch(
    () => form.code,
    (value) => {
        const clean = value.replace(/\D/g, '').slice(0, 6);
        if (clean === digits.value.join('')) {
            return;
        }
        digits.value = Array.from({ length: 6 }, (_, i) => clean[i] ?? '');
    },
);

onMounted(() => {
    nextTick(() => inputs.value[0]?.focus());
});

const submit = () => {
    syncCodeFromDigits();
    if (form.code.length !== 6) {
        return;
    }
    form.post('/email/verify-code');
};

const resend = () => {
    resendForm.post('/email/verification-code', {
        onSuccess: () => {
            digits.value = ['', '', '', '', '', ''];
            form.code = '';
            form.clearErrors();
            nextTick(() => inputs.value[0]?.focus());
        },
    });
};
</script>

<template>
    <Head title="Verificar correo" />

    <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-slate-950 p-4 sm:p-6">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <img
                src="/images/fondo-gota.png?v=2"
                alt=""
                class="h-full w-full object-cover opacity-40"
            />
            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/40 via-slate-950/55 to-slate-950/80" />
        </div>

        <div
            class="relative z-10 w-full max-w-md rounded-3xl border border-cyan-400/30 bg-white/90 p-5 shadow-2xl backdrop-blur-md sm:p-8 dark:border-cyan-500/20 dark:bg-slate-900/85"
        >
            <div class="mb-6 space-y-3 text-center sm:mb-7">
                <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-sky-600 text-white shadow-lg shadow-cyan-500/30">
                    <Mail class="size-7" />
                </div>
                <div class="space-y-1.5">
                    <p class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">
                        <Droplets class="size-3.5" />
                        ISINUTA
                    </p>
                    <h1 class="text-xl font-extrabold text-slate-900 sm:text-2xl dark:text-white">
                        Verificar correo
                    </h1>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
                        Ingrese el código de 6 dígitos. Solo necesita hacerlo una vez.
                    </p>
                </div>
            </div>

            <div
                v-if="codigoEnviado"
                class="mb-4 flex items-start gap-2.5 rounded-xl border border-emerald-300/60 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                <CheckCircle2 class="mt-0.5 size-4 shrink-0" />
                <span>Se envió un código de verificación a su correo.</span>
            </div>

            <div
                v-if="error"
                class="mb-4 rounded-xl border border-red-300/60 bg-red-50 px-3.5 py-3 text-center text-sm text-red-700 dark:border-red-500/30 dark:bg-red-950/40 dark:text-red-200"
            >
                {{ error }}
            </div>

            <div
                v-if="email"
                class="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-center dark:border-slate-700 dark:bg-slate-800/60"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Código enviado a
                </p>
                <p class="mt-0.5 break-all text-sm font-bold text-slate-900 dark:text-white">
                    {{ email }}
                </p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div class="space-y-2.5">
                    <label class="flex items-center justify-center gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                        <ShieldCheck class="size-4 text-cyan-600 dark:text-cyan-400" />
                        Código de verificación
                    </label>

                    <div class="flex justify-center gap-2 sm:gap-2.5">
                        <input
                            v-for="(_, index) in digits"
                            :key="index"
                            :ref="(el) => { inputs[index] = el as HTMLInputElement | null }"
                            :value="digits[index]"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="1"
                            class="size-11 rounded-xl border border-slate-300 bg-white text-center text-lg font-bold text-slate-900 shadow-sm outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-400/40 sm:size-12 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                            @input="setDigit(index, ($event.target as HTMLInputElement).value)"
                            @keydown="onKeydown(index, $event)"
                        />
                    </div>

                    <p v-if="form.errors.code" class="text-center text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.code }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-3 text-sm font-bold text-white shadow-md transition hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="form.processing || form.code.length !== 6"
                >
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <ShieldCheck v-else class="size-4" />
                    Verificar
                </button>
            </form>

            <div class="mt-4 space-y-3">
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                    :disabled="resendForm.processing"
                    @click="resend"
                >
                    <LoaderCircle v-if="resendForm.processing" class="size-4 animate-spin" />
                    <RefreshCw v-else class="size-4" />
                    Reenviar código
                </button>

                <p v-if="resendForm.errors.email" class="text-center text-sm text-red-600 dark:text-red-400">
                    {{ resendForm.errors.email }}
                </p>

                <TextLink
                    href="/logout"
                    method="post"
                    as="button"
                    class="mx-auto block text-center text-sm font-medium text-slate-500 hover:text-cyan-700 dark:text-slate-400 dark:hover:text-cyan-300"
                >
                    Cerrar sesión
                </TextLink>
            </div>
        </div>

        <p class="relative z-10 mt-5 text-center text-xs font-medium text-slate-400">
            Asociación de Agua Potable y Alcantarillado Isinuta
        </p>
    </div>
</template>
