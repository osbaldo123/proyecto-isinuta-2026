<script setup lang="ts">
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, UserRound } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { create, index } from '@/routes/usuarios';

type Usuario = {
    id: number;
    nombre: string;
    email: string;
    telefono_completo: string;
    rol: string;
    rol_id?: number | null;
    rol_label: string;
    estado: string;
    ingreso: string;
    es_institucional?: boolean;
};

type PaginatedUsuarios = {
    data: Usuario[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
};

const props = defineProps<{
    usuarios: PaginatedUsuarios;
    total: number;
    filtros: { q: string };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Usuarios de consulta', href: '/usuarios' }],
    }),
});

const busqueda = ref(props.filtros?.q ?? '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const buscar = (replace = true) => {
    router.get(
        index.url(),
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

const verDetalle = (id: number) => {
    router.visit(`/usuarios/${id}`);
};
</script>

<template>
    <Head title="Usuarios" />

    <div :class="pageShellClass">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 text-white shadow-sm">
                    <UserRound class="size-5" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">Usuarios</h1>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                        Gestión de cuentas: Presidente, Secretaria y usuarios de consulta.
                    </p>
                </div>
            </div>

            <div
                class="flex min-w-[110px] flex-col items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/5 px-5 py-3 dark:border-cyan-500/40 dark:bg-cyan-500/10"
            >
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total</span>
                <span class="text-3xl font-bold leading-none text-cyan-500">{{ total }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">usuario(s)</span>
            </div>
        </div>

        <form class="flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="buscar(false)">
            <div class="relative min-w-0 flex-1 sm:max-w-xl">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar por nombre, CI, correo, rol o estado..."
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-11 pr-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
                />
            </div>
            <Link
                :href="create.url()"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
            >
                <Plus class="size-4" />
                Nuevo usuario
            </Link>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table v-tabla-responsiva class="w-full md:min-w-[800px]">
                    <thead class="bg-slate-50 dark:bg-slate-800/80">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Correo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Rol</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Teléfono</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Ingreso</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 w-px whitespace-nowrap">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="usuarios.data.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">
                                No hay usuarios registrados.
                            </td>
                        </tr>
                        <tr
                            v-for="usuario in usuarios.data"
                            :key="usuario.id"
                            class="cursor-pointer transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
                            role="link"
                            tabindex="0"
                            @click="verDetalle(usuario.id)"
                            @keydown.enter="verDetalle(usuario.id)"
                        >
                            <td class="px-5 py-4 font-semibold whitespace-nowrap text-slate-900 dark:text-slate-50">
                                {{ usuario.nombre }}
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400">{{ usuario.email }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex max-w-none whitespace-nowrap rounded-lg bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ usuario.rol_label }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-400">
                                {{ usuario.telefono_completo || '—' }}
                            </td>
                            <td class="px-5 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-400">{{ usuario.ingreso || '—' }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-lg px-3 py-1 text-xs font-semibold"
                                    :class="
                                        usuario.estado === 'Activo'
                                            ? 'bg-emerald-500 text-white'
                                            : 'bg-red-500 text-white'
                                    "
                                >
                                    {{ usuario.estado }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="usuarios.last_page > 1" class="flex flex-wrap items-center gap-1">
            <template v-for="link in usuarios.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md px-3 py-1 text-sm"
                    :class="
                        link.active
                            ? 'bg-cyan-500 text-white'
                            : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'
                    "
                    preserve-scroll
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>
    </div>
</template>
