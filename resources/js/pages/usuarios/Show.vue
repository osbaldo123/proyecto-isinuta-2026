<script setup lang="ts">
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, IdCard, KeyRound, Mail, Pencil, Phone, Power, Shield, UserRound } from 'lucide-vue-next';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { edit, index, toggleEstado } from '@/routes/usuarios';

const props = defineProps<{
    usuario: {
        id: number;
        nombre: string;
        email: string;
        ci: string | null;
        telefono_completo: string;
        rol: string;
        rol_id: number | null;
        rol_label: string;
        estado: string;
        activo: boolean;
        ingreso: string;
        fecha_final?: string | null;
        es_institucional: boolean;
    };
    puede_editar: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Usuarios', href: '/usuarios' },
            { title: 'Detalle', href: '#' },
        ],
    }),
});

const { confirm: confirmar } = useConfirmDialog();

const handleToggleEstado = async () => {
    const accion = props.usuario.activo ? 'desactivar' : 'activar';
    const ok = await confirmar({
        title: 'Cambiar estado',
        message: `¿Desea ${accion} el acceso de "${props.usuario.nombre}"?`,
        confirmLabel: props.usuario.activo ? 'Dar de baja' : 'Reactivar',
        variant: 'warning',
    });
    if (!ok) return;

    router.patch(toggleEstado.url(props.usuario.id));
};
</script>

<template>
    <Head :title="`Usuario — ${usuario.nombre}`" />

    <div :class="[pageShellClass, 'mx-auto max-w-4xl']">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link :href="index.url()" class="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <ArrowLeft class="size-5 text-slate-600 dark:text-slate-400" />
                </Link>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <UserRound class="size-5 text-white" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 :class="pageTitleClass">{{ usuario.nombre }}</h1>
                        <span
                            v-if="usuario.es_institucional"
                            class="rounded-md bg-cyan-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-cyan-600 dark:text-cyan-400"
                        >
                            Sistema
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Detalle del usuario</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Link
                    v-if="puede_editar"
                    :href="edit.url(usuario.id)"
                    class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-600"
                >
                    <Pencil class="size-4" />
                    Editar
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-2.5 text-sm font-semibold text-amber-700 transition hover:bg-amber-500/10 dark:text-amber-300"
                    @click="handleToggleEstado"
                >
                    <Power class="size-4" />
                    {{ usuario.activo ? 'Dar de baja' : 'Reactivar' }}
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Estado</p>
                <span
                    class="mt-3 inline-flex rounded-lg px-3 py-1 text-sm font-semibold"
                    :class="usuario.activo ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'"
                >
                    {{ usuario.estado }}
                </span>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ingreso</p>
                <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-50">{{ usuario.ingreso }}</p>
            </div>
            <div
                v-if="usuario.fecha_final"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Fecha final</p>
                <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-50">{{ usuario.fecha_final }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-sm font-semibold text-slate-900 dark:text-slate-50">Datos personales</h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div class="flex items-start gap-3">
                    <Mail class="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Correo</dt>
                        <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-50">{{ usuario.email }}</dd>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <IdCard class="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">CI</dt>
                        <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-50">
                            {{ usuario.ci || '—' }}
                        </dd>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <Phone class="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Teléfono</dt>
                        <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-50">
                            {{ usuario.telefono_completo || '—' }}
                        </dd>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <Shield class="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Rol</dt>
                        <dd class="mt-0.5">
                            <Link
                                v-if="usuario.rol_id"
                                :href="`/roles/${usuario.rol_id}`"
                                class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 text-sm font-medium text-slate-700 transition hover:bg-cyan-500/15 hover:text-cyan-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:text-cyan-300"
                            >
                                {{ usuario.rol_label }}
                            </Link>
                            <span v-else class="text-sm text-slate-900 dark:text-slate-50">{{ usuario.rol_label }}</span>
                        </dd>
                    </div>
                </div>
                <div class="flex items-start gap-3 sm:col-span-2">
                    <KeyRound class="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Contraseña</dt>
                        <dd class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
                            No se puede mostrar la contraseña guardada (está cifrada). Use
                            <Link :href="edit.url(usuario.id)" class="font-semibold text-cyan-600 hover:underline dark:text-cyan-400">
                                Editar
                            </Link>
                            para asignar una nueva; se verá al escribirla y una sola vez al guardar.
                        </dd>
                    </div>
                </div>
            </dl>
        </div>
    </div>
</template>
