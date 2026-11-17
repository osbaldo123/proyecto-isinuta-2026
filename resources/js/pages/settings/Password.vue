<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { formInputClass, formLabelClass, formPrimaryButtonClass } from '@/lib/formStyles';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/settings/profile' },
    { title: 'Seguridad', href: '/settings/password' },
];

const passwordInput = ref<HTMLInputElement>();
const currentPasswordInput = ref<HTMLInputElement>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put('/settings/password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: Record<string, string>) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }

            if (errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Seguridad" />

        <SettingsLayout>
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">Actualizar contraseña</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Use una contraseña larga y segura para proteger su cuenta
                    </p>
                </div>

                <form class="space-y-5 px-5 py-5 sm:px-6" @submit.prevent="updatePassword">
                    <div>
                        <label for="current_password" :class="formLabelClass">Contraseña actual</label>
                        <input
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Contraseña actual"
                            :class="formInputClass"
                        />
                        <InputError class="mt-1" :message="form.errors.current_password" />
                    </div>

                    <div>
                        <label for="password" :class="formLabelClass">Nueva contraseña</label>
                        <input
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Nueva contraseña"
                            :class="formInputClass"
                        />
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <div>
                        <label for="password_confirmation" :class="formLabelClass">Confirmar contraseña</label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Confirmar contraseña"
                            :class="formInputClass"
                        />
                        <InputError class="mt-1" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                            Guardar contraseña
                        </button>
                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm font-medium text-emerald-600">Guardado.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
