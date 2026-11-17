<script setup lang="ts">
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Power, Shield, Trash2, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

type Permiso = {
    id: number;
    nombre: string;
    descripcion: string;
    grupo: string;
};

type Usuario = {
    id: number;
    name: string;
    email: string;
    activo: boolean;
};

const props = defineProps<{
    role: {
        id: number;
        nombre: string;
        nombre_label: string;
        descripcion: string | null;
        usuarios_count: number;
        activo?: boolean;
        es_sistema: boolean;
        protegido?: boolean;
        protegido_eliminacion: boolean;
        permisos: Permiso[];
    };
    usuarios: Usuario[];
    puede_editar: boolean;
    puede_dar_baja?: boolean;
    puede_eliminar: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Roles', href: '/roles' },
            { title: 'Detalle', href: '#' },
        ],
    }),
});

const { confirm: confirmar } = useConfirmDialog();

const permisosPorGrupo = computed(() => {
    const groups: Record<string, Permiso[]> = {};

    props.role.permisos.forEach((perm) => {
        const grupo = perm.grupo || 'Otros';

        if (!groups[grupo]) {
            groups[grupo] = [];
        }

        groups[grupo].push(perm);
    });

    return groups;
});

const totalPermisos = computed(() => props.role.permisos.length);
const rolActivo = computed(() => props.role.activo !== false);

const handleToggleEstado = async () => {
    const darDeBaja = rolActivo.value;
    const ok = await confirmar({
        title: darDeBaja ? 'Dar de baja rol' : 'Reactivar rol',
        message: darDeBaja
            ? `¿Dar de baja el rol "${props.role.nombre_label}"? Dejará de poder asignarse a usuarios nuevos.`
            : `¿Reactivar el rol "${props.role.nombre_label}"?`,
        confirmLabel: darDeBaja ? 'Dar de baja' : 'Reactivar',
        variant: darDeBaja ? 'warning' : 'default',
    });

    if (!ok) {
        return;
    }

    router.patch(`/roles/${props.role.id}/toggle-estado`);
};

const handleDelete = async () => {
    const ok = await confirmar({
        title: 'Eliminar rol',
        message: `¿Eliminar el rol "${props.role.nombre_label}"? Esta acción no se puede deshacer.`,
        confirmLabel: 'Eliminar',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.delete(`/roles/${props.role.id}`);
};
</script>

<template>
    <Head :title="`Rol — ${role.nombre_label}`" />

    <div :class="[pageShellClass, 'mx-auto max-w-5xl']">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link href="/roles" class="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <ArrowLeft class="size-5 text-slate-600 dark:text-slate-400" />
                </Link>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-500/10 text-cyan-500">
                    <Shield class="size-5" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 :class="pageTitleClass">{{ role.nombre_label }}</h1>
                        <span
                            v-if="role.es_sistema"
                            class="rounded-md bg-cyan-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-cyan-600 dark:text-cyan-400"
                        >
                            Sistema
                        </span>
                        <span
                            class="rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                            :class="
                                rolActivo
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-red-500/10 text-red-700 dark:text-red-300'
                            "
                        >
                            {{ rolActivo ? 'Activo' : 'Baja' }}
                        </span>
                    </div>
                    <p class="font-mono text-sm text-slate-500 dark:text-slate-400">{{ role.nombre }}</p>
                </div>
            </div>

            <div v-if="puede_editar || puede_dar_baja || puede_eliminar" class="flex flex-wrap items-center gap-2">
                <Link
                    v-if="puede_editar"
                    :href="`/roles/${role.id}/edit`"
                    class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-600"
                >
                    <Pencil class="size-4" />
                    Editar
                </Link>
                <button
                    v-if="puede_dar_baja"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-2.5 text-sm font-semibold text-amber-700 transition hover:bg-amber-500/10 dark:text-amber-300"
                    @click="handleToggleEstado"
                >
                    <Power class="size-4" />
                    {{ rolActivo ? 'Dar de baja' : 'Reactivar' }}
                </button>
                <button
                    v-if="puede_eliminar"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/5 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                    @click="handleDelete"
                >
                    <Trash2 class="size-4" />
                    Eliminar
                </button>
            </div>
        </div>

        <div
            v-if="role.protegido_eliminacion"
            class="rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm text-amber-800 dark:text-amber-200"
        >
            Este rol del sistema no se elimina. Puede editarlo o darlo de baja.
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Usuarios asignados</p>
                <p class="mt-2 text-3xl font-bold text-cyan-500">{{ role.usuarios_count }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Permisos</p>
                <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-slate-50">{{ totalPermisos }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tipo</p>
                <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-50">
                    {{ role.es_sistema ? 'Rol del sistema' : 'Rol personalizado' }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-900 dark:text-slate-50">Descripción</h2>
            <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                {{ role.descripcion || 'Sin descripción registrada.' }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-sm font-semibold text-slate-900 dark:text-slate-50">Funciones y permisos</h2>

            <div v-if="totalPermisos === 0" class="text-sm text-slate-400">Este rol no tiene permisos asignados.</div>

            <div v-else class="space-y-5">
                <div
                    v-for="(permisos, grupo) in permisosPorGrupo"
                    :key="grupo"
                    class="border-b border-slate-100 pb-5 last:border-0 last:pb-0 dark:border-slate-800"
                >
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ grupo }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="perm in permisos"
                            :key="perm.id"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ perm.descripcion || perm.nombre }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center gap-2">
                <Users class="size-4 text-slate-400" />
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Usuarios con este rol</h2>
            </div>

            <div v-if="usuarios.length === 0" class="text-sm text-slate-400">Ningún usuario tiene asignado este rol.</div>

            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <li
                    v-for="usuario in usuarios"
                    :key="usuario.id"
                >
                    <Link
                        :href="`/usuarios/${usuario.id}`"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl px-2 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/70 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900 dark:text-slate-50">{{ usuario.name }}</p>
                            <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ usuario.email }}</p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="
                                usuario.activo
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                    : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                            "
                        >
                            {{ usuario.activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
