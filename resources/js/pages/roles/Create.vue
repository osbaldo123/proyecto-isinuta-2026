<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import FormPageShell from '@/components/FormPageShell.vue';
import RolePermissionsGrid, { type PermissionItem } from '@/components/RolePermissionsGrid.vue';
import {
    formCardBodyClass,
    formCardClass,
    formCardTitleClass,
    formInputClass,
    formPrimaryButtonClass,
    formSecondaryButtonClass,
} from '@/lib/formStyles';

defineProps<{
    permissions: PermissionItem[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Roles', href: '/roles' },
            { title: 'Crear Rol', href: '/roles/create' },
        ],
    }),
});

const form = useForm({
    nombre: '',
    descripcion: '',
    permissions: [] as number[],
});

const submit = () => {
    form.post('/roles');
};
</script>

<template>
    <Head title="Crear Rol" />

    <FormPageShell title="Crear nuevo rol" back-href="/roles" max-width="6xl">
        <form class="mx-auto w-full space-y-4" @submit.prevent="submit">
            <div class="grid items-start gap-4 lg:grid-cols-12 lg:gap-5">
                <div :class="[formCardClass, 'lg:col-span-8']">
                    <div :class="formCardBodyClass">
                        <h2 :class="formCardTitleClass">Datos básicos</h2>
                        <div class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                                <input
                                    v-model="form.nombre"
                                    type="text"
                                    :class="formInputClass"
                                    placeholder="Nombre del rol"
                                    required
                                />
                                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Descripción</label>
                                <textarea
                                    v-model="form.descripcion"
                                    rows="2"
                                    :class="[formInputClass, '!h-auto min-h-[2.75rem] resize-y']"
                                    placeholder="Descripción opcional"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div :class="[formCardClass, 'lg:col-span-4 lg:sticky lg:top-4']">
                    <div :class="formCardBodyClass">
                        <h2 :class="formCardTitleClass">Acciones</h2>
                        <p class="mb-4 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            Define el nombre del rol y marca los permisos que tendrá en el sistema.
                        </p>
                        <div class="flex flex-col gap-2">
                            <Link href="/roles" :class="[formSecondaryButtonClass, 'lg:w-full']">Cancelar</Link>
                            <button type="submit" :disabled="form.processing" :class="[formPrimaryButtonClass, 'lg:w-full']">
                                <Save class="size-4 shrink-0" />
                                Guardar rol
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div :class="formCardClass">
                <div :class="formCardBodyClass">
                    <h2 :class="formCardTitleClass">Permisos</h2>
                    <RolePermissionsGrid v-model="form.permissions" :permissions="permissions" />
                    <p v-if="form.errors.permissions" class="mt-2 text-xs text-red-600">{{ form.errors.permissions }}</p>
                </div>
            </div>
        </form>
    </FormPageShell>
</template>
