<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Droplets, Pencil, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const { can, canManage } = usePermissions();

type Servicio = {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    tipo_label?: string;
    tarifa_mensual: number;
    tarifas_por_categoria: Array<{ categoria_id: number; categoria: string; monto: number; lotes: number }>;
    activo: boolean;
    lotes: number;
    es_base?: boolean;
};

type Paginated = {
    data: Servicio[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    last_page: number;
    total: number;
};

const props = defineProps<{
    servicios: Paginated;
    tipos?: Array<{ value: string; label: string }>;
    filtros: { q: string; tipo?: string; estado?: string };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Servicios', href: '/servicios' }],
    }),
});

const busqueda = ref(props.filtros?.q ?? '');
const estadoFiltro = ref(props.filtros?.estado ?? '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const buscar = (replace = true) => {
    router.get(
        '/servicios',
        {
            q: busqueda.value || undefined,
            estado: estadoFiltro.value || undefined,
        },
        { preserveState: true, replace, preserveScroll: true },
    );
};

watch(busqueda, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => buscar(), 300);
});

watch(estadoFiltro, () => buscar());

const formatBs = (n: number) => {
    try {
        return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);
    } catch {
        return `Bs ${Number(n).toFixed(2)}`;
    }
};

/** Una fila por servicio; categorías/tarifas se dividen dentro del formulario. */
const filas = computed(() =>
    (props.servicios?.data ?? []).map((servicio) => {
        const tarifas = servicio.tarifas_por_categoria ?? [];
        const categorias = tarifas.map((t) => t.categoria).filter(Boolean);
        const montos = tarifas.map((t) => formatBs(Number(t.monto) || 0));
        const lotes = tarifas.map((t) => String(t.lotes ?? 0));

        return {
            servicio,
            categoriaTexto: categorias.length > 0 ? categorias.join(' / ') : '—',
            tarifaTexto: montos.length > 0 ? montos.join(' / ') : formatBs(servicio.tarifa_mensual),
            lotesTexto: lotes.length > 1 ? lotes.join(' / ') : String(servicio.lotes ?? lotes[0] ?? 0),
            editHref: `/servicios/${servicio.id}/edit`,
        };
    }),
);

const verDetalle = (servicio: Servicio) => {
    router.visit(`/servicios/${servicio.id}`);
};
</script>

<template>
    <Head title="Servicios" />

    <div :class="pageShellClass">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                <Droplets class="size-5 text-white" />
            </div>
            <div>
                <h1 :class="pageTitleClass">Servicios</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Tarifas mensuales y asignación por lote.
                </p>
            </div>
        </div>

        <form class="flex w-full flex-col gap-2 lg:max-w-5xl sm:flex-row sm:items-center" @submit.prevent="buscar(false)">
            <div class="relative min-w-0 flex-1">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar por código o nombre..."
                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-11 pr-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
                />
            </div>
            <select
                v-model="estadoFiltro"
                class="w-full shrink-0 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 sm:w-44 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
                <option value="">Todos los estados</option>
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
            <Link
                v-if="canManage('servicios.gestionar')"
                href="/servicios/create"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
            >
                <Plus class="size-4" />
                Nuevo servicio
            </Link>
        </form>

        <div class="min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table v-tabla-responsiva class="w-full md:min-w-[760px]">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Servicio</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Categoría</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Tarifa/mes</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Lotes</th>
                        <th class="w-[12%] whitespace-nowrap px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Estado</th>
                        <th class="w-[14%] whitespace-nowrap px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-if="filas.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No hay servicios registrados.</td>
                    </tr>
                    <tr
                        v-for="fila in filas"
                        :key="fila.servicio.id"
                        class="cursor-pointer hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                        @click="verDetalle(fila.servicio)"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900 dark:text-slate-50">{{ fila.servicio.nombre }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ fila.categoriaTexto }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-center text-sm font-semibold text-cyan-700 dark:text-cyan-300">
                            {{ fila.tarifaTexto }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-slate-700 dark:text-slate-200">{{ fila.lotesTexto }}</td>
                        <td class="px-4 py-3 text-center text-sm">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    fila.servicio.activo
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                {{ fila.servicio.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3" @click.stop>
                            <div v-if="can('servicios.gestionar')" class="flex items-center justify-center gap-2">
                                <Link
                                    :href="fila.editHref"
                                    title="Editar"
                                    aria-label="Editar"
                                    class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    <Pencil class="size-3.5" />
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="servicios.last_page > 1" class="flex flex-wrap gap-1">
            <template v-for="link in servicios.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md px-3 py-1 text-sm"
                    :class="link.active ? 'bg-cyan-500 text-white' : 'border border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'"
                    preserve-scroll
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>
    </div>
</template>
