<script setup lang="ts">
import { computed } from 'vue';
import { formatBs, type ReciboDatos } from '@/lib/pagos';

const props = defineProps<{
    recibo: ReciboDatos;
}>();

const anulado = computed(() => props.recibo.estado === 'anulado');
const fecha = computed(() => props.recibo.fecha_pago_display ?? props.recibo.fecha_pago.split('-').reverse().join('/'));
const loteYOtb = computed(
    () =>
        [
            props.recibo.afiliado.numero_lote ? `Lote ${props.recibo.afiliado.numero_lote}` : null,
            props.recibo.afiliado.otb ? `OTB ${props.recibo.afiliado.otb}` : null,
        ]
            .filter(Boolean)
            .join(' · ') || '—',
);
const hayMeses = computed(() => props.recibo.detalles.some((linea) => (linea.meses ?? 0) > 0));

const mesesDeLinea = (linea: ReciboDatos['detalles'][number]): string => {
    if (!linea.rango) {
        return '—';
    }

    return linea.unitario ? `${linea.rango} × ${formatBs(linea.unitario)}` : linea.rango;
};
</script>

<template>
    <article
        class="recibo-impreso relative overflow-hidden rounded-2xl bg-white px-6 pb-10 pt-20 text-slate-900 shadow-sm ring-1 ring-slate-200 sm:px-9 sm:pb-12 sm:pt-24 print:overflow-visible print:rounded-none print:shadow-none print:ring-0"
        :aria-label="`${recibo.titulo_recibo} ${recibo.numero_recibo}`"
    >
        <div
            v-if="anulado"
            aria-hidden="true"
            class="pointer-events-none absolute left-1/2 top-[46%] -translate-x-1/2 -translate-y-1/2 -rotate-[24deg] whitespace-nowrap text-7xl font-extrabold tracking-[0.08em] text-red-600/20 sm:text-8xl"
        >
            ANULADO
        </div>

        <div class="relative flex flex-col gap-5">
            <header
                class="grid grid-cols-1 items-start gap-3 rounded-xl bg-white/90 px-3 py-3 ring-1 ring-cyan-700/30 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-4"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <img src="/images/logo-isinuta.png" alt="" class="size-12 shrink-0 object-contain" />
                    <div class="min-w-0">
                        <p class="text-[15px] font-bold leading-tight">Asociación de Agua Potable y Alcantarillado «Isinuta»</p>
                        <p class="text-xs text-slate-600">{{ recibo.proposito_label }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-0.5 sm:items-end sm:text-right">
                    <p class="text-lg font-bold tracking-wide text-cyan-800">{{ recibo.titulo_recibo }}</p>
                    <p class="text-sm tabular-nums">N.º {{ recibo.numero_recibo }}</p>
                    <p class="text-[13px] text-slate-600">Fecha: {{ fecha }}</p>
                </div>
            </header>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 rounded-xl bg-white/90 px-3 py-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-slate-600">Recibido de</dt>
                    <dd class="font-semibold uppercase">{{ recibo.afiliado.nombre_completo }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-600">CI</dt>
                    <dd class="font-semibold">{{ recibo.afiliado.ci || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-600">Lote y OTB</dt>
                    <dd class="font-semibold">{{ loteYOtb }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-600">Categoría</dt>
                    <dd class="font-semibold capitalize">{{ recibo.afiliado.categoria || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-600">Método de pago</dt>
                    <dd class="font-semibold">{{ recibo.metodo_pago_label ?? recibo.metodo_pago }}</dd>
                </div>
            </dl>

            <div class="overflow-x-auto rounded-xl bg-white/90 px-3 py-2">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-slate-300 text-left text-xs font-semibold text-slate-600">
                            <th scope="col" class="py-2 pr-3">Concepto</th>
                            <th v-if="hayMeses" scope="col" class="py-2 pr-3">Meses pagados</th>
                            <th scope="col" class="py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(linea, indice) in recibo.detalles" :key="indice" class="border-b border-slate-200 align-top">
                            <td class="py-2.5 pr-3 font-medium">{{ linea.texto_base ?? linea.texto }}</td>
                            <td v-if="hayMeses" class="py-2.5 pr-3">{{ mesesDeLinea(linea) }}</td>
                            <td class="whitespace-nowrap py-2.5 text-right tabular-nums">{{ formatBs(linea.monto, true) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td :colspan="hayMeses ? 2 : 1" class="pb-1 pr-4 pt-3 text-right font-bold">Total</td>
                            <td class="whitespace-nowrap pb-1 pt-3 text-right text-lg font-bold tabular-nums">
                                {{ formatBs(recibo.monto_total, true) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="space-y-1 rounded-xl bg-white/90 px-3 py-2 text-sm">
                <p class="font-semibold">{{ recibo.monto_en_letras }}</p>
                <p v-if="recibo.observaciones" class="whitespace-pre-line text-slate-700">{{ recibo.observaciones }}</p>
                <p v-if="anulado && recibo.anulacion" class="font-semibold text-red-700">
                    Anulado el {{ recibo.anulacion.fecha }}<span v-if="recibo.anulacion.usuario"> por {{ recibo.anulacion.usuario }}</span
                    >. Motivo:
                    {{ recibo.anulacion.motivo }}
                </p>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-8 text-center text-[13px] text-slate-600">
                <p class="border-t border-slate-400 pt-2">Recibí conforme (caja)</p>
                <p class="border-t border-slate-400 pt-2">Entregué conforme (afiliado)</p>
            </div>

            <p class="text-xs text-slate-600">
                Emitido<span v-if="recibo.emitido_en"> el {{ recibo.emitido_en }}</span
                ><span v-if="recibo.cobrador"> por {{ recibo.cobrador }}</span
                >. Consulte su estado de cuenta en la oficina de la asociación.
            </p>
        </div>
    </article>
</template>

<style scoped>
.recibo-impreso {
    background-image: url('/images/recibo-fondo-isinuta.png');
    background-repeat: no-repeat;
    background-position: center top;
    background-size: 100% 100%;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
</style>
