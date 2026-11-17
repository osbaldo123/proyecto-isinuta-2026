<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import CiIdentidadInput from '@/components/CiIdentidadInput.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import SouthAmericaPhoneInput from '@/components/SouthAmericaPhoneInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { formInputClass, formLabelClass, formPrimaryButtonClass } from '@/lib/formStyles';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

const props = defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
    perfil: {
        name: string;
        email: string;
        ci: string;
        telefono_codigo: string;
        telefono: string;
        role_label: string;
    };
    paisesTelefono: Array<{ value: string; label: string; dial: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/settings/profile' },
    { title: 'Perfil', href: '/settings/profile' },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
const { hasRole, isReadOnly } = usePermissions();

const puedeEliminarCuenta = !(isReadOnly.value && hasRole('presidente'));

const form = useForm({
    name: props.perfil.name,
    email: props.perfil.email,
    ci: props.perfil.ci,
    telefono_codigo: props.perfil.telefono_codigo || '+591',
    telefono: props.perfil.telefono,
});

const submit = () => {
    form.patch('/settings/profile', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Perfil" />

        <SettingsLayout>
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">Información del perfil</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Actualice sus datos personales
                        <span v-if="perfil.role_label"> · {{ perfil.role_label }}</span>
                    </p>
                </div>

                <form class="space-y-5 px-5 py-5 sm:px-6" @submit.prevent="submit">
                    <div>
                        <label for="name" :class="formLabelClass">Nombre completo</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            autocomplete="name"
                            placeholder="Nombre completo"
                            :class="formInputClass"
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <div>
                        <label for="email" :class="formLabelClass">Correo electrónico</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="username"
                            placeholder="correo@ejemplo.com"
                            :class="formInputClass"
                        />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <CiIdentidadInput
                        v-model:ci="form.ci"
                        :ci-error="form.errors.ci"
                    />

                    <SouthAmericaPhoneInput
                        v-model:telefono-codigo="form.telefono_codigo"
                        v-model:telefono="form.telefono"
                        :paises="paisesTelefono"
                        :codigo-error="form.errors.telefono_codigo"
                        :telefono-error="form.errors.telefono"
                    />

                    <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                        Su correo no está verificado.
                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="ml-1 font-medium underline hover:no-underline"
                        >
                            Reenviar correo de verificación
                        </Link>
                        <p v-if="status === 'verification-link-sent'" class="mt-2 font-medium text-emerald-600">
                            Se envió un nuevo enlace de verificación.
                        </p>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                            Guardar cambios
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

            <DeleteUser v-if="puedeEliminarCuenta" />
        </SettingsLayout>
    </AppLayout>
</template>
