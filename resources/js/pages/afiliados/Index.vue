<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CreditCard, Pencil, Plus, Receipt, Search, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const { can, canManage } = usePermissions();

const celdaHover =
    'transition-colors group-hover/afiliado:bg-cyan-50/50 dark:group-hover/afiliado:bg-slate-800/80';

type Propiedad = {
    id: number;
    otb_id?: number | null;
    otb: string | null;
    numero_lote: string;
    direccion: string | null;
    telefono: string | null;
    estado: string;
    fecha_afiliacion: string;
};

type Afiliado = {
    id: number;
    nombre_completo: string;
    ci: string;
    telefono: string | null;
    propiedades: Propiedad[];
};

type PaginatedAfiliados = {
    data: Afiliado[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
};

type OtbItem = { id: number; nombre: string };

const props = defineProps<{
    afiliados: PaginatedAfiliados;
    otbs: OtbItem[];
    filtros: { q: string; estado?: string; sin_pago?: boolean; otb_id?: number | null };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Afiliados', href: '/afiliados' }],
    }),
});

const busqueda = ref(props.filtros.q ?? '');
const estadoFiltro = ref(props.filtros.estado ?? '');
const otbFiltro = ref(props.filtros.otb_id ? String(props.filtros.otb_id) : '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const buscar = (replace = true) => {
    router.get(
        '/afiliados',
        {
            q: busqueda.value || undefined,
            estado: estadoFiltro.value || undefined,
            otb_id: otbFiltro.value || undefined,
        },
        // Sin preserveState: los props del listado (incl. OTB) deben venir siempre frescos del servidor.
        { replace, preserveScroll: true },
    );
};

watch(busqueda, () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => buscar(), 300);
});

watch(estadoFiltro, () => buscar());
watch(otbFiltro, () => buscar());

const nombreOtb = (propiedad: Propiedad): string => {
    if (propiedad.otb && propiedad.otb.trim() !== '') {
        return propiedad.otb;
    }

    const desdeLista = props.otbs.find((item) => item.id === propiedad.otb_id);

    return desdeLista?.nombre || '—';
};

const estadoBadge = (estado: string) => {
    if (estado === 'activo') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    }

    if (estado === 'cortado') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
    }

    return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
};

const estadoLabel = (estado: string) => {
    const map: Record<string, string> = {
        activo: 'Activo',
        cortado: 'Cortado',
        baja: 'Baja',
    };

    return map[estado] ?? estado;
};

const verDetalle = (propiedadId: number) => {
    router.visit(`/afiliados/${propiedadId}`);
};
</script>

<template>
    <Head title="Afiliados" />

    <div :class="pageShellClass">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <Users class="size-5 text-white" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">Afiliados</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Gestión de afiliados de la asociación ISINUTA.
                    </p>
                </div>
            </div>

            <div
                class="flex min-w-[110px] flex-col items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/5 px-5 py-3 dark:border-cyan-500/40 dark:bg-cyan-500/10"
            >
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total</span>
                <span class="text-3xl font-bold leading-none text-cyan-500">{{ afiliados.total }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">afiliado(s)</span>
            </div>
        </div>

        <form class="flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="buscar(false)">
            <div class="relative mx-auto min-w-0 w-[min(100%,22rem)] sm:mx-0 sm:w-full sm:max-w-xl">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar por CI, nombre, lote, dirección o estado..."
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-11 pr-10 text-sm !text-slate-900 caret-slate-900 placeholder:!text-slate-400 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:!text-slate-50 dark:caret-slate-50 dark:placeholder:!text-slate-400 dark:[color-scheme:dark]"
                />
            </div>
            <select
                v-model="otbFiltro"
                class="w-full min-w-0 shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 sm:w-72 [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
            >
                <option value="">Todas las OTB</option>
                <option v-for="otb in otbs" :key="otb.id" :value="String(otb.id)">
                    {{ otb.nombre }}
                </option>
            </select>
            <select
                v-model="estadoFiltro"
                class="w-full shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 sm:w-44 [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
            >
                <option value="">Todos los estados</option>
                <option value="activo">Activos</option>
                <option value="cortado">Cortados</option>
                <option value="baja">Baja</option>
            </select>
            <Link
                v-if="canManage('afiliados.gestionar')"
                href="/afiliados/create"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
            >
                <Plus class="size-4" />
                Nuevo afiliado
            </Link>
        </form>

        <div class="min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table v-tabla-responsiva class="w-full md:min-w-[900px]">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">CI</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">OTB</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Lote</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Dirección</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Teléfono</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Afiliación</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody v-if="afiliados.data.length === 0">
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">
                            <p v-if="filtros.sin_pago">
                                No hay afiliados sin pago en el mes.
                            </p>
                            <p v-else-if="estadoFiltro === 'cortado'">
                                No hay afiliados cortados. Si corresponde, regístrelos o cambie el estado de un afiliado existente.
                            </p>
                            <p v-else-if="estadoFiltro === 'activo'">
                                No hay afiliados activos.
                            </p>
                            <p v-else>
                                No se encontraron afiliados.
                            </p>
                            <Link
                                v-if="canManage('afiliados.gestionar')"
                                href="/afiliados/create"
                                class="mt-3 inline-flex items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-600"
                            >
                                <Plus class="size-4" />
                                Registrar afiliado
                            </Link>
                        </td>
                    </tr>
                </tbody>
                <tbody
                    v-for="afiliado in afiliados.data"
                    :key="`${afiliado.ci}-${afiliado.propiedades[0]?.id ?? afiliado.id}`"
                    class="group/afiliado border-t border-slate-300 dark:border-slate-600"
                >
                    <tr
                        v-for="(propiedad, idx) in afiliado.propiedades"
                        :key="propiedad.id"
                        class="cursor-pointer border-b border-slate-200 last:border-b-0 dark:border-slate-700/80"
                        @click="verDetalle(propiedad.id)"
                    >
                            <td
                                v-if="idx === 0"
                                :rowspan="afiliado.propiedades.length"
                                :class="['border-r border-slate-100 px-4 py-3 align-middle dark:border-slate-800', celdaHover]"
                            >
                                <button
                                    type="button"
                                    class="cursor-pointer text-left text-sm font-medium whitespace-nowrap text-slate-900 dark:text-slate-50"
                                    @click.stop="verDetalle(propiedad.id)"
                                >
                                    {{ afiliado.nombre_completo }}
                                </button>
                            </td>
                            <td
                                v-if="idx === 0"
                                :rowspan="afiliado.propiedades.length"
                                :class="['border-r border-slate-100 px-4 py-3 align-middle text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400', celdaHover]"
                            >
                                {{ afiliado.ci }}
                            </td>
                            <td :class="['px-4 py-3 align-middle text-sm whitespace-nowrap text-slate-700 dark:text-slate-300', celdaHover]">
                                {{ nombreOtb(propiedad) }}
                            </td>
                            <td :class="['border-r border-slate-100 px-4 py-3 align-middle font-mono text-sm text-slate-900 dark:border-slate-800 dark:text-slate-50', celdaHover]">
                                {{ propiedad.numero_lote || '—' }}
                            </td>
                            <td :class="['px-4 py-3 align-middle text-sm text-slate-600 dark:text-slate-400', celdaHover]">
                                {{ propiedad.direccion || '—' }}
                            </td>
                            <td :class="['border-r border-slate-100 px-4 py-3 align-middle text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400', celdaHover]">
                                {{ propiedad.telefono || '—' }}
                            </td>
                            <td :class="['px-4 py-3 align-middle', celdaHover]">
                                <span
                                    class="inline-flex w-fit rounded-full px-2 py-1 text-xs font-medium"
                                    :class="estadoBadge(propiedad.estado)"
                                >
                                    {{ estadoLabel(propiedad.estado) }}
                                </span>
                            </td>
                            <td :class="['px-4 py-3 align-middle text-sm whitespace-nowrap text-slate-600 dark:text-slate-400', celdaHover]">
                                {{ propiedad.fecha_afiliacion }}
                            </td>
                            <td :class="['px-4 py-3 align-middle', celdaHover]" @click.stop>
                                <div class="flex items-center gap-1.5">
                                    <Link
                                        v-if="canManage('pagos.gestionar')"
                                        :href="`/pagos/registrar/nuevo?afiliado_id=${propiedad.id}`"
                                        title="Cobrar deuda de este lote"
                                        aria-label="Cobrar deuda de este lote"
                                        class="inline-flex size-9 items-center justify-center rounded-lg bg-cyan-500 text-white transition hover:bg-cyan-600"
                                    >
                                        <CreditCard class="size-4" />
                                    </Link>
                                    <Link
                                        v-if="can('pagos.ver')"
                                        :href="`/pagos?afiliado_id=${propiedad.id}`"
                                        title="Ver recibos de este lote"
                                        aria-label="Ver recibos de este lote"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-cyan-500/35 bg-cyan-500/15 text-cyan-700 transition hover:bg-cyan-500 hover:text-white dark:border-cyan-400/30 dark:bg-cyan-500/20 dark:text-cyan-300 dark:hover:bg-cyan-500 dark:hover:text-white"
                                    >
                                        <Receipt class="size-4" />
                                    </Link>
                                    <Link
                                        v-if="canManage('afiliados.gestionar')"
                                        :href="`/afiliados/${propiedad.id}/edit`"
                                        title="Editar afiliado"
                                        aria-label="Editar afiliado"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                </tbody>
            </table>
        </div>

        <div v-if="afiliados.last_page > 1" class="flex flex-wrap items-center gap-1">
            <template v-for="link in afiliados.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md px-3 py-1 text-sm"
                    :class="
                        link.active
                            ? 'bg-cyan-500 text-white'
                            : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'
                    "
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>
    </div>
</template>
