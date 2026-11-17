<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound, LoaderCircle, Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Recuperar contraseña" />

    <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-white p-4 sm:p-6 dark:bg-slate-950">
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
            <img
                src="/images/fondo-gota.png?v=2"
                alt=""
                class="h-[120vh] w-[120vw] max-w-none object-contain opacity-100"
            />
        </div>

        <Link
            :href="route('login')"
            class="absolute left-3 top-3 z-20 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-800 shadow-md transition hover:bg-cyan-600 hover:text-white sm:left-5 sm:top-5 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
        >
            <ArrowLeft class="size-4" />
            Volver al inicio de sesión
        </Link>

        <div
            class="relative z-10 w-full max-w-md rounded-3xl border border-white/70 bg-white/60 p-5 shadow-xl backdrop-blur-md sm:p-8 dark:border-slate-600/50 dark:bg-slate-900/55"
        >
            <div class="mb-6 space-y-2 text-center sm:mb-8">
                <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-2xl bg-sky-500 text-white shadow-lg shadow-sky-300/50">
                    <KeyRound class="size-7" />
                </div>
                <h1 class="text-xl font-extrabold text-slate-900 drop-shadow-sm sm:text-2xl dark:text-white">¿Olvidaste tu contraseña?</h1>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                    Ingresa tu correo electrónico y te enviaremos un enlace para restablecerla.
                </p>
            </div>

            <div
                v-if="status"
                class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
            >
                {{ status }}
            </div>

            <form class="flex flex-col gap-5" autocomplete="off" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="email" class="font-bold text-slate-900 dark:text-white">Correo electrónico</Label>
                    <div
                        class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 shadow-sm focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-200 dark:border-cyan-800 dark:bg-slate-800 dark:focus-within:ring-cyan-500/30"
                    >
                        <Mail class="size-4 shrink-0 text-cyan-600 dark:text-cyan-400" />
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            autocomplete="off"
                            placeholder="correo@isinuta.test"
                            class="w-full border-0 bg-transparent py-2.5 text-sm !text-slate-900 caret-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:!text-slate-50 dark:caret-slate-50"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-1 flex w-full items-center justify-center gap-2 rounded-xl bg-sky-500 py-3 text-sm font-semibold text-white shadow-lg shadow-sky-300/50 transition hover:-translate-y-0.5 hover:bg-sky-600 hover:shadow-xl disabled:opacity-60"
                >
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <Mail v-else class="size-4" />
                    Enviar enlace de recuperación
                </button>
            </form>

            <p class="mt-6 text-center text-sm font-semibold text-slate-800 dark:text-slate-200">
                ¿Recordaste tu contraseña?
                <Link
                    :href="route('login')"
                    class="font-extrabold text-sky-700 underline-offset-2 hover:underline dark:text-cyan-300"
                >
                    Iniciar sesión
                </Link>
            </p>
        </div>
    </div>
</template>
