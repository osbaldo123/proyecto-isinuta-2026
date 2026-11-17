<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Plus, Search, Wallet } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import CiIdentidadInput from '@/components/CiIdentidadInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const { canManage } = usePermissions();

type Pago = {
    id: number;
    numero_recibo: string;
    tipo_recibo?: string;
    proposito_label?: string | null;
    fecha_pago: string;
    afiliado: {
        id: number;
        codigo: string;
        nombre_completo: string;
        ci: string;
        numero_lote?: string | null;
        otb?: string | null;
    };
    monto_total: number;
    metodo_pago: string;
    estado: string;
    cobrador: string | null;
};

type PaginatedPagos = {
    data: Pago[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
};

const props = defineProps<{
    modo: 'gestion' | 'consulta';
    pagos: PaginatedPagos;
    filtros: {
        q: string;
        fecha_desde?: string;
        fecha_hasta?: string;
        concepto?: string;
        afiliado_id?: number | null;
    };
    conceptos: Array<{ value: string; label: string }>;
        afiliado: {
        id: number;
        lote_id?: number | null;
        codigo?: string;
        nombre_completo: string;
        ci: string;
        numero_lote?: string | null;
        otb?: string | null;
        estado: string;
        total_multas_pendientes: number;
        lotes?: Array<{ id: number; numero_lote: string; otb: string | null }>;
    } | null;
    mensaje: string | null;
    busqueda: { ci: string };
    consultaVinculada: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Recibos', href: '/pagos' }],
    }),
});

const busquedaGestion = ref(props.filtros.q ?? '');
const fechaDesde = ref(props.filtros.fecha_desde ?? '');
const fechaHasta = ref(props.filtros.fecha_hasta ?? '');
const concepto = ref(props.filtros.concepto ?? '');
const ci = ref(props.busqueda.ci ?? '');

const buscarGestion = () => {
    router.get(
        '/pagos',
        queryListado(),
        { preserveState: true, preserveScroll: true, replace: true, only: ['pagos', 'filtros', 'afiliado', 'mensaje'] },
    );
};

let debounceGestion: ReturnType<typeof setTimeout> | null = null;

watch(busquedaGestion, () => {
    if (debounceGestion) {
        clearTimeout(debounceGestion);
    }

    debounceGestion = setTimeout(() => buscarGestion(), 300);
});

watch([fechaDesde, fechaHasta, concepto], () => buscarGestion());

const buscarConsulta = () => {
    router.get(
        '/pagos',
        {
            ci: ci.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const queryListado = (): Record<string, string> => {
    const params: Record<string, string> = {};

    if (props.modo === 'consulta') {
        if (ci.value || props.busqueda.ci) {
            params.ci = (ci.value || props.busqueda.ci).trim();
        }

        return params;
    }

    const q = busquedaGestion.value.trim();
    if (q) {
        params.q = q;
    }
    if (fechaDesde.value) {
        params.fecha_desde = fechaDesde.value;
    }
    if (fechaHasta.value) {
        params.fecha_hasta = fechaHasta.value;
    }
    if (concepto.value) {
        params.concepto = concepto.value;
    }
    if (props.filtros.afiliado_id) {
        params.afiliado_id = String(props.filtros.afiliado_id);
    }

    return params;
};

const withQuery = (path: string, params: Record<string, string> = queryListado()): string => {
    const qs = new URLSearchParams(params).toString();

    return qs ? `${path}?${qs}` : path;
};

/** Lotes con estado de cuenta: el lote filtrado (gestión) o los lotes del afiliado consultado. */
const lotesEstadoCuenta = (): Array<{ id: number; etiqueta: string }> => {
    const afiliado = props.afiliado;

    if (!afiliado) {
        return [];
    }

    if (afiliado.lotes && afiliado.lotes.length > 0) {
        return afiliado.lotes.map((lote) => ({
            id: lote.id,
            etiqueta: `Lote ${lote.numero_lote || '—'}${lote.otb ? ` · ${lote.otb}` : ''}`,
        }));
    }

    return afiliado.lote_id ? [{ id: afiliado.lote_id, etiqueta: `Lote ${afiliado.numero_lote || '—'}` }] : [];
};

const formatBs = (n: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);

const metodoLabel = (metodo: string) => {
    const map: Record<string, string> = {
        efectivo: 'Efectivo',
        qr: 'QR',
    };

    return map[metodo] ?? metodo;
};

const showPagoUrl = (pagoId: number) => withQuery(`/pagos/${pagoId}`);
</script>

<template>
    <Head title="Recibos" />

    <div :class="pageShellClass">
        <div class="flex min-w-0 items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                <Wallet class="size-5 text-white" />
            </div>
            <div class="min-w-0">
                <h1 :class="pageTitleClass">Recibos</h1>
                <p v-if="modo === 'consulta'" class="text-sm text-slate-600 dark:text-slate-400">
                    Consulta privada por CI. No se muestran datos de otros afiliados.
                </p>
                <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                    Listado de recibos emitidos al registrar un cobro.
                </p>
            </div>
        </div>

        <form
            v-if="modo === 'gestion'"
            class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"
            @submit.prevent="buscarGestion"
        >
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-xs sm:w-72">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 z-10 size-4 shrink-0 -translate-y-1/2 text-slate-400 sm:left-3" />
                    <input
                        v-model="busquedaGestion"
                        type="search"
                        placeholder="Recibo, CI, nombre o apellido..."
                        class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-8 pr-2 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none sm:pl-10 sm:pr-3 [color-scheme:light] dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:[color-scheme:dark]"
                    />
                </div>
                <input
                    v-model="fechaDesde"
                    type="date"
                    class="w-full max-w-[11rem] rounded-lg border border-slate-300 bg-white px-2 py-2.5 text-sm text-slate-900 sm:w-40 sm:px-3 [color-scheme:light] dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:[color-scheme:dark]"
                    title="Fecha desde"
                />
                <input
                    v-model="fechaHasta"
                    type="date"
                    class="w-full max-w-[11rem] rounded-lg border border-slate-300 bg-white px-2 py-2.5 text-sm text-slate-900 sm:w-40 sm:px-3 [color-scheme:light] dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:[color-scheme:dark]"
                    title="Fecha hasta"
                />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-model="concepto"
                    class="w-full max-w-md rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 sm:w-80 [color-scheme:light] dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:[color-scheme:dark]"
                >
                    <option value="">Todos los conceptos</option>
                    <option v-for="c in conceptos" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
                <Link
                    v-if="canManage('pagos.gestionar')"
                    href="/pagos/registrar/nuevo"
                    class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600 sm:w-auto"
                >
                    <Plus class="size-4 shrink-0" />
                    Registrar pago
                </Link>
            </div>
        </form>

        <Link
            v-if="modo !== 'gestion' && canManage('pagos.gestionar')"
            href="/pagos/registrar/nuevo"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600 sm:w-auto"
        >
            <Plus class="size-4 shrink-0" />
            Registrar pago
        </Link>

        <form
            v-if="modo === 'consulta' && !consultaVinculada"
            class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
            @submit.prevent="buscarConsulta"
        >
            <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-50">Buscar por CI</h2>
            <CiIdentidadInput v-model:ci="ci" />
            <div class="mt-4 flex justify-end">
                <button
                    type="submit"
                    class="flex items-center gap-2 rounded-lg bg-cyan-500 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-600"
                >
                    <Search class="size-4" />
                    Consultar pagos
                </button>
            </div>
        </form>

        <p
            v-if="mensaje"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
            {{ mensaje }}
        </p>

        <div
            v-if="afiliado"
            class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-900/50 dark:bg-cyan-950/30"
        >
            <p class="font-semibold text-slate-900 dark:text-slate-50">{{ afiliado.nombre_completo }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                CI {{ afiliado.ci }}
                <span v-if="afiliado.numero_lote"> · Lote {{ afiliado.numero_lote }}</span>
                <span v-if="afiliado.otb"> · OTB {{ afiliado.otb }}</span>
            </p>
            <p class="mt-1 text-xs text-cyan-700 dark:text-cyan-300">
                Recibos y cobros de este lote únicamente (no se mezclan con otros lotes del mismo afiliado).
            </p>
            <div v-if="lotesEstadoCuenta().length > 0" class="mt-3 flex flex-wrap gap-2">
                <Link
                    v-for="lote in lotesEstadoCuenta()"
                    :key="lote.id"
                    :href="`/pagos/estado-cuenta/${lote.id}`"
                    class="inline-flex min-h-9 items-center rounded-lg border border-cyan-300 bg-white px-3 text-xs font-semibold text-cyan-800 transition hover:bg-cyan-100 dark:border-cyan-700 dark:bg-slate-900 dark:text-cyan-200 dark:hover:bg-cyan-950/50"
                >
                    Estado de cuenta · {{ lote.etiqueta }}
                </Link>
            </div>
        </div>

        <div
            v-if="modo === 'consulta' && !afiliado && !mensaje"
            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/50"
        >
            Ingrese el CI del afiliado para consultar sus pagos.
        </div>

        <div
            v-if="modo === 'gestion' || afiliado"
            class="min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        >
            <table v-tabla-responsiva class="w-full md:min-w-[720px]">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Recibo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Fecha</th>
                        <th v-if="modo === 'gestion'" class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Afiliado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-slate-500">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Método</th>
                        <th v-if="modo === 'gestion'" class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Cobrador</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-if="pagos.data.length === 0">
                        <td :colspan="modo === 'gestion' ? 9 : 7" class="px-4 py-8 text-center text-sm text-slate-500">
                            {{
                                modo === 'gestion' && filtros.q
                                    ? `No hay pagos que coincidan con “${filtros.q}”.`
                                    : 'No hay pagos registrados para esta consulta.'
                            }}
                        </td>
                    </tr>
                    <tr
                        v-for="pago in pagos.data"
                        :key="pago.id"
                        class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800"
                        role="link"
                        tabindex="0"
                        @click="router.visit(showPagoUrl(pago.id))"
                        @keydown.enter="router.visit(showPagoUrl(pago.id))"
                    >
                        <td class="px-4 py-3 text-sm font-mono text-slate-900 dark:text-slate-50">{{ pago.numero_recibo }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="
                                    pago.tipo_recibo === 'caja'
                                        ? 'bg-amber-100 text-amber-900 dark:bg-amber-900/50 dark:text-amber-100'
                                        : 'bg-cyan-100 text-cyan-900 dark:bg-cyan-900/50 dark:text-cyan-100'
                                "
                            >
                                {{ pago.tipo_recibo === 'caja' ? 'Caja' : 'Ingreso' }}
                            </span>
                            <p class="mt-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                                {{ pago.proposito_label || '—' }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ pago.fecha_pago }}</td>
                        <td v-if="modo === 'gestion'" class="px-4 py-3">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-50">{{ pago.afiliado.nombre_completo }}</p>
                            <p class="text-xs text-slate-500">
                                CI {{ pago.afiliado.ci }}
                                · Lote {{ pago.afiliado.numero_lote || '—' }}
                                <span v-if="pago.afiliado.otb"> · {{ pago.afiliado.otb }}</span>
                            </p>
                        </td>
                        <td
                            class="px-4 py-3 text-right text-sm font-medium"
                            :class="pago.estado === 'anulado' ? 'text-slate-400 line-through' : 'text-slate-900 dark:text-slate-50'"
                        >
                            {{ formatBs(pago.monto_total) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ metodoLabel(pago.metodo_pago) }}</td>
                        <td v-if="modo === 'gestion'" class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ pago.cobrador ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-1 text-xs font-medium"
                                :class="
                                    pago.estado === 'emitido'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                        : 'bg-orange-100 text-orange-800 dark:bg-orange-400/15 dark:text-orange-300'
                                "
                            >
                                {{ pago.estado === 'emitido' ? 'Emitido' : 'Anulado' }}
                            </span>
                        </td>
                        <td class="px-4 py-3" @click.stop>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="showPagoUrl(pago.id)"
                                    :aria-label="`Ver recibo ${pago.numero_recibo}`"
                                    class="rounded p-1 text-slate-600 hover:bg-slate-100 hover:text-cyan-600 dark:text-slate-400 dark:hover:bg-slate-800"
                                >
                                    <Eye class="size-4" />
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="pagos.last_page > 1" class="flex flex-wrap items-center gap-1">
            <template v-for="link in pagos.links" :key="link.label">
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
