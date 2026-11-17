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

const props = defineProps<{
    role: {
        id: number;
        nombre: string;
        nombre_label?: string;
        descripcion: string;
        es_sistema?: boolean;
    };
    permissions: PermissionItem[];
    role_permissions: number[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Roles', href: '/roles' },
            { title: 'Editar rol', href: '' },
        ],
    }),
});

const form = useForm({
    nombre: props.role.nombre,
    descripcion: props.role.descripcion ?? '',
    permissions: [...props.role_permissions] as number[],
});

const submit = () => {
    form.put(`/roles/${props.role.id}`);
};
</script>

<template>
    <Head title="Editar Rol" />

    <FormPageShell title="Editar rol" :back-href="`/roles/${role.id}`" max-width="6xl">
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
                                    :readonly="role.es_sistema"
                                    :required="!role.es_sistema"
                                />
                                <p v-if="role.es_sistema" class="mt-1.5 text-xs leading-snug text-slate-500">
                                    El identificador de roles del sistema no se puede cambiar.
                                </p>
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
                            Actualiza la descripción y los permisos asignados a este rol.
                        </p>
                        <div class="flex flex-col gap-2">
                            <Link :href="`/roles/${role.id}`" :class="[formSecondaryButtonClass, 'lg:w-full']">Cancelar</Link>
                            <button type="submit" :disabled="form.processing" :class="[formPrimaryButtonClass, 'lg:w-full']">
                                <Save class="size-4 shrink-0" />
                                Actualizar rol
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
