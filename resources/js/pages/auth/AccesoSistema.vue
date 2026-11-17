<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Droplets, Eye, EyeOff, LoaderCircle, Lock, Mail, ShieldCheck } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const form = useForm({
    email: '',
    password: '',
});

const showPassword = ref(false);
const fieldsReady = ref(false);

const limpiarCampos = () => {
    form.defaults({
        email: '',
        password: '',
    });
    form.reset();
    form.clearErrors();
};

onMounted(() => {
    limpiarCampos();
    window.setTimeout(() => {
        limpiarCampos();
        fieldsReady.value = true;
    }, 80);
});

const submit = () => {
    form.post('/acceso', {
        onFinish: () => {
            form.reset('password');
            form.password = '';
        },
    });
};
</script>

<template>
    <Head title="Acceder al sistema" />

    <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-white p-4 sm:p-6 dark:bg-slate-950">
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
            <img
                src="/images/fondo-gota.png?v=2"
                alt=""
                class="h-[120vh] w-[120vw] max-w-none object-contain opacity-100"
            />
        </div>

        <Link
            href="/"
            class="absolute left-3 top-3 z-20 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-800 shadow-md transition hover:bg-cyan-600 hover:text-white sm:left-5 sm:top-5 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
        >
            <ArrowLeft class="size-4" />
            Volver
        </Link>

        <div
            class="relative z-10 w-full max-w-md rounded-3xl border border-white/70 bg-white/60 p-5 shadow-xl backdrop-blur-md sm:p-8 dark:border-slate-600/50 dark:bg-slate-900/55"
        >
            <div class="mb-6 space-y-2 text-center sm:mb-8">
                <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-2xl bg-sky-500 text-white shadow-lg shadow-sky-300/50 dark:shadow-sky-900/40">
                    <ShieldCheck class="size-7" />
                </div>
                <h1 class="text-xl font-extrabold text-slate-900 drop-shadow-sm sm:text-2xl dark:text-white">Acceder al sistema</h1>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                    Ingrese su correo y contraseña
                </p>
            </div>

            <form
                class="flex flex-col gap-5"
                autocomplete="off"
                data-lpignore="true"
                data-1p-ignore="true"
                @submit.prevent="submit"
            >
                <input type="text" name="fake_user" autocomplete="username" tabindex="-1" class="hidden" aria-hidden="true" />
                <input type="password" name="fake_pass" autocomplete="current-password" tabindex="-1" class="hidden" aria-hidden="true" />

                <div class="grid gap-2">
                    <Label for="acceso_rol_email" class="font-bold text-slate-900 dark:text-white">Correo</Label>
                    <div
                        class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 shadow-sm focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-200 dark:border-cyan-800 dark:bg-slate-800 dark:focus-within:ring-cyan-500/30"
                    >
                        <Mail class="size-4 shrink-0 text-cyan-600 dark:text-cyan-400" />
                        <input
                            id="acceso_rol_email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            :readonly="!fieldsReady"
                            placeholder="correo@isinuta.test"
                            class="w-full border-0 bg-transparent py-2.5 text-sm !text-slate-900 caret-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:!text-slate-50 dark:caret-slate-50"
                            @focus="fieldsReady = true"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="acceso_rol_password" class="font-bold text-slate-900 dark:text-white">Contraseña</Label>
                    <div
                        class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 shadow-sm focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-200 dark:border-cyan-800 dark:bg-slate-800 dark:focus-within:ring-cyan-500/30"
                    >
                        <Lock class="size-4 shrink-0 text-cyan-600 dark:text-cyan-400" />
                        <input
                            id="acceso_rol_password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            required
                            autocomplete="current-password"
                            autocapitalize="off"
                            spellcheck="false"
                            data-lpignore="true"
                            data-1p-ignore="true"
                            :readonly="!fieldsReady"
                            placeholder="••••••••"
                            class="w-full border-0 bg-transparent py-2.5 text-sm !text-slate-900 caret-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:!text-slate-50 dark:caret-slate-50"
                            @focus="fieldsReady = true"
                        />
                        <button
                            type="button"
                            class="shrink-0 text-slate-700 transition hover:text-slate-900 dark:text-cyan-400 dark:hover:text-cyan-200"
                            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-sky-500 py-3 text-sm font-semibold text-white shadow-lg shadow-sky-300/50 transition hover:-translate-y-0.5 hover:bg-sky-600 hover:shadow-xl disabled:opacity-60 dark:shadow-sky-900/40"
                >
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <Droplets v-else class="size-4" />
                    Acceder al sistema
                </button>
            </form>
        </div>
    </div>
</template>
