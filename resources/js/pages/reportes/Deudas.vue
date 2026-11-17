<script setup lang="ts">
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, FileSpreadsheet, Landmark, Printer } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watchEffect } from 'vue';

type Linea = {
    fecha: string;
    socio: string;
    detalle: string;
    bolivianos: number;
    afiliado_id?: number | null;
    lote_id?: number | null;
    tipo?: string;
};

type Grupo = {
    concepto: string;
    label: string;
    lineas: Linea[];
    subtotal: number;
};

const props = withDefaults(
    defineProps<{
        reporte?: {
            grupos: Grupo[];
            total_general: number;
            totales: {
                cuotas: number;
                multas: number;
                afiliacion: number;
                lotes: number;
            };
            emitido: string;
        };
        totalGeneral?: number;
        totales?: {
            cuotas: number;
            multas: number;
            afiliacion: number;
            lotes: number;
        };
        emitido?: string;
        filtros?: {
            concepto?: string | null;
        };
        opciones?: {
            conceptos?: { value: string; label: string }[];
        };
    }>(),
    {
        reporte: () => ({
            grupos: [],
            total_general: 0,
            totales: { cuotas: 0, multas: 0, afiliacion: 0, lotes: 0 },
            emitido: '',
        }),
        totalGeneral: 0,
        totales: () => ({ cuotas: 0, multas: 0, afiliacion: 0, lotes: 0 }),
        emitido: '',
        filtros: () => ({ concepto: null }),
        opciones: () => ({ conceptos: [] }),
    },
);

defineOptions({
    inheritAttrs: false,
    layout: () => ({
        breadcrumbs: [
            { title: 'Reportes', href: '/reportes' },
            { title: 'Deudas', href: '/reportes/deudas' },
        ],
    }),
});

const grupos = computed(() => props.reporte?.grupos ?? []);
const totalGeneral = computed(() => props.totalGeneral ?? props.reporte?.total_general ?? 0);
const totales = computed(
    () =>
        props.totales ??
        props.reporte?.totales ?? {
            cuotas: 0,
            multas: 0,
            afiliacion: 0,
            lotes: 0,
        },
);
const emitido = computed(() => props.emitido || props.reporte?.emitido || '');
const conceptos = computed(() => props.opciones?.conceptos ?? []);
const concepto = ref(props.filtros?.concepto ?? '');

const formatBs = (monto: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(monto);

const formatNum = (monto: number) =>
    new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(monto);

const fechaEmisionLabel = computed(() => `Emitido: ${emitido.value}`);

const exportQuery = computed(() => {
    const params = new URLSearchParams();
    if (concepto.value) params.set('concepto', concepto.value);
    const q = params.toString();
    return q ? `?${q}` : '';
});

const aplicarFiltro = () => {
    const params: Record<string, string> = {};
    if (concepto.value) params.concepto = concepto.value;
    router.get('/reportes/deudas', params, { preserveState: true, preserveScroll: true });
};

watchEffect(() => {
    document.documentElement.style.setProperty('--deudas-print-emitido', `"${fechaEmisionLabel.value}"`);
});

onBeforeUnmount(() => {
    document.documentElement.style.removeProperty('--deudas-print-emitido');
});

const imprimir = () => window.print();
</script>

<template>
    <div :class="[pageShellClass, 'mx-auto max-w-6xl print:max-w-none print:gap-0 print:p-0']">
        <Head title="Reporte de deudas" />

        <div class="flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div class="flex items-center gap-3">
                <Link href="/reportes" class="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <ArrowLeft class="size-5 text-slate-600 dark:text-slate-400" />
                </Link>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500">
                    <Landmark class="size-5 text-white" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">Deudas</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Todo lo que los afiliados deben: cuotas, multas y afiliación a plazo.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Concepto</label>
                    <select
                        v-model="concepto"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50"
                        @change="aplicarFiltro"
                    >
                        <option value="">Todos los conceptos</option>
                        <option v-for="c in conceptos" :key="c.value" :value="c.value">
                            {{ c.label }}
                        </option>
                    </select>
                </div>
                <a
                    :href="`/reportes/deudas/export/xlsx${exportQuery}`"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                >
                    <FileSpreadsheet class="size-4 text-emerald-600" />
                    Excel
                </a>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-cyan-500 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-600"
                    @click="imprimir"
                >
                    <Printer class="size-4" />
                    Imprimir / PDF
                </button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-4 print:hidden">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-medium uppercase text-slate-500">Lotes deudores</p>
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-50">{{ totales.lotes }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-medium uppercase text-slate-500">Cuotas</p>
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-50">{{ formatBs(totales.cuotas) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-medium uppercase text-slate-500">Multas</p>
                <p class="text-lg font-semibold text-slate-900 dark:text-slate-50">{{ formatBs(totales.multas) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/40">
                <p class="text-[11px] font-medium uppercase text-amber-800 dark:text-amber-300">Total general</p>
                <p class="text-lg font-bold text-amber-900 dark:text-amber-100">{{ formatBs(totalGeneral) }}</p>
            </div>
        </div>

        <article id="deudas-print" class="bg-white p-4 text-slate-900 print:rounded-none print:border-0 print:p-0 print:shadow-none">
            <header class="deudas-print-header mb-3 w-full text-center">
                <p class="text-[10px] font-medium leading-tight tracking-wide text-slate-700">
                    Deudas — ISINUTA — Gestión Administrativa
                </p>
                <p class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-800">
                    Asociación de Agua Potable y Alcantarillado “ISINUTA”
                </p>
                <h2 class="mt-1 text-lg font-bold leading-tight tracking-wide text-slate-900 uppercase">
                    Detalle de deudas por concepto
                </h2>
                <p class="mt-0.5 text-sm font-semibold leading-tight text-slate-800">{{ fechaEmisionLabel }}</p>
            </header>

            <div class="w-full overflow-x-auto print:overflow-visible">
                <p v-if="grupos.length === 0" class="px-1.5 py-4 text-center text-sm text-slate-700">
                    No hay saldos pendientes registrados.
                </p>

                <section v-for="grupo in grupos" :key="grupo.concepto" class="mb-4 border-t border-slate-900 pt-2">
                    <h3 class="grupo-titulo mb-1 text-center text-sm font-semibold text-slate-900">
                        {{ grupo.label }}
                    </h3>
                    <table class="w-full border-collapse table-fixed text-sm text-slate-900">
                        <colgroup>
                            <col class="w-[18%]" />
                            <col class="w-[42%]" />
                            <col class="w-[26%]" />
                            <col class="w-[14%]" />
                        </colgroup>
                        <thead>
                            <tr class="border-b border-slate-900">
                                <th class="px-1.5 py-1 text-left text-[11px] font-bold uppercase tracking-wide">Fecha</th>
                                <th class="px-1.5 py-1 text-left text-[11px] font-bold uppercase tracking-wide">Nombre</th>
                                <th class="px-1.5 py-1 text-left text-[11px] font-bold uppercase tracking-wide">Detalle</th>
                                <th class="col-monto px-1.5 py-1 text-left text-[11px] font-bold uppercase tracking-wide">Bolivianos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(linea, idx) in grupo.lineas"
                                :key="`${grupo.concepto}-${linea.lote_id ?? linea.afiliado_id ?? idx}-${idx}`"
                            >
                                <td class="px-1.5 py-1">{{ linea.fecha }}</td>
                                <td class="break-words px-1.5 py-1">{{ linea.socio }}</td>
                                <td class="break-words px-1.5 py-1">{{ linea.detalle }}</td>
                                <td class="col-monto px-1.5 py-1 text-left tabular-nums">{{ formatNum(linea.bolivianos) }}</td>
                            </tr>
                            <tr class="border-t border-slate-800">
                                <td colspan="3" class="px-1.5 py-1.5 text-right text-sm font-semibold">Subtotal</td>
                                <td class="col-monto px-1.5 py-1.5 text-left text-sm font-semibold tabular-nums">
                                    {{ formatNum(grupo.subtotal) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <p v-if="grupos.length > 0" class="flex px-1.5 pt-2 text-sm font-semibold text-slate-900">
                    <span class="w-[86%] pr-1.5 text-right">Total general</span>
                    <span class="col-monto w-[14%] pl-1.5 text-left tabular-nums">{{ formatNum(totalGeneral) }}</span>
                </p>
            </div>
        </article>
    </div>
</template>

<style>
/* :has(#deudas-print) evita mezclar encabezado/visibilidad con Ingresos. */
@media print {
    @page {
        size: A4 portrait;
        margin: 18mm 12mm 16mm 12mm;

        @bottom-center {
            content: "Asociación de Agua Potable y Alcantarillado \"ISINUTA\" — Página " counter(page) "/" counter(pages);
            font-size: 8pt;
            color: #000;
        }
    }

    body:has(#deudas-print) {
        background: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body:has(#deudas-print) aside,
    body:has(#deudas-print) nav,
    body:has(#deudas-print) [data-sidebar],
    body:has(#deudas-print) [data-side],
    body:has(#deudas-print) [data-slot='sidebar'],
    body:has(#deudas-print) [data-slot='sidebar-gap'],
    body:has(#deudas-print) .print\:hidden {
        display: none !important;
    }

    body:has(#deudas-print) main,
    body:has(#deudas-print) .group\/sidebar-wrapper,
    body:has(#deudas-print) .isinuta-content-compact {
        overflow: visible !important;
        height: auto !important;
        min-height: 0 !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        zoom: 1 !important;
        transform: none !important;
    }

    body:has(#deudas-print) * {
        visibility: hidden !important;
        box-shadow: none !important;
    }

    body:has(#deudas-print) #deudas-print,
    body:has(#deudas-print) #deudas-print * {
        visibility: visible !important;
        color: #000 !important;
        opacity: 1 !important;
    }

    body:has(#deudas-print) #deudas-print {
        position: relative !important;
        left: auto !important;
        top: auto !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 1.5mm !important;
        border: 0 !important;
        background: #fff !important;
        box-sizing: border-box !important;
        overflow: visible !important;
    }

    body:has(#deudas-print) #deudas-print > .deudas-print-header {
        display: none !important;
    }

    body:has(#deudas-print) #deudas-print::before {
        display: block;
        text-align: center;
        white-space: pre-line;
        content: "Deudas — ISINUTA — Gestión Administrativa\A Asociación de Agua Potable y Alcantarillado \"ISINUTA\"\A DETALLE DE DEUDAS POR CONCEPTO\A" var(--deudas-print-emitido, "");
        font-size: 9pt;
        font-weight: 600;
        line-height: 1.35;
        margin-bottom: 8px;
        color: #000;
    }

    body:has(#deudas-print) #deudas-print table {
        width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
    }

    body:has(#deudas-print) #deudas-print thead {
        display: table-header-group;
    }

    body:has(#deudas-print) #deudas-print tbody tr,
    body:has(#deudas-print) #deudas-print .grupo-titulo {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    body:has(#deudas-print) #deudas-print th,
    body:has(#deudas-print) #deudas-print td {
        padding: 4px 5px !important;
        font-size: 8.5pt !important;
        line-height: 1.35 !important;
        vertical-align: top !important;
        word-break: break-word !important;
    }

    body:has(#deudas-print) #deudas-print section {
        border-top: 1px solid #000 !important;
        padding-top: 8px !important;
        margin-top: 4px !important;
    }

    body:has(#deudas-print) #deudas-print .grupo-titulo {
        text-align: center !important;
        font-weight: 600 !important;
        font-size: 9pt !important;
        padding: 6px 2px 4px !important;
    }

    body:has(#deudas-print) #deudas-print .col-monto {
        width: 14% !important;
        text-align: left !important;
        white-space: nowrap !important;
        font-variant-numeric: tabular-nums;
    }
}
</style>
