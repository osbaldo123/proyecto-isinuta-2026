<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Wallet } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { formatBs, nombreMes } from '@/lib/pagos';

type Celda = {
    periodo: string;
    mes: string;
    nombre: string;
    estado: 'pagado' | 'adelantado' | 'vencido' | 'pendiente' | 'por_venir' | 'sin_servicio' | string;
    detalle: string;
    monto: number | null;
};

type FilaServicio = { servicio_id: number; nombre: string; tarifa: number; meses: Celda[] };

type ReciboLote = {
    id: number;
    numero_recibo: string;
    fecha: string;
    tipo_recibo: string;
    concepto: string;
    monto_total: number;
    estado: string;
    motivo_anulacion: string | null;
};

type MultaLote = { id: number; titulo: string; detalle: string; monto: number; estado: string };

const props = defineProps<{
    lote: {
        id: number;
        numero_lote: string;
        otb: string | null;
        categoria: string | null;
        estado: string;
        fecha_afiliacion: string | null;
        forma_pago_afiliacion: string | null;
        afiliado: { id: number; nombre_completo: string; ci: string };
        lotes: Array<{ id: number; numero_lote: string; otb: string | null; estado: string; activo: boolean }>;
    };
    estado: {
        gestion: number;
        gestiones: number[];
        resumen: {
            cuotas_pendientes: number;
            cuotas_vencidas: number;
            meses_vencidos: number;
            rango_vencido: string | null;
            multas_pendientes: number;
            saldo_afiliacion: number;
            forma_pago_afiliacion: string | null;
            total: number;
            al: string;
        };
        servicios: FilaServicio[];
        recibos: ReciboLote[];
        multas: MultaLote[];
    };
    puedeCobrar: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Recibos', href: '/pagos' },
            { title: 'Estado de cuenta', href: '#' },
        ],
    }),
});

const MESES_CABECERA = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const resumen = computed(() => props.estado.resumen);
const multasPendientes = computed(() => props.estado.multas.filter((m) => m.estado === 'pendiente'));

const tieneMoraAnualPendiente = computed(() => {
    const deMora = multasPendientes.value.filter((m) => String(m.titulo || '').toLowerCase().includes('mora'));
    const suma = deMora.reduce((acc, m) => acc + Number(m.monto || 0), 0);
    const hayEtiquetaAnual = deMora.some((m) => String(m.titulo || '').includes('Mora anual'));

    return hayEtiquetaAnual || suma >= 149.99;
});

const estadoLote = computed(() => {
    if (props.lote.estado === 'baja') {
        return { texto: 'Dado de baja', clase: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' };
    }

    // Corte solo con mora anual Bs 150. Trimestral = sola multa (Bs 50 → 100 → 150).
    if (tieneMoraAnualPendiente.value) {
        return {
            texto: 'Cortado anual',
            clase: 'bg-orange-100 text-orange-800 dark:bg-orange-400/15 dark:text-orange-300',
        };
    }

    return { texto: 'Activo', clase: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300' };
});

const subtitulo = computed(() =>
    [
        props.lote.afiliado.nombre_completo,
        props.lote.numero_lote ? `Lote ${props.lote.numero_lote}` : null,
        props.lote.otb ? `OTB ${props.lote.otb}` : null,
        props.lote.afiliado.ci ? `CI ${props.lote.afiliado.ci}` : null,
        props.lote.categoria ? props.lote.categoria.charAt(0).toUpperCase() + props.lote.categoria.slice(1) : null,
        props.lote.fecha_afiliacion ? `Afiliado desde el ${props.lote.fecha_afiliacion}` : null,
    ]
        .filter(Boolean)
        .join(' · '),
);

const verGestion = (gestion: number) => {
    router.get(`/pagos/estado-cuenta/${props.lote.id}`, { gestion }, { preserveScroll: true, preserveState: true, only: ['estado'] });
};

const celda = (estado: string) =>
    ({
        pagado: {
            icono: '✓',
            texto: 'Pagado',
            clase: 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-800 dark:bg-teal-900/50 dark:text-teal-200',
        },
        adelantado: {
            icono: '✓',
            texto: 'Adelantado',
            clase: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        },
        vencido: {
            icono: '!',
            texto: 'Atrasado',
            clase: 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-400/60 dark:bg-orange-400/10 dark:text-orange-300',
        },
        pendiente: {
            icono: '○',
            texto: 'A cobrar',
            clase: 'border-slate-300 bg-white text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100',
        },
        por_venir: {
            icono: '·',
            texto: 'Por venir',
            clase: 'border-dashed border-slate-300 bg-transparent text-slate-500 dark:border-slate-700 dark:text-slate-400',
        },
    })[estado] ?? { icono: '', texto: '—', clase: 'border-transparent bg-slate-100 text-slate-400 dark:bg-slate-800/50 dark:text-slate-600' };

const tituloCelda = (servicio: FilaServicio, mes: Celda) =>
    `${servicio.nombre}, ${nombreMes(mes.periodo)}: ${celda(mes.estado).texto.toLowerCase()}${mes.detalle ? ` (${mes.detalle})` : ''}${mes.monto ? ` · ${formatBs(mes.monto)}` : ''}`;

const afiliacionTexto = computed(() => {
    if (resumen.value.saldo_afiliacion > 0) {
        return resumen.value.forma_pago_afiliacion === 'plazo' ? 'A plazo, falta pagar' : 'Falta pagar';
    }

    return 'Pagada';
});

/* En pantallas angostas la grilla se desplaza: se lleva a la vista el mes que importa
   (el mes en curso en la gestión actual, si no el primero con servicio). */
const grilla = ref<HTMLElement | null>(null);

const mostrarMesRelevante = async () => {
    await nextTick();

    const contenedor = grilla.value;

    if (!contenedor || contenedor.scrollWidth <= contenedor.clientWidth) {
        return;
    }

    const hoy = new Date();
    const actual = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
    const objetivo =
        contenedor.querySelector<HTMLElement>(`[data-periodo="${actual}"]`) ??
        contenedor.querySelector<HTMLElement>('[data-estado]:not([data-estado="sin_servicio"])');

    if (objetivo) {
        contenedor.scrollLeft = Math.max(0, objetivo.offsetLeft - contenedor.clientWidth / 2 + objetivo.offsetWidth / 2);
    }
};

onMounted(mostrarMesRelevante);
watch(() => props.estado.gestion, mostrarMesRelevante);
</script>

<template>
    <Head :title="`Estado de cuenta · Lote ${lote.numero_lote}`" />

    <div class="min-h-[calc(100vh-4rem)] w-full print:bg-white">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-3 py-3 sm:gap-4 sm:px-6 sm:py-4 lg:px-8 print:max-w-none print:p-0">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-50">Estado de cuenta</h1>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="estadoLote.clase">{{
                            estadoLote.texto
                        }}</span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ subtitulo }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="puedeCobrar"
                        :href="`/pagos/registrar/nuevo?afiliado_id=${lote.id}`"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-cyan-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700 dark:bg-cyan-400 dark:text-cyan-950 dark:hover:bg-cyan-300"
                    >
                        <Wallet class="size-4" />
                        Cobrar a este lote
                    </Link>
                </div>
            </div>

            <section
                v-if="lote.lotes.length > 0"
                aria-label="Seleccionar lote del afiliado"
                class="rounded-2xl border border-cyan-500/30 bg-cyan-500/5 p-3 print:hidden dark:border-cyan-400/25 dark:bg-cyan-500/10"
            >
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">
                    {{ lote.lotes.length > 1 ? 'Seleccionar lote / propiedad' : 'Lote / propiedad' }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="item in lote.lotes"
                        :key="item.id"
                        :href="`/pagos/estado-cuenta/${item.id}`"
                        class="inline-flex min-w-[7.5rem] flex-col items-start rounded-xl border px-3 py-2 text-left transition"
                        :class="
                            item.activo
                                ? 'border-cyan-500 bg-cyan-500 text-white shadow-sm'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-cyan-400 hover:bg-cyan-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-cyan-500/50 dark:hover:bg-slate-800'
                        "
                        :aria-current="item.activo ? 'page' : undefined"
                    >
                        <span class="text-sm font-semibold">Lote {{ item.numero_lote || '—' }}</span>
                        <span
                            class="mt-0.5 max-w-[11rem] truncate text-xs"
                            :class="item.activo ? 'text-cyan-50' : 'text-slate-500 dark:text-slate-400'"
                        >
                            {{ item.otb ? `OTB ${item.otb}` : 'Sin OTB' }}
                        </span>
                    </Link>
                </div>
            </section>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="flex flex-col gap-1 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <span class="text-[13px] text-slate-600 dark:text-slate-400">Deuda total</span>
                    <strong class="text-[26px] tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(resumen.total) }}</strong>
                    <span class="text-xs text-slate-500">Al {{ resumen.al }}</span>
                </div>
                <div class="flex flex-col gap-1 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <span class="text-[13px] text-slate-600 dark:text-slate-400">Mensualidades pendientes</span>
                    <strong
                        class="text-[26px] tabular-nums"
                        :class="resumen.cuotas_vencidas > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-slate-900 dark:text-slate-50'"
                        >{{ formatBs(resumen.cuotas_vencidas) }}</strong
                    >
                    <span class="text-xs text-slate-500">{{ resumen.rango_vencido ?? 'Al día' }}</span>
                </div>
                <div class="flex flex-col gap-1 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <span class="text-[13px] text-slate-600 dark:text-slate-400">Multas pendientes</span>
                    <strong class="text-[26px] tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(resumen.multas_pendientes) }}</strong>
                    <span class="text-xs text-slate-500">{{
                        multasPendientes.length === 0 ? 'Sin multas' : multasPendientes.length === 1 ? '1 multa' : `${multasPendientes.length} multas`
                    }}</span>
                </div>
                <div class="flex flex-col gap-1 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <span class="text-[13px] text-slate-600 dark:text-slate-400">Afiliación</span>
                    <strong class="text-[26px] tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(resumen.saldo_afiliacion) }}</strong>
                    <span class="text-xs text-slate-500">{{ afiliacionTexto }}</span>
                </div>
            </div>

            <section
                aria-labelledby="meses-titulo"
                class="flex flex-col gap-3.5 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="meses-titulo" class="text-[17px] font-bold text-slate-900 dark:text-slate-50">Mensualidades {{ estado.gestion }}</h2>
                    <div
                        role="group"
                        aria-label="Gestión"
                        class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-950 print:hidden"
                    >
                        <button
                            v-for="gestion in estado.gestiones"
                            :key="gestion"
                            type="button"
                            :aria-pressed="gestion === estado.gestion"
                            class="min-h-9 rounded-lg px-3 text-[13px] font-semibold transition"
                            :class="
                                gestion === estado.gestion
                                    ? 'bg-cyan-600 text-white dark:bg-cyan-400 dark:text-cyan-950'
                                    : 'text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-800'
                            "
                            @click="verGestion(gestion)"
                        >
                            {{ gestion }}
                        </button>
                    </div>
                </div>

                <p v-if="estado.servicios.length === 0" class="text-sm text-slate-600 dark:text-slate-400">
                    Este lote no tiene servicio de agua ni de alcantarillado.
                </p>

                <p v-if="estado.servicios.length > 0" class="-mb-1 text-xs text-slate-500 md:hidden">
                    Desliza la tabla hacia los lados para ver los 12 meses.
                </p>
                <div v-else ref="grilla" class="relative overflow-x-auto">
                    <table class="w-full min-w-[860px] table-fixed border-separate border-spacing-1.5 text-center">
                        <caption class="sr-only">
                            Estado de cada mes de
                            {{
                                estado.gestion
                            }}
                            por servicio
                        </caption>
                        <thead>
                            <tr class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                <th scope="col" class="sticky left-0 z-10 w-36 bg-white text-left font-medium dark:bg-slate-900">Servicio</th>
                                <th v-for="mes in MESES_CABECERA" :key="mes" scope="col" class="font-medium">{{ mes }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="servicio in estado.servicios" :key="servicio.servicio_id">
                                <th scope="row" class="sticky left-0 z-10 bg-white text-left align-middle dark:bg-slate-900">
                                    <span class="block text-sm font-bold text-slate-900 dark:text-slate-50">{{ servicio.nombre }}</span>
                                    <span class="block text-xs font-normal text-slate-500">{{ formatBs(servicio.tarifa) }} por mes</span>
                                </th>
                                <td
                                    v-for="mes in servicio.meses"
                                    :key="mes.periodo"
                                    class="p-0"
                                    :data-periodo="mes.periodo"
                                    :data-estado="mes.estado"
                                >
                                    <span
                                        :title="tituloCelda(servicio, mes)"
                                        class="flex min-h-[52px] flex-col items-center justify-center gap-0.5 rounded-xl border px-1 text-[11px] font-semibold"
                                        :class="celda(mes.estado).clase"
                                    >
                                        <span aria-hidden="true" class="text-[13px] leading-none">{{ celda(mes.estado).icono }}</span>
                                        <span>{{ celda(mes.estado).texto }}</span>
                                        <span class="sr-only">{{ tituloCelda(servicio, mes) }}</span>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
                    <span><strong class="text-teal-700 dark:text-teal-300">✓ Pagado</strong> con su recibo</span>
                    <span><strong class="text-sky-700 dark:text-sky-300">✓ Adelantado</strong> pagado antes de cerrarse el mes</span>
                    <span><strong class="text-slate-800 dark:text-slate-100">○ A cobrar</strong> días 1–2 del mes siguiente</span>
                    <span><strong class="text-orange-700 dark:text-orange-300">! Atrasado</strong> desde el día 3 si no pagó</span>
                    <span><strong class="text-slate-500">· Por venir</strong> se puede adelantar</span>
                </div>
            </section>

            <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section
                    aria-labelledby="recibos-titulo"
                    class="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="recibos-titulo" class="text-[17px] font-bold text-slate-900 dark:text-slate-50">Recibos del lote</h2>
                        <span class="text-xs text-slate-500">Los anulados se muestran tachados y no suman.</span>
                    </div>
                    <p v-if="estado.recibos.length === 0" class="text-sm text-slate-600 dark:text-slate-400">Este lote todavía no tiene recibos.</p>
                    <div v-else class="relative overflow-x-auto">
                        <table v-tabla-responsiva class="w-full md:min-w-[640px] text-[13px]">
                            <thead>
                                <tr
                                    class="border-b border-slate-200 text-left text-xs font-semibold text-slate-500 dark:border-slate-800 dark:text-slate-400"
                                >
                                    <th scope="col" class="py-2.5 pr-2">Fecha</th>
                                    <th scope="col" class="py-2.5 pr-2">Recibo</th>
                                    <th scope="col" class="py-2.5 pr-2">Concepto y meses</th>
                                    <th scope="col" class="py-2.5 pr-2 text-right">Importe</th>
                                    <th scope="col" class="py-2.5">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="recibo in estado.recibos"
                                    :key="recibo.id"
                                    class="border-b border-slate-100 align-top dark:border-slate-800/70"
                                >
                                    <td class="whitespace-nowrap py-3 pr-2 text-slate-600 dark:text-slate-400">{{ recibo.fecha }}</td>
                                    <td class="whitespace-nowrap py-3 pr-2">
                                        <Link
                                            :href="`/pagos/${recibo.id}`"
                                            class="font-mono text-cyan-700 hover:underline dark:text-cyan-300"
                                            :class="recibo.estado === 'anulado' ? 'line-through' : ''"
                                            >{{ recibo.numero_recibo }}</Link
                                        >
                                    </td>
                                    <td class="py-3 pr-2">
                                        <span
                                            :class="
                                                recibo.estado === 'anulado'
                                                    ? 'text-slate-400 line-through dark:text-slate-500'
                                                    : 'text-slate-800 dark:text-slate-100'
                                            "
                                            >{{ recibo.concepto }}</span
                                        >
                                        <span
                                            v-if="recibo.estado === 'anulado' && recibo.motivo_anulacion"
                                            class="mt-0.5 block text-xs text-orange-700 dark:text-orange-300"
                                        >
                                            Anulado: {{ recibo.motivo_anulacion }}
                                        </span>
                                    </td>
                                    <td
                                        class="whitespace-nowrap py-3 pr-2 text-right tabular-nums"
                                        :class="recibo.estado === 'anulado' ? 'text-slate-400 line-through' : 'text-slate-900 dark:text-slate-50'"
                                    >
                                        {{ formatBs(recibo.monto_total) }}
                                    </td>
                                    <td class="py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold"
                                            :class="
                                                recibo.estado === 'anulado'
                                                    ? 'bg-orange-100 text-orange-800 dark:bg-orange-400/15 dark:text-orange-300'
                                                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300'
                                            "
                                            >{{ recibo.estado === 'anulado' ? 'ANULADO' : 'EMITIDO' }}</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    aria-labelledby="multas-titulo"
                    class="flex min-w-0 flex-col gap-2.5 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <h2 id="multas-titulo" class="text-[17px] font-bold text-slate-900 dark:text-slate-50">Multas</h2>
                    <p v-if="estado.multas.length === 0" class="text-sm text-slate-600 dark:text-slate-400">Sin multas registradas.</p>
                    <div
                        v-for="multa in estado.multas"
                        :key="multa.id"
                        class="flex justify-between gap-3 rounded-xl border px-3 py-2.5 text-[13px]"
                        :class="
                            multa.estado === 'pendiente'
                                ? 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/60'
                                : 'border-dashed border-slate-300 text-slate-500 dark:border-slate-700 dark:text-slate-400'
                        "
                    >
                        <span class="flex min-w-0 flex-col">
                            <strong class="text-slate-900 dark:text-slate-50">{{ multa.titulo }}</strong>
                            <span class="text-slate-600 dark:text-slate-400"
                                >{{ multa.detalle }} · {{ multa.estado === 'pendiente' ? 'Pendiente' : 'Pagada' }}</span
                            >
                        </span>
                        <strong class="shrink-0 tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(multa.monto) }}</strong>
                    </div>
                    <Link
                        v-if="puedeCobrar && multasPendientes.length > 0"
                        :href="`/pagos/registrar/nuevo?afiliado_id=${lote.id}`"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 text-sm font-medium text-slate-800 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800 print:hidden"
                    >
                        Cobrar multas pendientes
                    </Link>
                </section>
            </div>
        </div>
    </div>
</template>
