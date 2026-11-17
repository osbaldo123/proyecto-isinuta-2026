<script setup lang="ts">
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Droplets, Pencil, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    servicio: {
        id: number;
        codigo: string;
        nombre: string;
        tipo: string;
        tipo_label: string;
        tarifa_mensual: number;
        tarifas_por_categoria: Array<{ categoria_id?: number; categoria: string; monto: number; lotes?: number }> | null;
        activo: boolean;
        lotes_count: number;
        creado_en: string | null;
        actualizado_en: string | null;
    };
    categoria_id?: number | null;
    puede_editar: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Servicios', href: '/servicios' },
            { title: 'Detalle', href: '#' },
        ],
    }),
});

const hrefEditar = computed(() => `/servicios/${props.servicio.id}/edit`);

const tarifas = computed(() => props.servicio.tarifas_por_categoria ?? []);

const formatBs = (n: number) => {
    try {
        return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);
    } catch {
        return `Bs ${Number(n).toFixed(2)}`;
    }
};
</script>

<template>
    <Head :title="`Servicio — ${servicio.nombre}`" />

    <div :class="[pageShellClass, 'mx-auto max-w-4xl']">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link href="/servicios" class="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <ArrowLeft class="size-5 text-slate-600 dark:text-slate-400" />
                </Link>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <Droplets class="size-5 text-white" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 :class="pageTitleClass">{{ servicio.nombre }}</h1>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                servicio.activo
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                    : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                            "
                        >
                            {{ servicio.activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Detalle del servicio</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="puede_editar"
                    :href="hrefEditar"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                >
                    <Pencil class="size-4" />
                    Editar
                </Link>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Datos del servicio</h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase text-slate-500">Nombre</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-50">{{ servicio.nombre }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase text-slate-500">Estado</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-50">
                        {{ servicio.activo ? 'Activo' : 'Inactivo' }}
                    </dd>
                </div>
            </dl>

            <div v-if="tarifas.length > 0" class="mt-5 space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categorías y tarifas</p>
                <div
                    v-for="(tarifa, index) in tarifas"
                    :key="tarifa.categoria_id ?? index"
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 last:border-0 last:pb-0 dark:border-slate-800"
                >
                    <div>
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-50">{{ tarifa.categoria }}</p>
                        <p class="text-xs text-slate-500">{{ tarifa.lotes ?? 0 }} lote(s)</p>
                    </div>
                    <p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">
                        {{ formatBs(Number(tarifa.monto) || 0) }}
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-1">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex items-center gap-2 text-slate-500">
                    <Users class="size-4" />
                    <span class="text-xs font-semibold uppercase">Lotes con este servicio</span>
                </div>
                <p :class="pageTitleClass">{{ servicio.lotes_count }}</p>
            </div>
        </section>

        <p class="text-xs text-slate-500">
            Creado: {{ servicio.creado_en || '—' }}
            <span v-if="servicio.actualizado_en"> · Actualizado: {{ servicio.actualizado_en }}</span>
        </p>
    </div>
</template>
