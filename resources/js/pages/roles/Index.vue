<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Power, Search, Shield, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const props = defineProps<{
    roles: Array<{
        id: number;
        nombre: string;
        nombre_label: string;
        descripcion: string | null;
        usuarios_count: number;
        activo: boolean;
        es_sistema: boolean;
        puede_editar: boolean;
        puede_dar_baja: boolean;
        puede_eliminar: boolean;
        funciones_count: number;
    }>;
    total: number;
    filtros?: { q?: string };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Roles', href: '/roles' }],
    }),
});

const { canManage } = usePermissions();
const { confirm: confirmar } = useConfirmDialog();

const busqueda = ref(props.filtros?.q ?? '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const buscar = (replace = true) => {
    router.get(
        '/roles',
        { q: busqueda.value || undefined },
        { preserveState: true, replace, preserveScroll: true },
    );
};

watch(busqueda, () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => buscar(), 300);
});

const canManageRoles = computed(() => canManage('roles.gestionar'));

const verDetalle = (id: number) => {
    router.visit(`/roles/${id}`);
};

const handleToggleEstado = async (role: (typeof props.roles)[number]) => {
    const darDeBaja = role.activo;
    const ok = await confirmar({
        title: darDeBaja ? 'Dar de baja rol' : 'Reactivar rol',
        message: darDeBaja
            ? `¿Dar de baja el rol "${role.nombre_label}"? Dejará de poder asignarse a usuarios nuevos.`
            : `¿Reactivar el rol "${role.nombre_label}"?`,
        confirmLabel: darDeBaja ? 'Dar de baja' : 'Reactivar',
        variant: darDeBaja ? 'warning' : 'default',
    });
    if (!ok) return;

    router.patch(`/roles/${role.id}/toggle-estado`, {}, { preserveScroll: true });
};

const handleDelete = async (id: number, nombre: string) => {
    const ok = await confirmar({
        title: 'Eliminar rol',
        message: `¿Eliminar el rol "${nombre}"? Esta acción no se puede deshacer.`,
        confirmLabel: 'Eliminar',
        variant: 'danger',
    });
    if (!ok) return;

    router.delete(`/roles/${id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Roles" />

    <div :class="pageShellClass">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <Shield class="size-5 text-white" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">Roles</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Define los roles del sistema y asigna permisos a cada perfil de usuario.
                    </p>
                </div>
            </div>

            <div
                class="flex min-w-[110px] flex-col items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/5 px-5 py-3 dark:border-cyan-500/40 dark:bg-cyan-500/10"
            >
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total</span>
                <span class="text-3xl font-bold leading-none text-cyan-500">{{ total }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">rol(es)</span>
            </div>
        </div>

        <form class="flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="buscar(false)">
            <div class="relative w-full max-w-sm min-w-0">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Nombre, descripción o función…"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-11 pr-10 text-sm !text-slate-900 caret-slate-900 placeholder:!text-slate-400 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:!text-slate-50 dark:caret-slate-50 dark:placeholder:!text-slate-400 dark:[color-scheme:dark]"
                />
            </div>
            <Link
                v-if="canManageRoles"
                href="/roles/create"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
            >
                <Plus class="size-4" />
                Nuevo rol
            </Link>
        </form>

        <div class="min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table v-tabla-responsiva class="w-full md:min-w-[980px]">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Descripción</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Permisos</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Usuarios</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Estado</th>
                        <th v-if="canManageRoles" class="px-4 py-3 text-right text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="roles.length === 0">
                        <td :colspan="canManageRoles ? 6 : 5" class="px-4 py-8 text-center text-sm text-slate-500">
                            No hay roles registrados.
                        </td>
                    </tr>
                    <tr
                        v-for="role in roles"
                        :key="role.id"
                        class="cursor-pointer transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
                        :class="role.activo ? '' : 'opacity-70'"
                        @click="verDetalle(role.id)"
                    >
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-900 dark:text-slate-50">{{ role.nombre_label }}</span>
                                <span
                                    v-if="role.es_sistema"
                                    class="rounded-md bg-cyan-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-cyan-600 dark:text-cyan-400"
                                >
                                    Sistema
                                </span>
                            </div>
                        </td>
                        <td class="max-w-xs px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                            <p class="line-clamp-2">{{ role.descripcion || 'Sin descripción' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-sm font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ role.funciones_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex min-w-8 items-center justify-center rounded-full bg-slate-100 px-2.5 py-0.5 text-sm font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ role.usuarios_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-lg px-3 py-1 text-xs font-semibold"
                                :class="
                                    role.activo
                                        ? 'bg-emerald-500 text-white'
                                        : 'bg-red-500 text-white'
                                "
                            >
                                {{ role.activo ? 'Activo' : 'Baja' }}
                            </span>
                        </td>
                        <td v-if="canManageRoles" class="px-4 py-3" @click.stop>
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    v-if="role.puede_editar"
                                    :href="`/roles/${role.id}/edit`"
                                    title="Editar rol"
                                    class="rounded-lg p-2 text-cyan-600 transition hover:bg-cyan-50 hover:text-cyan-700 dark:text-cyan-400 dark:hover:bg-slate-800"
                                >
                                    <Pencil class="size-4" />
                                </Link>
                                <button
                                    v-if="role.puede_dar_baja"
                                    type="button"
                                    :title="role.activo ? 'Dar de baja' : 'Reactivar rol'"
                                    class="rounded-lg p-2 text-amber-600 transition hover:bg-amber-50 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-950/30"
                                    @click="handleToggleEstado(role)"
                                >
                                    <Power class="size-4" />
                                </button>
                                <button
                                    v-if="role.puede_eliminar"
                                    type="button"
                                    title="Eliminar rol"
                                    class="rounded-lg p-2 text-red-500 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                    @click="handleDelete(role.id, role.nombre_label)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
