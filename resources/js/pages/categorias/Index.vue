<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Layers, Pencil, Plus, Power, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const { canManage } = usePermissions();
const { confirm: confirmar } = useConfirmDialog();

type Categoria = {
    id: number;
    nombre: string;
    descripcion: string;
    lotes: number;
    estado: string;
};

const props = defineProps<{
    categorias: Categoria[];
    filtros?: { q?: string };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Servicios', href: '/servicios' },
            { title: 'Categorías', href: '/categorias' },
        ],
    }),
});

const busqueda = ref(props.filtros?.q ?? '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const buscar = (replace = true) => {
    router.get(
        '/categorias',
        { q: busqueda.value || undefined },
        { preserveState: true, replace, preserveScroll: true },
    );
};

watch(busqueda, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => buscar(), 300);
});

const cambiarEstado = async (categoria: Categoria) => {
    const activar = categoria.estado !== 'Activa';
    const ok = await confirmar({
        title: activar ? 'Activar categoría' : 'Desactivar categoría',
        message: activar
            ? `¿Activar la categoría "${categoria.nombre}"?`
            : `¿Desactivar la categoría "${categoria.nombre}"?`,
        confirmLabel: activar ? 'Activar' : 'Desactivar',
        variant: activar ? 'default' : 'danger',
    });
    if (!ok) return;

    router.patch(`/categorias/${categoria.id}/estado`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Categorías" />

    <div :class="pageShellClass">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <Layers class="size-5 text-white" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">Categorías</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Tipos de propiedad de los lotes
                    </p>
                </div>
            </div>
        </div>

        <form class="flex w-full flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="buscar(false)">
            <div class="relative w-full min-w-0 max-w-sm">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar por nombre, descripción o estado..."
                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-11 pr-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
                />
            </div>
            <Link
                v-if="canManage('categorias.gestionar')"
                href="/categorias/create"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
            >
                <Plus class="size-4" />
                Nueva categoría
            </Link>
        </form>

        <div class="min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table v-tabla-responsiva class="w-full md:min-w-[560px]">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Categoría</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Lotes</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-if="categorias.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                            No hay categorías registradas.
                        </td>
                    </tr>
                    <tr
                        v-for="categoria in categorias"
                        :key="categoria.id"
                        class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900 dark:text-slate-50">{{ categoria.nombre }}</p>
                            <p v-if="categoria.descripcion" class="mt-0.5 max-w-md text-xs text-slate-500">
                                {{ categoria.descripcion }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-slate-700 dark:text-slate-200">
                            {{ categoria.lotes }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    categoria.estado === 'Activa'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                {{ categoria.estado }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <Link
                                    v-if="canManage('categorias.gestionar')"
                                    :href="`/categorias/${categoria.id}/edit`"
                                    title="Editar"
                                    aria-label="Editar"
                                    class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    <Pencil class="size-3.5" />
                                </Link>
                                <button
                                    v-if="canManage('categorias.gestionar')"
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-lg border"
                                    :class="
                                        categoria.estado === 'Activa'
                                            ? 'border-amber-200 text-amber-800 hover:bg-amber-50 dark:border-amber-900/50 dark:text-amber-300 dark:hover:bg-amber-950/30'
                                            : 'border-emerald-200 text-emerald-800 hover:bg-emerald-50 dark:border-emerald-900/50 dark:text-emerald-300 dark:hover:bg-emerald-950/30'
                                    "
                                    :title="categoria.estado === 'Activa' ? 'Desactivar' : 'Activar'"
                                    :aria-label="categoria.estado === 'Activa' ? 'Desactivar' : 'Activar'"
                                    @click="cambiarEstado(categoria)"
                                >
                                    <Power class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
