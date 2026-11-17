<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import ReciboImpreso from '@/components/pagos/ReciboImpreso.vue';
import { formatBs, sumar, type ReciboDatos } from '@/lib/pagos';

const props = defineProps<{
    recibos: ReciboDatos[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Recibos', href: '/pagos' },
            { title: 'Imprimir cobro', href: '#' },
        ],
    }),
});

const primero = computed(() => props.recibos[0]);
const total = computed(() => sumar(props.recibos.filter((r) => r.estado !== 'anulado').map((r) => r.monto_total)));

const imprimir = () => window.print();
</script>

<template>
    <Head :title="`Recibos ${primero?.numero_recibo ?? ''}`" />

    <div class="min-h-[calc(100vh-4rem)] w-full bg-[#eef1f6] dark:bg-slate-950 print:min-h-0 print:bg-white">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 px-3 py-3 sm:gap-4 sm:px-6 sm:py-4 print:max-w-none print:p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div class="flex items-center gap-3">
                    <Link
                        v-if="primero"
                        :href="`/pagos/${primero.id}`"
                        class="inline-flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                        aria-label="Volver al recibo"
                    >
                        <ArrowLeft class="size-4" />
                    </Link>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-50">
                            {{ recibos.length === 1 ? '1 recibo' : `${recibos.length} recibos` }} de este cobro
                        </h1>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            {{ primero?.afiliado.nombre_completo }} · Total {{ formatBs(total) }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-cyan-600 px-4 text-sm font-semibold text-white transition hover:bg-cyan-700 dark:bg-cyan-400 dark:text-cyan-950 dark:hover:bg-cyan-300"
                    @click="imprimir"
                >
                    <Printer class="size-4" />
                    Imprimir todos
                </button>
            </div>

            <div id="area-impresion" class="flex flex-col gap-6 print:gap-0">
                <div v-for="recibo in recibos" :key="recibo.id" class="recibo-pagina">
                    <ReciboImpreso :recibo="recibo" />
                </div>
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
