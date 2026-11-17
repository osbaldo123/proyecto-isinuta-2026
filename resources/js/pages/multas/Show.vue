<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Receipt } from 'lucide-vue-next';
import { computed } from 'vue';
import FormPageShell from '@/components/FormPageShell.vue';
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();

const props = defineProps<{
    multa: {
        id: number;
        fecha_generacion: string;
        motivo: string;
        descripcion: string | null;
        monto: number;
        saldo: number;
        estado: string;
        gestion: number | null;
        cuotas_vencidas: number | null;
        afiliado: { id: number; nombre_completo: string; ci: string | null };
        lote_id: number | null;
        lote: { numero: string | null; otb: string | null } | null;
        trabajo: { tipo: string; fecha: string | null; otb: string | null; monto_multa: number } | null;
        generada_por: string | null;
        recibos: Array<{
            id: number;
            numero_recibo: string;
            fecha_pago: string | null;
            estado: string;
            monto: number;
        }>;
    };
    filtros_retorno?: { estado?: string | null; ci?: string | null; q?: string | null };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Multas', href: '/multas' },
            { title: 'Detalle', href: '#' },
        ],
    }),
});

const formatBs = (n: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);

const motivoLabel = (motivo: string) => {
    const map: Record<string, string> = {
        mora_trimestral: 'Mora trimestral',
        mora_anual: 'Mora anual',
        individual: 'Multa individual',
        falta_trabajo: 'Falta a trabajo',
    };

    return map[motivo] ?? motivo;
};

const estadoBadge = (estado: string) => {
    if (estado === 'pagada') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    }

    if (estado === 'anulada') {
        return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
    }

    return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200';
};

const listadoHref = computed(() => {
    const params = new URLSearchParams();
    const filtros = props.filtros_retorno ?? {};

    if (filtros.estado) {
        params.set('estado', filtros.estado);
    }

    if (filtros.ci) {
        params.set('ci', filtros.ci);
    }

    if (filtros.q) {
        params.set('q', filtros.q);
    }

    const qs = params.toString();

    return qs ? `/multas?${qs}` : '/multas';
});

const filas = computed(() => {
    const multa = props.multa;
    const items: Array<{ label: string; valor: string }> = [
        { label: 'Afiliado', valor: multa.afiliado.nombre_completo },
        { label: 'CI', valor: multa.afiliado.ci || '—' },
        { label: 'Fecha', valor: multa.fecha_generacion },
        { label: 'Motivo', valor: motivoLabel(multa.motivo) },
        { label: 'Descripción', valor: multa.descripcion?.trim() || '—' },
        { label: 'Monto', valor: formatBs(multa.monto) },
        { label: 'Saldo', valor: formatBs(multa.saldo) },
    ];

    if (multa.lote?.numero) {
        items.push({ label: 'Lote', valor: String(multa.lote.numero) });
    }

    if (multa.lote?.otb) {
        items.push({ label: 'OTB', valor: multa.lote.otb });
    }

    if (multa.gestion) {
        items.push({ label: 'Gestión', valor: String(multa.gestion) });
    }

    if (multa.cuotas_vencidas) {
        items.push({ label: 'Meses vencidos', valor: String(multa.cuotas_vencidas) });
    }

    if (multa.trabajo) {
        items.push({ label: 'Trabajo', valor: multa.trabajo.tipo });
        if (multa.trabajo.otb && multa.trabajo.otb !== multa.lote?.otb) {
            items.push({ label: 'OTB del trabajo', valor: multa.trabajo.otb });
        }
        if (multa.trabajo.fecha) {
            items.push({ label: 'Fecha del trabajo', valor: multa.trabajo.fecha });
        }
    }

    if (multa.generada_por) {
        items.push({ label: 'Registrada por', valor: multa.generada_por });
    }

    return items;
});

</script>

<template>
    <Head :title="`Multa de ${multa.afiliado.nombre_completo}`" />

    <FormPageShell
        :title="motivoLabel(multa.motivo)"
        :subtitle="multa.afiliado.nombre_completo"
        :back-href="listadoHref"
        :icon="Receipt"
        max-width="5xl"
    >
        <template #actions>
            <Link
                v-if="multa.estado === 'pendiente' && multa.lote_id && canManage('pagos.gestionar')"
                :href="`/pagos/registrar/nuevo?afiliado_id=${multa.lote_id}&multa_id=${multa.id}`"
                class="inline-flex items-center rounded-lg bg-cyan-500 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-600"
            >
                Cobrar esta multa
            </Link>
            <Link
                v-if="multa.recibos[0]"
                :href="`/pagos/${multa.recibos[0].id}`"
                class="inline-flex items-center rounded-lg border border-cyan-500/40 bg-cyan-500/10 px-3 py-2 text-sm font-medium text-cyan-800 hover:bg-cyan-500 hover:text-white dark:text-cyan-200"
            >
                Ver recibo
            </Link>
            <span class="rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="estadoBadge(multa.estado)">
                {{ multa.estado }}
            </span>
        </template>

        <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <dl class="divide-y divide-slate-200 dark:divide-slate-800">
                <div v-for="fila in filas" :key="fila.label" class="grid gap-1 px-4 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-baseline sm:gap-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ fila.label }}</dt>
                    <dd class="text-sm text-slate-900 dark:text-slate-50">{{ fila.valor }}</dd>
                </div>
            </dl>
        </div>

        <div
            v-if="multa.recibos.length > 0"
            class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
        >
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Recibos</h2>
            <ul class="mt-3 space-y-2">
                <li v-for="recibo in multa.recibos" :key="recibo.id">
                    <Link :href="`/pagos/${recibo.id}`" class="text-sm font-medium text-cyan-700 hover:underline dark:text-cyan-300">
                        Recibo {{ recibo.numero_recibo }}
                    </Link>
                    <p class="text-xs text-slate-500">
                        {{ recibo.fecha_pago || '—' }} · {{ formatBs(recibo.monto) }} · {{ recibo.estado }}
                    </p>
                </li>
            </ul>
        </div>
    </FormPageShell>
</template>
