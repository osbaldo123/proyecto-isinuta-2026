<script setup lang="ts">
import { computed } from 'vue';

export type PermissionItem = {
    id: number;
    nombre: string;
    descripcion: string;
    grupo: string;
};

const props = defineProps<{
    permissions: PermissionItem[];
    modelValue: number[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const GROUP_LABELS: Record<string, string> = {
    panel: 'Panel',
    afiliados: 'Afiliados',
    pagos: 'Pagos',
    reportes: 'Reportes',
    usuarios: 'Usuarios',
    multas: 'Multas',
    otb: 'OTB',
    categorias: 'Categorías',
    afiliaciones: 'Tipos de afiliación',
    servicios: 'Servicios',
    trabajos: 'Trabajos',
    configuracion: 'Configuración de multas',
    roles: 'Roles',
};

const groupedPermissions = computed(() => {
    const groups: Record<string, PermissionItem[]> = {};

    props.permissions.forEach((perm) => {
        const grupo = perm.grupo || 'otros';

        if (!groups[grupo]) {
            groups[grupo] = [];
        }

        groups[grupo].push(perm);
    });

    return groups;
});

const tituloGrupo = (grupo: string) =>
    GROUP_LABELS[grupo] ?? grupo.charAt(0).toUpperCase() + grupo.slice(1);

const estaSeleccionado = (id: number) => props.modelValue.includes(id);

const grupoCompleto = (perms: PermissionItem[]) =>
    perms.length > 0 && perms.every((perm) => estaSeleccionado(perm.id));

const emitSelection = (ids: number[]) => {
    emit('update:modelValue', ids);
};

const togglePermiso = (id: number) => {
    if (estaSeleccionado(id)) {
        emitSelection(props.modelValue.filter((selectedId) => selectedId !== id));
        return;
    }

    emitSelection([...props.modelValue, id]);
};

const toggleGrupo = (perms: PermissionItem[]) => {
    const ids = perms.map((perm) => perm.id);
    const completo = grupoCompleto(perms);

    if (completo) {
        emitSelection(props.modelValue.filter((id) => !ids.includes(id)));
        return;
    }

    emitSelection([...new Set([...props.modelValue, ...ids])]);
};
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div
            v-for="(perms, grupo) in groupedPermissions"
            :key="grupo"
            class="flex min-w-0 flex-col rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 dark:border-slate-700 dark:bg-slate-800/40"
        >
            <div class="mb-3 flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                <h3 class="min-w-0 text-sm font-semibold text-slate-900 dark:text-slate-50">
                    {{ tituloGrupo(String(grupo)) }}
                </h3>
                <button
                    type="button"
                    class="shrink-0 text-xs font-medium text-cyan-600 hover:text-cyan-700 hover:underline dark:text-cyan-400 dark:hover:text-cyan-300"
                    @click="toggleGrupo(perms)"
                >
                    {{ grupoCompleto(perms) ? 'Quitar todo' : 'Seleccionar todo' }}
                </button>
            </div>

            <div class="flex flex-col gap-1.5">
                <label
                    v-for="perm in perms"
                    :key="perm.id"
                    class="flex cursor-pointer items-start gap-2.5 rounded-lg px-1.5 py-1.5 hover:bg-white/80 dark:hover:bg-slate-900/50"
                >
                    <input
                        type="checkbox"
                        class="mt-0.5 size-4 shrink-0 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                        :checked="estaSeleccionado(perm.id)"
                        @change="togglePermiso(perm.id)"
                    />
                    <span class="break-words text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                        {{ perm.descripcion || perm.nombre }}
                    </span>
                </label>
            </div>
        </div>
    </div>
</template>
