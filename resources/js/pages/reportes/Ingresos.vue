<script setup lang="ts">
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, FileSpreadsheet, Printer, Wallet, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch, watchEffect } from 'vue';

type Linea = {
    fecha: string;
    cantidad?: number | null;
    socio: string;
    ci?: string;
    lote?: string;
    afiliado_id?: number | null;
    lote_id?: number | null;
    detalle: string;
    periodo?: string;
    dolares: number;
    bolivianos: number;
    recibo: string;
    pagado: string;
};

type Grupo = {
    concepto: string;
    label: string;
    lineas: Linea[];
    subtotal: number;
};

type Props = {
    reporte: {
        desde: string;
        hasta: string;
        grupos: Grupo[];
        total_general: number;
        cuentas?: number;
    };
    filtros: {
        desde: string;
        hasta: string;
        socio?: number | null;
        concepto?: string | null;
        categoria?: number | null;
    };
    opciones: {
        afiliados: {
            id: number;
            ci: string | null;
            nombre: string;
            categorias: number[];
            con_pagos: boolean;
            primer_pago: string | null;
            ultimo_pago: string | null;
        }[];
        conceptos: { value: string; label: string }[];
        categorias: { id: number; nombre: string; total: number }[];
        primer_pago: string | null;
    };
};

const props = defineProps<Props>();

defineOptions({
    inheritAttrs: false,
    layout: () => ({
        breadcrumbs: [
            { title: 'Reportes', href: '/reportes' },
            { title: 'Ingresos', href: '/reportes/ingresos' },
        ],
    }),
});

const desde = ref(props.filtros.desde);
const hasta = ref(props.filtros.hasta);

watch(
    () => [props.filtros.desde, props.filtros.hasta],
    ([nuevoDesde, nuevoHasta]) => {
        desde.value = nuevoDesde as string;
        hasta.value = nuevoHasta as string;
    },
);
type OpcionAfiliado = Props['opciones']['afiliados'][number];

const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

const etiquetaAfiliado = (a: OpcionAfiliado) => (a.ci ? `CI ${a.ci} · ${a.nombre}` : a.nombre);

const socio = ref(props.filtros.socio ? String(props.filtros.socio) : '');
const afiliadoSeleccionado = computed(() => props.opciones.afiliados.find((a) => String(a.id) === socio.value) ?? null);
const busquedaAfiliado = ref(afiliadoSeleccionado.value ? etiquetaAfiliado(afiliadoSeleccionado.value) : '');
const listaAfiliadosAbierta = ref(false);

const nombreCategoria = (id: number) => props.opciones.categorias.find((c) => c.id === id)?.nombre ?? '';

const afiliadosIndexados = computed(() =>
    props.opciones.afiliados.map((a) => ({
        afiliado: a,
        indice: normalizar(`${a.nombre} ${a.ci ?? ''}`),
    })),
);

const afiliadosFiltrados = computed(() => {
    const seleccionadoTexto = afiliadoSeleccionado.value ? etiquetaAfiliado(afiliadoSeleccionado.value) : null;
    const termino = busquedaAfiliado.value === seleccionadoTexto ? '' : normalizar(busquedaAfiliado.value);
    const palabras = termino.split(/\s+/).filter(Boolean);

    return afiliadosIndexados.value
        .filter(({ indice }) => palabras.every((p) => indice.includes(p)))
        .map(({ afiliado }) => afiliado);
});

const abrirListaAfiliados = () => {
    listaAfiliadosAbierta.value = true;
};

const cerrarListaAfiliados = () => {
    window.setTimeout(() => {
        listaAfiliadosAbierta.value = false;
    }, 150);
};

const ampliarRangoA = (afiliado: OpcionAfiliado) => {
    if (afiliado.primer_pago && afiliado.primer_pago < desde.value) desde.value = afiliado.primer_pago;
    if (afiliado.ultimo_pago && afiliado.ultimo_pago > hasta.value) hasta.value = afiliado.ultimo_pago;
};

const seleccionarAfiliado = (afiliado: OpcionAfiliado | null) => {
    socio.value = afiliado ? String(afiliado.id) : '';
    busquedaAfiliado.value = afiliado ? etiquetaAfiliado(afiliado) : '';
    listaAfiliadosAbierta.value = false;
    // No cambia el rango de fechas: respeta desde/hasta elegidos.
    aplicarFiltro();
};

const generar = () => {
    const texto = busquedaAfiliado.value.trim();
    const seleccionadoTexto = afiliadoSeleccionado.value ? etiquetaAfiliado(afiliadoSeleccionado.value) : '';

    if (texto === '') {
        socio.value = '';
    } else if (texto !== seleccionadoTexto) {
        if (!afiliadosFiltrados.value.length) {
            listaAfiliadosAbierta.value = true;
            return;
        }
        seleccionarAfiliado(afiliadosFiltrados.value[0]);
        return;
    }

    aplicarFiltro();
};

const verTodosLosPagosDelAfiliado = () => {
    if (!afiliadoSeleccionado.value) return;
    ampliarRangoA(afiliadoSeleccionado.value);
    aplicarFiltro();
};

const formatFecha = (iso: string) => iso.split('-').reverse().join('/');

const fechaIso = (fecha: Date) => {
    const m = String(fecha.getMonth() + 1).padStart(2, '0');
    const d = String(fecha.getDate()).padStart(2, '0');
    return `${fecha.getFullYear()}-${m}-${d}`;
};

const usarRango = (rango: 'mes' | 'anio' | 'todo') => {
    const hoy = new Date();
    hasta.value = fechaIso(hoy);
    if (rango === 'mes') desde.value = fechaIso(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
    if (rango === 'anio') desde.value = fechaIso(new Date(hoy.getFullYear(), 0, 1));
    if (rango === 'todo') desde.value = props.opciones.primer_pago ?? fechaIso(new Date(hoy.getFullYear(), 0, 1));
    aplicarFiltro();
};

const afiliadosEnReporte = computed(() => {
    const nombres = new Set<string>();
    props.reporte.grupos.forEach((g) => g.lineas.forEach((l) => nombres.add(String(l.afiliado_id ?? l.socio))));
    return nombres.size;
});
const concepto = ref(props.filtros.concepto ?? '');

const formatNum = (n: number) =>
    new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

const fechaRangoLabel = computed(() => {
    const d = props.reporte.desde.split('-').reverse().join('/');
    const h = props.reporte.hasta.split('-').reverse().join('/');
    return `DEL: ${d} AL: ${h}`;
});

/** Variable para @top-center: encabezado repetido en cada hoja al imprimir. */
watchEffect(() => {
    const rango = fechaRangoLabel.value.replace(/["\\]/g, '');
    document.documentElement.style.setProperty('--ingresos-print-rango', `"${rango}"`);
});

onBeforeUnmount(() => {
    document.documentElement.style.removeProperty('--ingresos-print-rango');
});

const exportQuery = computed(() => {
    const params = new URLSearchParams({
        desde: desde.value,
        hasta: hasta.value,
    });
    if (socio.value) params.set('socio', socio.value);
    if (concepto.value) params.set('concepto', concepto.value);
    return `?${params.toString()}`;
});

const aplicarFiltro = () => {
    const params: Record<string, string> = {
        desde: desde.value,
        hasta: hasta.value,
    };
    if (socio.value) params.socio = socio.value;
    if (concepto.value) params.concepto = concepto.value;

    router.get('/reportes/ingresos', params, { preserveState: true, preserveScroll: true });
};

const imprimir = () => window.print();
</script>

<template>
    <div :class="[pageShellClass, 'mx-auto max-w-6xl print:max-w-none print:gap-0 print:p-0']">
        <Head title="Ingresos" />

        <div class="flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div class="flex items-center gap-3">
                <Link href="/reportes" class="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <ArrowLeft class="size-5 text-slate-600 dark:text-slate-400" />
                </Link>
                <div class="flex items-center justify-center w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <Wallet class="text-white size-5" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">Ingresos</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Todo lo cobrado recientemente por concepto: servicios, afiliación, multas pagadas y demás.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-2 p-3 border rounded-xl border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/50">
                <div>
                    <label class="block mb-1 text-xs font-medium text-slate-600 dark:text-slate-300">Desde fecha</label>
                    <input
                        v-model="desde"
                        type="date"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50 dark:[color-scheme:dark]"
                        @change="aplicarFiltro"
                    />
                </div>
                <div>
                    <label class="block mb-1 text-xs font-medium text-slate-600 dark:text-slate-300">Hasta</label>
                    <input
                        v-model="hasta"
                        type="date"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50 dark:[color-scheme:dark]"
                        @change="aplicarFiltro"
                    />
                </div>
                <div>
                    <label class="block mb-1 text-xs font-medium text-slate-600 dark:text-slate-300">Afiliado</label>
                    <div class="relative w-72">
                        <input
                            v-model="busquedaAfiliado"
                            type="text"
                            autocomplete="off"
                            placeholder="Buscar por nombre, código o CI"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 pr-8 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50"
                            @focus="abrirListaAfiliados"
                            @input="abrirListaAfiliados"
                            @blur="cerrarListaAfiliados"
                            @keydown.enter.prevent="generar"
                            @keydown.esc="listaAfiliadosAbierta = false"
                        />
                        <button
                            v-if="socio"
                            type="button"
                            class="absolute -translate-y-1/2 right-2 top-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                            title="Quitar filtro de afiliado"
                            @mousedown.prevent="seleccionarAfiliado(null)"
                        >
                            <X class="size-4" />
                        </button>

                        <div
                            v-if="listaAfiliadosAbierta"
                            class="absolute left-0 z-30 w-full mt-1 overflow-hidden bg-white border rounded-lg shadow-lg top-full border-slate-200 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <ul class="overflow-y-auto max-h-72">
                                <li v-for="afi in afiliadosFiltrados" :key="afi.id">
                                    <button
                                        type="button"
                                        class="flex items-center justify-between w-full gap-2 px-3 py-1.5 text-left text-sm hover:bg-cyan-50 dark:hover:bg-slate-800"
                                        :class="String(afi.id) === socio ? 'bg-cyan-50 font-semibold dark:bg-slate-800' : ''"
                                        @mousedown.prevent="seleccionarAfiliado(afi)"
                                    >
                                        <span class="min-w-0">
                                            <span class="block truncate text-slate-900 dark:text-slate-100">{{ afi.nombre }}</span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                <template v-if="afi.ci">CI {{ afi.ci }}</template
                                                ><template v-if="afi.categorias.length"> · {{ afi.categorias.map(nombreCategoria).join(', ') }}</template>
                                            </span>
                                        </span>
                                        <span
                                            v-if="afi.con_pagos"
                                            class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                        >
                                            con pagos
                                        </span>
                                    </button>
                                </li>
                                <li v-if="!afiliadosFiltrados.length" class="px-3 py-3 text-sm text-center text-slate-500">
                                    No se encontraron afiliados.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block mb-1 text-xs font-medium text-slate-600 dark:text-slate-300">Concepto</label>
                    <select v-model="concepto" @change="generar" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50">
                        <option value="">Todos los conceptos</option>
                        <option v-for="c in opciones.conceptos" :key="c.value" :value="c.value">
                            {{ c.label }}
                        </option>
                    </select>
                </div>
                <button
                    type="button"
                    class="px-3 py-2 text-sm font-medium bg-white border rounded-lg border-slate-200 text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                    @click="generar"
                >
                    Generar
                </button>
                <a
                    :href="`/reportes/ingresos/export/xlsx${exportQuery}`"
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
                <div class="flex flex-wrap items-center w-full gap-2 pt-1 text-xs text-slate-600 dark:text-slate-300">
                    <span class="font-medium">Rango rápido:</span>
                    <button
                        v-for="r in [
                            { id: 'mes', label: 'Este mes' },
                            { id: 'anio', label: 'Este año' },
                            { id: 'todo', label: 'Todo el historial' },
                        ] as const"
                        :key="r.id"
                        type="button"
                        class="rounded-full border border-slate-200 bg-white px-3 py-1 font-medium text-slate-700 hover:border-cyan-400 hover:text-cyan-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        @click="usarRango(r.id)"
                    >
                        {{ r.label }}
                    </button>
                    <span class="ml-auto">
                        {{ afiliadosEnReporte }} afiliado(s) con pagos entre {{ formatFecha(reporte.desde) }} y {{ formatFecha(reporte.hasta) }}
                    </span>
                </div>
            </div>
        </div>

        <article id="ingresos-print" class="bg-white p-4 text-slate-900 print:rounded-none print:border-0 print:p-0 print:shadow-none">
            <!-- En pantalla: normal. Al imprimir: position:fixed → se repite en cada hoja. -->
            <header class="ingresos-print-header mb-3 w-full text-center">
                <p class="text-[10px] font-medium leading-tight tracking-wide text-slate-700">
                    Pagos x Concepto — ISINUTA — Gestión Administrativa
                </p>
                <p class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-800">
                    Asociación de Agua Potable y Alcantarillado “ISINUTA”
                </p>
                <h2 class="mt-1 text-lg font-bold leading-tight tracking-wide text-slate-900 uppercase">
                    Detalle de pagos por concepto
                </h2>
                <p class="mt-0.5 text-sm font-semibold leading-tight text-slate-800">{{ fechaRangoLabel }}</p>
            </header>

            <div class="w-full overflow-x-auto print:overflow-visible">
                <p v-if="reporte.grupos.length === 0" class="px-1.5 py-4 text-center text-sm text-slate-700">
                    <template v-if="afiliadoSeleccionado">
                        {{ afiliadoSeleccionado.nombre }} no tiene pagos en el rango seleccionado.
                        <span v-if="afiliadoSeleccionado.primer_pago" class="block mt-1 text-xs text-slate-500">
                            Tiene pagos del {{ formatFecha(afiliadoSeleccionado.primer_pago) }} al
                            {{ formatFecha(afiliadoSeleccionado.ultimo_pago ?? afiliadoSeleccionado.primer_pago) }}.
                            <button
                                type="button"
                                class="ml-1 font-medium underline text-cyan-700 hover:text-cyan-900 print:hidden"
                                @click="verTodosLosPagosDelAfiliado"
                            >
                                Ver todos sus pagos
                            </button>
                        </span>
                        <span v-else class="block mt-1 text-xs text-slate-500">
                            Este afiliado aún no tiene recibos emitidos.
                        </span>
                    </template>
                    <template v-else>
                        No hay pagos registrados en el rango seleccionado.
                        <span class="block mt-1 text-xs text-slate-500">
                            Amplíe las fechas o registre recibos emitidos en Recibos.
                        </span>
                    </template>
                </p>

                <section v-for="grupo in reporte.grupos" :key="grupo.concepto" class="mb-4 border-t border-slate-900 pt-2">
                    <h3 class="grupo-titulo mb-1 text-center text-sm font-semibold text-slate-900">
                        {{ grupo.label }}
                    </h3>
                    <table class="w-full text-sm border-collapse table-fixed text-slate-900">
                        <colgroup>
                            <col class="w-[22%]" />
                            <col class="w-[46%]" />
                            <col class="w-[18%]" />
                            <col class="w-[14%]" />
                        </colgroup>
                        <thead>
                            <tr class="border-b border-slate-900">
                                <th class="px-1.5 py-1 text-left text-[11px] font-bold uppercase tracking-wide text-slate-900">Fecha</th>
                                <th class="px-1.5 py-1 text-left text-[11px] font-bold uppercase tracking-wide text-slate-900">Nombre</th>
                                <th class="px-1.5 py-1 text-left text-[11px] font-bold uppercase tracking-wide text-slate-900">Detalle</th>
                                <th class="col-monto px-1.5 py-1 text-left text-[11px] font-bold uppercase tracking-wide text-slate-900">Bolivianos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(linea, idx) in grupo.lineas"
                                :key="`${grupo.concepto}-${linea.lote_id ?? linea.afiliado_id ?? idx}-${idx}`"
                            >
                                <td class="px-1.5 py-1 text-slate-900">{{ linea.fecha }}</td>
                                <td class="break-words px-1.5 py-1 text-slate-900">{{ linea.socio }}</td>
                                <td class="break-words px-1.5 py-1 text-slate-900">
                                    {{ linea.detalle }}<span v-if="linea.cantidad"> · cantidad: {{ linea.cantidad }}</span>
                                </td>
                                <td class="col-monto px-1.5 py-1 text-left tabular-nums text-slate-900">
                                    {{ formatNum(linea.bolivianos) }}
                                </td>
                            </tr>
                            <tr class="border-t border-slate-800">
                                <td colspan="3" class="px-1.5 py-1.5 text-right text-sm font-semibold text-slate-900">
                                    Subtotal
                                </td>
                                <td class="col-monto px-1.5 py-1.5 text-left text-sm font-semibold tabular-nums text-slate-900">
                                    {{ formatNum(grupo.subtotal) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <p v-if="reporte.grupos.length > 0" class="flex px-1.5 pt-2 text-sm font-semibold text-slate-900">
                    <span class="w-[86%] pr-1.5 text-right">Total general</span>
                    <span class="col-monto w-[14%] pl-1.5 text-left tabular-nums">{{ formatNum(reporte.total_general) }}</span>
                </p>
            </div>
        </article>
    </div>
</template>

<style>
/* :has(#ingresos-print) evita que estas reglas afecten la impresión de Deudas u otras pantallas. */
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

    body:has(#ingresos-print) {
        width: 100% !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body:has(#ingresos-print) aside,
    body:has(#ingresos-print) nav,
    body:has(#ingresos-print) [data-sidebar],
    body:has(#ingresos-print) [data-side],
    body:has(#ingresos-print) [data-slot='sidebar'],
    body:has(#ingresos-print) [data-slot='sidebar-gap'],
    body:has(#ingresos-print) .print\:hidden {
        display: none !important;
    }

    body:has(#ingresos-print) main,
    body:has(#ingresos-print) .group\/sidebar-wrapper,
    body:has(#ingresos-print) .isinuta-content-compact {
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

    body:has(#ingresos-print) * {
        visibility: hidden !important;
        box-shadow: none !important;
    }

    body:has(#ingresos-print) #ingresos-print,
    body:has(#ingresos-print) #ingresos-print * {
        visibility: visible !important;
        color: #000 !important;
        opacity: 1 !important;
    }

    body:has(#ingresos-print) #ingresos-print {
        position: relative !important;
        left: auto !important;
        top: auto !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 1.5mm !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: #fff !important;
        box-shadow: none !important;
        box-sizing: border-box !important;
        overflow: visible !important;
        transform: none !important;
        zoom: 1 !important;
    }

    body:has(#ingresos-print) #ingresos-print > .ingresos-print-header {
        display: none !important;
    }

    body:has(#ingresos-print) #ingresos-print::before {
        display: block;
        text-align: center;
        white-space: pre-line;
        content: "Ingresos — ISINUTA — Gestión Administrativa\A Asociación de Agua Potable y Alcantarillado \"ISINUTA\"\A DETALLE DE PAGOS POR CONCEPTO\A" var(--ingresos-print-rango, "");
        font-size: 9pt;
        font-weight: 600;
        line-height: 1.35;
        margin-bottom: 8px;
        color: #000;
    }

    body:has(#ingresos-print) #ingresos-print > div {
        width: 100% !important;
        max-width: 100% !important;
        overflow: visible !important;
    }

    body:has(#ingresos-print) #ingresos-print table {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
    }

    body:has(#ingresos-print) #ingresos-print thead {
        display: table-header-group;
    }

    body:has(#ingresos-print) #ingresos-print tbody tr,
    body:has(#ingresos-print) #ingresos-print tfoot tr,
    body:has(#ingresos-print) #ingresos-print .grupo-titulo {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    body:has(#ingresos-print) #ingresos-print th,
    body:has(#ingresos-print) #ingresos-print td {
        padding: 4px 5px !important;
        font-size: 8.5pt !important;
        line-height: 1.35 !important;
        white-space: normal !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        overflow: visible !important;
        text-overflow: clip !important;
        vertical-align: top !important;
    }

    body:has(#ingresos-print) #ingresos-print section {
        border-top: 1px solid #000 !important;
        padding-top: 8px !important;
        margin-top: 4px !important;
        overflow: visible !important;
    }

    body:has(#ingresos-print) #ingresos-print .grupo-titulo {
        text-align: center !important;
        font-weight: 600 !important;
        font-size: 9pt !important;
        line-height: 1.4 !important;
        text-transform: none !important;
        border: 0 !important;
        padding: 6px 2px 4px !important;
        margin: 0 !important;
        overflow: visible !important;
    }

    body:has(#ingresos-print) #ingresos-print td:first-child,
    body:has(#ingresos-print) #ingresos-print th:first-child {
        width: 20% !important;
        white-space: normal !important;
    }

    body:has(#ingresos-print) #ingresos-print .col-monto {
        width: 14% !important;
        text-align: left !important;
        white-space: nowrap !important;
        font-variant-numeric: tabular-nums;
        padding-right: 4px !important;
        padding-left: 4px !important;
    }
}
</style>