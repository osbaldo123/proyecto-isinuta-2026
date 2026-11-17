<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye, EyeOff, KeyRound, LoaderCircle, Lock, Mail } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirm = ref(false);

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <Head title="Nueva contraseña" />

    <div
        class="relative flex min-h-svh flex-col items-center justify-center bg-gradient-to-br from-slate-100 via-cyan-50 to-white p-4 sm:p-6 dark:from-slate-950 dark:via-cyan-950 dark:to-slate-900"
    >
        <Link
            :href="route('login')"
            class="absolute left-3 top-3 inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-100 px-3.5 py-2 text-sm font-medium text-cyan-800 shadow-sm transition hover:bg-cyan-600 hover:text-white sm:left-5 sm:top-5 dark:border-cyan-400 dark:bg-cyan-600 dark:text-white dark:hover:bg-cyan-500"
        >
            <ArrowLeft class="size-4" />
            Volver al inicio de sesión
        </Link>

        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl sm:p-8 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/40">
            <div class="mb-6 space-y-2 text-center sm:mb-8">
                <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400">
                    <KeyRound class="size-6" />
                </div>
                <h1 class="text-xl font-bold text-slate-900 sm:text-2xl dark:text-white">Nueva contraseña</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Elige una contraseña segura para tu cuenta de ISINUTA.
                </p>
            </div>

            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="email" class="text-slate-700 dark:text-slate-200">Correo electrónico</Label>
                    <div
                        class="flex items-center gap-3 rounded-xl border border-slate-300 bg-slate-50 px-3.5 dark:border-slate-600 dark:bg-slate-800/70"
                    >
                        <Mail class="size-4 shrink-0 text-slate-400" />
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            readonly
                            class="w-full border-0 bg-transparent py-2.5 text-sm !text-slate-700 [color-scheme:light] focus:outline-none focus:ring-0 dark:!text-slate-300 dark:[color-scheme:dark]"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password" class="text-slate-700 dark:text-slate-200">Nueva contraseña</Label>
                    <div
                        class="flex items-center gap-3 rounded-xl border border-slate-300 bg-white px-3.5 focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-200 dark:border-slate-600 dark:bg-slate-800 dark:focus-within:ring-cyan-500/30"
                    >
                        <Lock class="size-4 shrink-0 text-slate-400" />
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            required
                            autofocus
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="w-full border-0 bg-transparent py-2.5 text-sm !text-slate-900 caret-slate-900 placeholder:text-slate-400 [color-scheme:light] focus:outline-none focus:ring-0 dark:!text-slate-50 dark:caret-slate-50 dark:[color-scheme:dark]"
                        />
                        <button
                            type="button"
                            class="shrink-0 text-slate-500 transition hover:text-slate-700 dark:hover:text-slate-300"
                            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation" class="text-slate-700 dark:text-slate-200">Confirmar contraseña</Label>
                    <div
                        class="flex items-center gap-3 rounded-xl border border-slate-300 bg-white px-3.5 focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-200 dark:border-slate-600 dark:bg-slate-800 dark:focus-within:ring-cyan-500/30"
                    >
                        <Lock class="size-4 shrink-0 text-slate-400" />
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showConfirm ? 'text' : 'password'"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="w-full border-0 bg-transparent py-2.5 text-sm !text-slate-900 caret-slate-900 placeholder:text-slate-400 [color-scheme:light] focus:outline-none focus:ring-0 dark:!text-slate-50 dark:caret-slate-50 dark:[color-scheme:dark]"
                        />
                        <button
                            type="button"
                            class="shrink-0 text-slate-500 transition hover:text-slate-700 dark:hover:text-slate-300"
                            :aria-label="showConfirm ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            @click="showConfirm = !showConfirm"
                        >
                            <EyeOff v-if="showConfirm" class="size-4" />
                            <Eye v-else class="size-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-1 flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-600 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-cyan-500 disabled:opacity-60 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                >
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <KeyRound v-else class="size-4" />
                    Guardar nueva contraseña
                </button>
            </form>
        </div>
    </div>
</template>
