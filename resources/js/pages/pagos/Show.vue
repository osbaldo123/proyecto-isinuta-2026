<script setup lang="ts">
import ReciboImpreso from '@/components/pagos/ReciboImpreso.vue';
import { formatBs, sumar, type ReciboDatos } from '@/lib/pagos';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CircleCheck, FileSpreadsheet, History, Printer, ReceiptText } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    pago: ReciboDatos;
    recibos_emitidos?: Array<{
        id: number;
        numero_recibo: string;
        tipo_recibo: string;
        proposito_label: string | null;
    }> | null;
    filtros_retorno?: Record<string, string>;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Recibos', href: '/pagos' },
            { title: 'Recibo', href: '#' },
        ],
    }),
});

const anulado = computed(() => props.pago.estado === 'anulado');
const recibosDelCobro = computed(() => props.pago.recibos_del_cobro ?? []);
const totalDelCobro = computed(() => sumar(recibosDelCobro.value.filter((r) => r.estado !== 'anulado').map((r) => r.monto_total)));
const recienEmitidos = computed(() => props.recibos_emitidos ?? []);

const consulta = computed(() => {
    const params = new URLSearchParams();

    for (const [clave, valor] of Object.entries(props.filtros_retorno ?? {})) {
        if (valor) {
            params.set(clave, String(valor));
        }
    }

    const qs = params.toString();

    return qs ? `?${qs}` : '';
});

const listadoHref = computed(() => `/pagos${consulta.value}`);
const imprimirCobroHref = computed(() => (props.pago.cobro_id ? `/pagos/cobro/${props.pago.cobro_id}/imprimir` : null));
const estadoCuentaHref = computed(() => (props.pago.lote_afiliado_id ? `/pagos/estado-cuenta/${props.pago.lote_afiliado_id}` : null));

const imprimir = () => window.print();
</script>

<template>
    <Head :title="`${pago.titulo_recibo} ${pago.numero_recibo}`" />

    <div class="min-h-[calc(100vh-4rem)] w-full print:min-h-0 print:bg-white">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-3 py-3 sm:gap-4 sm:px-6 sm:py-4 lg:px-8 print:max-w-none print:p-0">
            <div class="flex flex-wrap items-end justify-between gap-4 print:hidden">
                <div class="flex min-w-0 items-start gap-3">
                    <Link
                        :href="listadoHref"
                        class="mt-1 inline-flex size-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-cyan-700 dark:hover:text-cyan-300"
                        aria-label="Volver a los recibos"
                    >
                        <ArrowLeft class="size-4" />
                    </Link>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-50">
                                {{ pago.tipo_recibo === 'ingreso' ? 'Recibo de ingreso' : 'Recibo de caja' }}
                            </h1>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold tracking-wide"
                                :class="
                                    anulado
                                        ? 'bg-orange-100 text-orange-800 dark:bg-orange-400/15 dark:text-orange-300'
                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300'
                                "
                            >
                                {{ anulado ? 'ANULADO' : 'EMITIDO' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                            <span class="font-mono">{{ pago.numero_recibo }}</span> · {{ pago.afiliado.nombre_completo }}
                            <span v-if="pago.afiliado.numero_lote"> · Lote {{ pago.afiliado.numero_lote }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-cyan-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-500 dark:bg-cyan-400 dark:text-cyan-950 dark:hover:bg-cyan-300"
                        @click="imprimir"
                    >
                        <Printer class="size-4" />
                        Imprimir
                    </button>
                    <a
                        :href="`/pagos/${pago.id}/export/xlsx`"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        <FileSpreadsheet class="size-4 text-emerald-600 dark:text-emerald-400" />
                        Excel
                    </a>
                </div>
            </div>

            <div
                v-if="recienEmitidos.length > 0"
                role="status"
                class="flex items-center gap-3 rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3.5 dark:border-emerald-400/40 dark:bg-emerald-400/10 print:hidden"
            >
                <CircleCheck class="size-5 shrink-0 text-emerald-600 dark:text-emerald-300" />
                <div>
                    <p class="text-[15px] font-semibold text-emerald-900 dark:text-emerald-100">
                        Se {{ recienEmitidos.length === 1 ? 'emitió 1 recibo' : `emitieron ${recienEmitidos.length} recibos` }}
                        <span v-if="recibosDelCobro.length > 0">por {{ formatBs(totalDelCobro) }}</span>
                    </p>
                    <p class="text-[13px] text-emerald-800 dark:text-emerald-200/80">
                        Los meses y multas cobrados ya figuran en el estado de cuenta.
                        <span v-if="recibosDelCobro.length > 1"> Use «Imprimir los {{ recibosDelCobro.length }} recibos» a la derecha para imprimirlos juntos.</span>
                    </p>
                </div>
            </div>

            <div
                v-if="anulado && pago.anulacion"
                role="status"
                class="flex items-start gap-3 rounded-2xl border border-orange-300 bg-orange-50 px-4 py-3.5 text-sm dark:border-orange-400/50 dark:bg-orange-400/10 print:hidden"
            >
                <Ban class="mt-0.5 size-5 shrink-0 text-orange-600 dark:text-orange-300" />
                <div class="space-y-1">
                    <p class="font-semibold text-orange-900 dark:text-orange-100">
                        Anulado el {{ pago.anulacion.fecha }}<span v-if="pago.anulacion.usuario"> por {{ pago.anulacion.usuario }}</span>
                    </p>
                    <p class="text-orange-800 dark:text-orange-200">Motivo: {{ pago.anulacion.motivo }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div id="area-impresion" class="min-w-0">
                    <div class="recibo-pagina">
                        <ReciboImpreso :recibo="pago" />
                    </div>
                </div>

                <aside class="flex min-w-0 flex-col gap-4 print:hidden">
                    <section
                        v-if="recibosDelCobro.length > 1"
                        aria-labelledby="cobro-titulo"
                        class="flex flex-col gap-2.5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 id="cobro-titulo" class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-slate-50">
                            <ReceiptText class="size-4 text-cyan-600 dark:text-cyan-400" />
                            Recibos de este cobro
                        </h2>
                        <Link
                            v-for="recibo in recibosDelCobro"
                            :key="recibo.id"
                            :href="`/pagos/${recibo.id}${consulta}`"
                            :aria-current="recibo.id === pago.id ? 'page' : undefined"
                            class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5 text-[13px] transition"
                            :class="
                                recibo.id === pago.id
                                    ? 'border-cyan-400 bg-cyan-50 text-slate-900 dark:border-cyan-400/70 dark:bg-cyan-400/10 dark:text-slate-50'
                                    : 'border-slate-200 bg-slate-50 text-slate-800 hover:border-cyan-300 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-200 dark:hover:border-cyan-700'
                            "
                        >
                            <span class="flex min-w-0 flex-col">
                                <strong class="font-mono" :class="recibo.estado === 'anulado' ? 'line-through' : ''">{{
                                    recibo.numero_recibo
                                }}</strong>
                                <span class="truncate text-slate-600 dark:text-slate-400">
                                    {{ recibo.tipo_recibo === 'ingreso' ? 'Ingreso' : 'Caja' }} · {{ recibo.proposito_label }}
                                    <span v-if="recibo.estado === 'anulado'" class="font-semibold text-orange-700 dark:text-orange-300">
                                        · anulado</span
                                    >
                                </span>
                            </span>
                            <strong class="shrink-0 tabular-nums" :class="recibo.estado === 'anulado' ? 'text-slate-400 line-through' : ''">
                                {{ formatBs(recibo.monto_total) }}
                            </strong>
                        </Link>
                        <a
                            v-if="imprimirCobroHref"
                            :href="imprimirCobroHref"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 text-sm font-medium text-slate-800 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800"
                        >
                            <Printer class="size-4" />
                            Imprimir los {{ recibosDelCobro.length }} recibos
                        </a>
                    </section>

                    <section
                        aria-labelledby="historial-titulo"
                        class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 id="historial-titulo" class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-slate-50">
                            <History class="size-4 text-cyan-600 dark:text-cyan-400" />
                            Historial
                        </h2>
                        <div class="flex gap-2.5 text-[13px]">
                            <span aria-hidden="true" class="mt-1 size-2.5 shrink-0 rounded-full bg-emerald-500" />
                            <span class="flex flex-col">
                                <strong class="text-slate-900 dark:text-slate-50">Emitido</strong>
                                <span class="text-slate-600 dark:text-slate-400">
                                    {{ pago.emitido_en ?? pago.fecha_pago_display }}
                                    <span v-if="pago.cobrador"> · {{ pago.cobrador }}</span>
                                    · {{ pago.metodo_pago_label ?? pago.metodo_pago }}
                                </span>
                            </span>
                        </div>
                        <div v-if="anulado && pago.anulacion" class="flex gap-2.5 text-[13px]">
                            <span aria-hidden="true" class="mt-1 size-2.5 shrink-0 rounded-full bg-orange-500" />
                            <span class="flex flex-col">
                                <strong class="text-slate-900 dark:text-slate-50">Anulado</strong>
                                <span class="text-slate-600 dark:text-slate-400">
                                    {{ pago.anulacion.fecha }}<span v-if="pago.anulacion.usuario"> · {{ pago.anulacion.usuario }}</span>
                                </span>
                                <span class="text-orange-700 dark:text-orange-300">{{ pago.anulacion.motivo }}</span>
                            </span>
                        </div>
                        <Link
                            v-if="estadoCuentaHref"
                            :href="estadoCuentaHref"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 text-sm font-medium text-slate-800 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800"
                        >
                            Ver estado de cuenta del lote
                        </Link>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</template>

<style>
/* Reglas de impresión de recibos (N páginas): resources/css/app.css — #area-impresion */
@media print {
    @page {
        size: A4;
        margin: 12mm;
    }
}
</style>
