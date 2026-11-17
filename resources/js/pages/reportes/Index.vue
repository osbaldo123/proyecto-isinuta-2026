<script setup lang="ts">
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, ExternalLink, Landmark, Wallet } from 'lucide-vue-next';

defineProps<{
    reportes: Array<{
        id: number;
        titulo: string;
        descripcion?: string;
        tipo: string;
        fecha: string;
        estado: string;
        href?: string;
    }>;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Reportes', href: '/reportes' }],
    }),
});

const iconoReporte = (tipo: string) => (tipo === 'Deudas' ? Landmark : Wallet);
</script>

<template>
    <Head title="Reportes" />

    <div :class="pageShellClass">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                <BarChart3 class="size-5 text-white" />
            </div>
            <div>
                <h1 :class="pageTitleClass">Reportes</h1>
            </div>
        </div>

        <div
            v-if="reportes.length === 0"
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900"
        >
            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                No hay reportes disponibles para su rol.
            </p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Solicite al presidente que asigne el permiso de reportes correspondiente.
            </p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 max-w-3xl">
            <component
                :is="reporte.href ? Link : 'div'"
                v-for="reporte in reportes"
                :key="reporte.id"
                :href="reporte.href"
                class="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-cyan-400/50 hover:bg-cyan-500/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-cyan-500/40 dark:hover:bg-cyan-500/10"
            >
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg"
                        :class="reporte.tipo === 'Deudas' ? 'bg-amber-500/10 text-amber-700' : 'bg-cyan-500/10 text-cyan-600'"
                    >
                        <component :is="iconoReporte(reporte.tipo)" class="size-4" />
                    </div>
                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-200">
                        {{ reporte.estado }}
                    </span>
                </div>
                <h2 class="font-semibold text-slate-900 dark:text-slate-50">{{ reporte.titulo }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ reporte.descripcion }}
                </p>
                <span
                    v-if="reporte.href"
                    class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-cyan-600"
                >
                    Abrir
                    <ExternalLink class="size-3.5" />
                </span>
            </component>
        </div>
    </div>
</template>
