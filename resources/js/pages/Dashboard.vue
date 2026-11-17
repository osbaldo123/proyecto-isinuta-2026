<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    BarChart3,
    Droplets,
    Receipt,
    TrendingUp,
    UserRound,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps<{
    institucion: string;
    usuario: string;
    resumen: {
        afiliados_activos: number;
        afiliados_cortados: number;
        cortados_origen?: 'corte' | 'sin_pago';
        multas_pendientes: number;
        pagos_hoy: number;
        pagos_hoy_fecha?: string;
        pagos_mes: number;
        afiliados_pagaron_mes?: number;
        afiliados_pendientes_pago?: number;
    };
    alertas_deuda: Array<{
        id: number;
        lote_id?: number | null;
        ci: string;
        nombre_completo: string;
        otb: string | null;
        total_deuda: number;
    }>;
    pagos_recientes: Array<{
        id: number;
        numero_recibo: string;
        monto_total: number;
        fecha_pago: string;
        afiliado: string;
        conceptos: string[];
    }>;
    es_presidente?: boolean;
    mes_pago_label?: string;
    pagos_del_mes?: Array<{
        id: number;
        lote_id?: number | null;
        ci: string;
        nombre_completo: string;
        otb: string | null;
        total_pagado: number;
        recibos: number;
        ultima_fecha: string;
    }>;
    estadistica_anual?: {
        anio: number;
        total: number;
        recibos: number;
        promedio_mensual: number;
        mejor_mes: {
            mes: number;
            label: string;
            monto: number;
            recibos: number;
        } | null;
        meses: Array<{
            mes: number;
            label: string;
            monto: number;
            recibos: number;
        }>;
    };
    enlaces_resumen?: {
        activos: string;
        cortados: string;
        pagos_hoy: string;
        pagos_mes: string;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Panel', href: '/dashboard' }],
    }),
});

const { can, canManage } = usePermissions();

const formatBs = (monto: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(monto);

/** Agrupa conceptos repetidos: 15× "Consumo de agua" → una sola etiqueta. */
const resumirConceptos = (conceptos: string[]): Array<{ texto: string; etiqueta: string }> => {
    const conteo = new Map<string, number>();

    for (const raw of conceptos) {
        const texto = raw.trim();
        if (!texto) {
            continue;
        }
        conteo.set(texto, (conteo.get(texto) ?? 0) + 1);
    }

    return [...conteo.entries()].map(([texto, cantidad]) => ({
        texto,
        etiqueta: cantidad > 1 ? `${texto} (${cantidad})` : texto,
    }));
};

const formatCompact = (monto: number) =>
    new Intl.NumberFormat('es-BO', {
        notation: monto >= 1000 ? 'compact' : 'standard',
        maximumFractionDigits: 1,
    }).format(monto);

const saludo = computed(() => {
    const hora = new Date().getHours();

    if (hora < 12) {
        return 'Buenos días';
    }

    if (hora < 19) {
        return 'Buenas tardes';
    }

    return 'Buenas noches';
});

const maxMontoAnual = computed(() => {
    const meses = props.estadistica_anual?.meses ?? [];
    const max = Math.max(0, ...meses.map((m) => m.monto));

    return max > 0 ? max : 1;
});

const hrefCobrarDeuda = (item: { lote_id?: number | null; id: number }) => {
    const selector = item.lote_id ?? item.id;

    return `/pagos/registrar/nuevo?afiliado_id=${selector}`;
};

const hrefRecibosAfiliado = (item: { lote_id?: number | null; id: number }) => {
    const selector = item.lote_id ?? item.id;

    return `/pagos?afiliado_id=${selector}`;
};

const claseTarjetaClic =
    'block rounded-xl border border-slate-100 px-4 py-3 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500';

/** Agrupa conceptos repetidos (p. ej. 15 meses de agua → 1 etiqueta) para una lista limpia. */
const resumenConceptos = (conceptos: string[]): string[] => {
    const conteo = new Map<string, number>();

    for (const raw of conceptos) {
        const label = raw.trim();
        if (!label) {
            continue;
        }
        conteo.set(label, (conteo.get(label) ?? 0) + 1);
    }

    return [...conteo.entries()].map(([label, n]) => (n > 1 ? `${label} (${n})` : label));
};

const pagosRecientesLista = computed(() =>
    props.pagos_recientes.map((pago) => {
        const conceptos = resumenConceptos(pago.conceptos);

        return {
            ...pago,
            conceptosResumen: conceptos.slice(0, 3),
            conceptosExtra: Math.max(0, conceptos.length - 3),
        };
    }),
);

const alturaBarra = (monto: number) => {
    const pct = Math.round((monto / maxMontoAnual.value) * 100);

    return `${Math.max(monto > 0 ? 8 : 2, pct)}%`;
};

const mesActual = computed(() => new Date().getMonth() + 1);

const enlaceActivos = computed(() => props.enlaces_resumen?.activos ?? '/afiliados?estado=activo');
const enlaceCortados = computed(() => props.enlaces_resumen?.cortados ?? '/afiliados?estado=cortado');
const enlacePagosHoy = computed(() => props.enlaces_resumen?.pagos_hoy ?? '/pagos');
const enlacePagosMes = computed(() => props.enlaces_resumen?.pagos_mes ?? '/pagos');
const tarjetaKpiBase =
    'rounded-2xl border p-3 shadow-sm transition hover:shadow-md sm:p-4';
const kpiFila =
    'flex w-full items-center justify-center gap-3 sm:items-start sm:justify-between sm:gap-4';
const kpiTexto = 'min-w-0 text-center sm:text-left';
const kpiMeta = 'mt-1 flex items-center justify-center gap-1 text-xs sm:mt-1.5 sm:justify-start';
const kpiIconoCaja = 'flex size-9 shrink-0 items-center justify-center rounded-xl text-white shadow-sm sm:size-11';
const kpiIcono = 'size-4 sm:size-5';

const fechaCierre = computed(() => {
    const fecha = props.resumen.pagos_hoy_fecha;
    if (! fecha) {
        return '';
    }

    return fecha.split('-').reverse().join('/');
});

const pagosHoyEsHoy = computed(() => {
    const hoy = new Date().toISOString().slice(0, 10);

    return !props.resumen.pagos_hoy_fecha || props.resumen.pagos_hoy_fecha === hoy;
});
</script>

<template>
    <Head title="Panel" />

    <div class="dashboard-page min-h-[calc(100vh-4rem)] w-full min-w-0 overflow-x-hidden px-3 pb-4 pt-0 text-slate-900 sm:px-6">
        <div class="flex w-full min-w-0 flex-col gap-3 sm:gap-4">
            <!-- Encabezado -->
            <div
                class="relative overflow-hidden rounded-b-2xl border-x border-b border-cyan-200/60 shadow-[0_8px_28px_rgba(14,116,144,0.14)]"
            >
                <div
                    class="absolute inset-0 bg-cover bg-right bg-no-repeat"
                    style="background-image: url('/images/dashboard-hero-agua.jpg')"
                    aria-hidden="true"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-r from-slate-950/75 via-cyan-950/55 to-cyan-900/25"
                    aria-hidden="true"
                />
                <div
                    class="absolute -right-10 -top-16 size-48 rounded-full bg-cyan-300/20 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    class="absolute -bottom-20 left-10 size-40 rounded-full bg-sky-200/15 blur-3xl"
                    aria-hidden="true"
                />

                <div class="relative z-10 flex flex-col gap-4 px-4 py-6 sm:px-6 sm:py-7 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0 max-w-2xl space-y-2">
                        <p class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-cyan-50 backdrop-blur-sm">
                            <Droplets class="size-3.5 text-cyan-200" />
                            ISINUTA
                        </p>
                        <h1 class="break-words text-xl font-bold tracking-tight text-white drop-shadow-sm sm:text-2xl lg:text-3xl">
                            {{ saludo }},
                            <span class="text-cyan-200">{{ usuario }}</span>
                        </h1>
                        <p class="max-w-xl text-sm text-cyan-50/90 sm:text-[15px]">
                            Resumen de afiliados, cobros y alertas de {{ institucion }}.
                        </p>
                    </div>

                    <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-wrap">
                        <Link
                            v-if="can('afiliados.ver')"
                            href="/afiliados"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/35 bg-white/15 px-4 py-2.5 text-sm font-medium text-white backdrop-blur-md transition hover:bg-white/25 sm:w-auto sm:py-2"
                        >
                            <Users class="size-4 shrink-0" />
                            Afiliados
                        </Link>
                        <Link
                            v-if="canManage('pagos.gestionar')"
                            href="/pagos/registrar/nuevo"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-lg shadow-cyan-900/25 transition hover:bg-cyan-300 sm:w-auto sm:py-2"
                        >
                            <Receipt class="size-4 shrink-0" />
                            Registrar pago
                        </Link>
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    :href="enlaceActivos"
                    class="block border-cyan-100 bg-cyan-50 hover:border-cyan-200 dark:border-cyan-900/40 dark:bg-cyan-950/40 dark:hover:border-cyan-700"
                    :class="tarjetaKpiBase"
                >
                    <div :class="kpiFila">
                        <div :class="kpiTexto">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-cyan-700/70 dark:text-cyan-300/70">
                                Afiliados activos
                            </p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 sm:mt-1.5 sm:text-3xl dark:text-white">
                                {{ resumen.afiliados_activos }}
                            </p>
                            <p :class="[kpiMeta, 'text-emerald-600 dark:text-emerald-400']">
                                <TrendingUp class="size-3.5" />
                                En servicio
                            </p>
                        </div>
                        <div :class="[kpiIconoCaja, 'bg-cyan-500 shadow-cyan-500/30']">
                            <Users :class="kpiIcono" />
                        </div>
                    </div>
                </Link>

                <Link
                    :href="enlaceCortados"
                    class="block border-red-100 bg-red-50 hover:border-red-200 dark:border-red-900/40 dark:bg-red-950/40 dark:hover:border-red-700"
                    :class="tarjetaKpiBase"
                >
                    <div :class="kpiFila">
                        <div :class="kpiTexto">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-red-700/70 dark:text-red-300/70">
                                {{ resumen.cortados_origen === 'sin_pago' ? 'Sin pago del mes' : 'Cortados' }}
                            </p>
                            <p class="mt-1 text-2xl font-bold text-red-600 sm:mt-1.5 sm:text-3xl dark:text-red-400">
                                {{ resumen.afiliados_cortados }}
                            </p>
                            <p :class="[kpiMeta, 'text-amber-600 dark:text-amber-400']">
                                <AlertTriangle class="size-3.5" />
                                Requieren atención
                            </p>
                        </div>
                        <div :class="[kpiIconoCaja, 'bg-red-500 shadow-red-500/30']">
                            <Droplets :class="kpiIcono" />
                        </div>
                    </div>
                </Link>

                <Link
                    :href="es_presidente ? enlacePagosMes : enlacePagosHoy"
                    class="block border-emerald-100 bg-emerald-50 hover:border-emerald-200 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:hover:border-emerald-700"
                    :class="tarjetaKpiBase"
                >
                    <div :class="kpiFila">
                        <div :class="kpiTexto">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700/70 dark:text-emerald-300/70">
                                {{ es_presidente ? `Pagaron en ${mes_pago_label}` : 'Pagos hoy' }}
                            </p>
                            <p class="mt-1 break-words text-lg font-bold tabular-nums text-slate-900 sm:mt-1.5 sm:text-xl lg:text-2xl dark:text-white">
                                <template v-if="es_presidente">
                                    {{ resumen.afiliados_pagaron_mes ?? 0 }}
                                </template>
                                <template v-else>
                                    {{ formatBs(resumen.pagos_hoy) }}
                                </template>
                            </p>
                            <p :class="[kpiMeta, 'text-emerald-600 dark:text-emerald-400']">
                                <TrendingUp class="size-3.5" />
                                {{ es_presidente ? 'Afiliados con pago' : pagosHoyEsHoy ? 'Cierre del día' : `Cierre ${fechaCierre}` }}
                            </p>
                        </div>
                        <div :class="[kpiIconoCaja, 'bg-emerald-500 shadow-emerald-500/30']">
                            <Wallet :class="kpiIcono" />
                        </div>
                    </div>
                </Link>

                <Link
                    :href="es_presidente ? enlaceActivos : enlacePagosMes"
                    class="block"
                    :class="[
                        tarjetaKpiBase,
                        es_presidente
                            ? 'border-amber-100 bg-amber-50 hover:border-amber-200 dark:border-amber-900/40 dark:bg-amber-950/40 dark:hover:border-amber-700'
                            : 'border-blue-100 bg-blue-50 hover:border-blue-200 dark:border-blue-900/40 dark:bg-blue-950/40 dark:hover:border-blue-700',
                    ]"
                >
                    <div :class="kpiFila">
                        <div :class="kpiTexto">
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wide"
                                :class="es_presidente ? 'text-amber-700/70 dark:text-amber-300/70' : 'text-blue-700/70 dark:text-blue-300/70'"
                            >
                                {{ es_presidente ? 'Pendientes de pago' : 'Pagos del mes' }}
                            </p>
                            <p
                                class="mt-1 break-words text-lg font-bold tabular-nums sm:mt-1.5 sm:text-xl lg:text-2xl"
                                :class="es_presidente ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white'"
                            >
                                <template v-if="es_presidente">
                                    {{ resumen.afiliados_pendientes_pago ?? 0 }}
                                </template>
                                <template v-else>
                                    {{ formatBs(resumen.pagos_mes) }}
                                </template>
                            </p>
                            <p
                                :class="[
                                    kpiMeta,
                                    es_presidente ? 'text-amber-600 dark:text-amber-400' : 'text-blue-600 dark:text-blue-400',
                                ]"
                            >
                                <component :is="es_presidente ? AlertTriangle : TrendingUp" class="size-3.5" />
                                {{ es_presidente ? `Cuota de ${mes_pago_label}` : 'Acumulado mensual' }}
                            </p>
                        </div>
                        <div
                            :class="[
                                kpiIconoCaja,
                                es_presidente ? 'bg-amber-500 shadow-amber-500/30' : 'bg-blue-500 shadow-blue-500/30',
                            ]"
                        >
                            <Receipt :class="kpiIcono" />
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Estadística anual -->
            <div
                v-if="estadistica_anual"
                class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-5"
            >
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex size-9 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600">
                            <BarChart3 class="size-4" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 sm:text-lg">Estadística anual {{ estadistica_anual.anio }}</h2>
                            <p class="text-sm text-slate-500">Ingresos por mes (recibos emitidos)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total año</p>
                            <p class="text-base font-bold text-cyan-600">{{ formatBs(estadistica_anual.total) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Recibos</p>
                            <p class="text-base font-bold text-slate-900">{{ estadistica_anual.recibos }}</p>
                        </div>
                        <div class="col-span-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 sm:col-span-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Promedio mensual</p>
                            <p class="text-base font-bold text-slate-900">{{ formatBs(estadistica_anual.promedio_mensual) }}</p>
                        </div>
                    </div>
                </div>

                <!--
                  Scroll solo en X. pt-14 deja hueco al tooltip (si no, overflow lo corta).
                  El tooltip va sobre la barra, dentro de ese espacio.
                -->
                <div class="-mx-1 overflow-x-auto px-1 pb-1 pt-14 [scrollbar-width:thin]">
                    <div class="flex h-48 min-w-[34rem] items-end gap-1.5 sm:h-60 sm:min-w-0 sm:gap-2">
                        <div
                            v-for="item in estadistica_anual.meses"
                            :key="item.mes"
                            class="group relative flex min-w-0 flex-1 flex-col items-center justify-end gap-1.5"
                        >
                            <div
                                class="pointer-events-none absolute bottom-[calc(100%-0.25rem)] left-1/2 z-20 hidden w-max max-w-[11rem] -translate-x-1/2 rounded-lg bg-slate-900 px-2.5 py-1.5 text-center text-xs text-white shadow-lg group-hover:block group-focus-within:block"
                            >
                                <p class="font-semibold">{{ item.label }} {{ estadistica_anual.anio }}</p>
                                <p>{{ formatBs(item.monto) }}</p>
                                <p class="text-slate-300">{{ item.recibos }} recibo(s)</p>
                            </div>

                            <span
                                v-if="item.monto > 0"
                                class="hidden text-xs font-semibold tabular-nums text-slate-500 sm:block"
                            >
                                {{ formatCompact(item.monto) }}
                            </span>

                            <div class="relative flex h-36 w-full items-end justify-center sm:h-44">
                                <div
                                    class="w-full max-w-[2.25rem] rounded-t-md transition-all duration-300"
                                    :class="
                                        item.mes === mesActual
                                            ? 'bg-cyan-500 shadow-md shadow-cyan-300/40'
                                            : item.monto > 0
                                              ? 'bg-cyan-400/80 group-hover:bg-cyan-500'
                                              : 'bg-slate-200'
                                    "
                                    :style="{ height: alturaBarra(item.monto) }"
                                    :title="`${item.label}: ${formatBs(item.monto)}`"
                                />
                            </div>

                            <span
                                class="text-[10px] font-semibold uppercase tracking-wide sm:text-xs"
                                :class="item.mes === mesActual ? 'text-cyan-700' : 'text-slate-400'"
                            >
                                {{ item.label }}
                            </span>
                        </div>
                    </div>
                </div>

                <p
                    v-if="estadistica_anual.mejor_mes"
                    class="mt-4 text-center text-sm text-slate-500 sm:text-left"
                >
                    Mejor mes:
                    <strong class="text-slate-800">
                        {{ estadistica_anual.mejor_mes.label }} ({{ formatBs(estadistica_anual.mejor_mes.monto) }})
                    </strong>
                </p>
            </div>

            <!-- Paneles inferiores -->
            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 sm:gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-500">
                                <AlertTriangle class="size-4" />
                            </div>
                            <h2 class="min-w-0 truncate font-semibold text-slate-900">Afiliados con deuda</h2>
                        </div>
                        <Link
                            v-if="can('reportes.ver')"
                            href="/reportes/deudas"
                            class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-cyan-600 hover:text-cyan-700"
                        >
                            Ver reporte
                            <ArrowUpRight class="size-4" />
                        </Link>
                    </div>

                    <div v-if="alertas_deuda.length === 0" class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500">
                        No hay afiliados con deuda pendiente.
                    </div>

                    <ul v-else class="space-y-3">
                        <li v-for="item in alertas_deuda" :key="item.id">
                            <Link
                                v-if="canManage('pagos.gestionar')"
                                :href="hrefCobrarDeuda(item)"
                                :class="claseTarjetaClic"
                                :title="`Cobrar deuda de ${item.nombre_completo}`"
                            >
                                <div class="flex items-center justify-between gap-2 sm:gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium text-slate-900">{{ item.nombre_completo }}</p>
                                        <p class="truncate text-xs text-slate-500">
                                            CI {{ item.ci }}
                                            <span v-if="item.otb">· {{ item.otb }}</span>
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-base font-bold tabular-nums text-red-600 sm:text-lg">{{ formatBs(item.total_deuda) }}</p>
                                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Cobrar</p>
                                    </div>
                                </div>
                            </Link>
                            <div v-else class="flex items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-3 sm:gap-3 sm:px-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-slate-900">{{ item.nombre_completo }}</p>
                                    <p class="truncate text-xs text-slate-500">
                                        CI {{ item.ci }}
                                        <span v-if="item.otb">· {{ item.otb }}</span>
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-base font-bold tabular-nums text-red-600 sm:text-lg">{{ formatBs(item.total_deuda) }}</p>
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Deuda</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 sm:gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <Receipt class="size-4" />
                            </div>
                            <h2 class="min-w-0 truncate font-semibold text-slate-900">
                                <template v-if="es_presidente">
                                    Pago del mes de {{ mes_pago_label }}
                                </template>
                                <template v-else>
                                    Pagos recientes
                                </template>
                            </h2>
                        </div>
                        <Link
                            v-if="can('pagos.ver')"
                            href="/pagos"
                            class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-cyan-600 hover:text-cyan-700"
                        >
                            Ver pagos
                            <ArrowUpRight class="size-4" />
                        </Link>
                    </div>

                    <!-- Solo presidente: resumen grande pagaron / pendientes -->
                    <div v-if="es_presidente" class="mb-3 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700/70">
                                        Pagaron en {{ mes_pago_label }}
                                    </p>
                                    <p class="mt-1.5 text-3xl font-bold text-slate-900">
                                        {{ resumen.afiliados_pagaron_mes ?? 0 }}
                                    </p>
                                    <p class="mt-1.5 flex items-center gap-1 text-xs text-emerald-600">
                                        <TrendingUp class="size-3.5" />
                                        Afiliados con pago
                                    </p>
                                </div>
                                <div class="flex size-11 items-center justify-center rounded-xl bg-emerald-500 text-white">
                                    <Wallet class="size-5" />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700/70">
                                        Pendientes de pago
                                    </p>
                                    <p class="mt-1.5 text-3xl font-bold text-amber-600">
                                        {{ resumen.afiliados_pendientes_pago ?? 0 }}
                                    </p>
                                    <p class="mt-1.5 flex items-center gap-1 text-xs text-amber-600">
                                        <AlertTriangle class="size-3.5" />
                                        Cuota de {{ mes_pago_label }}
                                    </p>
                                </div>
                                <div class="flex size-11 items-center justify-center rounded-xl bg-amber-500 text-white">
                                    <Receipt class="size-5" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Solo presidente: afiliados que pagaron en el mes -->
                    <template v-if="es_presidente">
                        <div
                            v-if="!pagos_del_mes || pagos_del_mes.length === 0"
                            class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500"
                        >
                            Ningún afiliado ha realizado pagos en {{ mes_pago_label }}.
                        </div>

                        <ul v-else class="space-y-3">
                            <li v-for="item in pagos_del_mes" :key="item.id">
                                <Link
                                    v-if="can('pagos.ver')"
                                    :href="hrefRecibosAfiliado(item)"
                                    :class="claseTarjetaClic"
                                    :title="`Ver recibos de ${item.nombre_completo}`"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-900">{{ item.nombre_completo }}</p>
                                            <p class="truncate text-xs text-slate-500">
                                                CI {{ item.ci }}
                                                <span v-if="item.otb">· {{ item.otb }}</span>
                                                · {{ item.ultima_fecha }}
                                                · {{ item.recibos }} recibo(s)
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-base font-bold tabular-nums text-cyan-600 sm:text-lg">{{ formatBs(item.total_pagado) }}</p>
                                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Ver recibos</p>
                                        </div>
                                    </div>
                                </Link>
                                <div v-else class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-900">{{ item.nombre_completo }}</p>
                                        <p class="truncate text-xs text-slate-500">
                                            CI {{ item.ci }}
                                            <span v-if="item.otb">· {{ item.otb }}</span>
                                            · {{ item.ultima_fecha }}
                                            · {{ item.recibos }} recibo(s)
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-base font-bold tabular-nums text-cyan-600 sm:text-lg">{{ formatBs(item.total_pagado) }}</p>
                                        <p class="text-[10px] uppercase tracking-wide text-slate-400">Pagado</p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </template>

                    <!-- Otros roles: pagos recientes (lista compacta) -->
                    <template v-else>
                        <div v-if="pagosRecientesLista.length === 0" class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500">
                            Aún no hay pagos registrados.
                        </div>

                        <ul v-else class="space-y-2.5">
                            <li v-for="pago in pagosRecientesLista" :key="pago.id">
                                <Link
                                    v-if="can('pagos.ver')"
                                    :href="`/pagos/${pago.id}`"
                                    :class="claseTarjetaClic"
                                    :title="`Ver recibo ${pago.numero_recibo}`"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-base font-bold tabular-nums text-cyan-600 sm:text-lg">{{ formatBs(pago.monto_total) }}</p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ pago.fecha_pago }} · {{ pago.afiliado }}
                                            </p>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-medium text-slate-600">
                                            {{ pago.numero_recibo }}
                                        </span>
                                    </div>
                                    <div v-if="pago.conceptosResumen.length" class="mt-2 flex flex-wrap gap-1.5">
                                        <span
                                            v-for="(concepto, index) in pago.conceptosResumen"
                                            :key="`${pago.id}-${index}`"
                                            class="max-w-full truncate rounded-full bg-cyan-50 px-2.5 py-0.5 text-[11px] font-medium text-cyan-700"
                                        >
                                            {{ concepto }}
                                        </span>
                                        <span
                                            v-if="pago.conceptosExtra > 0"
                                            class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-500"
                                        >
                                            +{{ pago.conceptosExtra }} más
                                        </span>
                                    </div>
                                </Link>
                                <div v-else class="rounded-xl border border-slate-100 px-4 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-base font-bold tabular-nums text-cyan-600 sm:text-lg">{{ formatBs(pago.monto_total) }}</p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ pago.fecha_pago }} · {{ pago.afiliado }}
                                            </p>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-medium text-slate-600">
                                            {{ pago.numero_recibo }}
                                        </span>
                                    </div>
                                    <div v-if="pago.conceptosResumen.length" class="mt-2 flex flex-wrap gap-1.5">
                                        <span
                                            v-for="(concepto, index) in pago.conceptosResumen"
                                            :key="`${pago.id}-${index}`"
                                            class="max-w-full truncate rounded-full bg-cyan-50 px-2.5 py-0.5 text-[11px] font-medium text-cyan-700"
                                        >
                                            {{ concepto }}
                                        </span>
                                        <span
                                            v-if="pago.conceptosExtra > 0"
                                            class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-500"
                                        >
                                            +{{ pago.conceptosExtra }} más
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </template>
                </div>
            </div>

            <div
                v-if="resumen.multas_pendientes > 0"
                class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900"
            >
                <UserRound class="size-5 shrink-0" />
                <p>
                    Hay <strong>{{ resumen.multas_pendientes }}</strong> multa(s) pendientes de cobro.
                    <Link v-if="can('multas.ver')" href="/multas" class="ml-1 font-medium text-cyan-700 hover:underline">
                        Revisar multas
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dashboard-page,
.dashboard-page :deep(*) {
    color-scheme: light;
}
</style>
