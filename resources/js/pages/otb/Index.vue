<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Power, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import {
    pageShellClass,
    pageStatClass,
    pageStatLabelClass,
    pageStatValueClass,
    pageTitleClass,
} from '@/lib/pageStyles';

const { canManage } = usePermissions();

type OtbItem = {
    id: number;
    nombre: string;
    descripcion: string;
    afiliados: number;
    lotes: number;
    estado: string;
};

const props = defineProps<{
    otbs: OtbItem[];
    resumen: { afiliados: number; lotes: number };
    filtros?: { q?: string };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'OTB', href: '/otb' }],
    }),
});

const busqueda = ref(props.filtros?.q ?? '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const buscar = (replace = true) => {
    router.get(
        '/otb',
        { q: busqueda.value || undefined },
        { replace, preserveScroll: true },
    );
};

watch(busqueda, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => buscar(), 300);
});

const cambiarEstado = (otb: OtbItem) => {
    router.patch(`/otb/${otb.id}/estado`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="OTB" />

    <div :class="pageShellClass">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <MapPin class="size-5 text-white" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">OTB</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Sectores, barrios o sindicatos
                    </p>
                </div>
            </div>

            <div :class="pageStatClass">
                <span :class="pageStatLabelClass">Afiliados</span>
                <span :class="pageStatValueClass">{{ resumen.afiliados }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">persona(s)</span>
            </div>
            <div :class="pageStatClass">
                <span :class="pageStatLabelClass">Lotes</span>
                <span :class="pageStatValueClass">{{ resumen.lotes }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">propiedad(es)</span>
            </div>
        </div>

        <form class="flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="buscar(false)">
            <div class="relative w-full min-w-0 max-w-sm">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar por nombre, descripción o estado..."
                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-11 pr-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
                />
            </div>
            <Link
                v-if="canManage('afiliados.gestionar')"
                href="/otb/create"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
            >
                <Plus class="size-4" />
                Nueva OTB
            </Link>
        </form>

        <div class="min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table v-tabla-responsiva class="w-full md:min-w-[720px]">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                    <tr>
                        <th class="w-14 px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">N°</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Descripción</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Afiliados</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Lotes</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-if="otbs.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                            No hay OTB registradas.
                        </td>
                    </tr>
                    <tr v-for="(otb, index) in otbs" :key="otb.id" class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="px-4 py-3 text-center text-sm font-semibold text-cyan-600 dark:text-cyan-400">
                            {{ index + 1 }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-slate-50">{{ otb.nombre }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ otb.descripcion || '—' }}</td>
                        <td class="px-4 py-3 text-center text-sm text-slate-600 dark:text-slate-400">{{ otb.afiliados }}</td>
                        <td class="px-4 py-3 text-center font-mono text-sm text-slate-900 dark:text-slate-50">{{ otb.lotes }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-1 text-xs font-medium"
                                :class="
                                    otb.estado === 'Activa'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                                "
                            >
                                {{ otb.estado }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="canManage('afiliados.gestionar')" class="flex items-center gap-1">
                                <Link
                                    :href="`/otb/${otb.id}/edit`"
                                    class="rounded p-1 text-slate-600 hover:bg-slate-100 hover:text-cyan-600 dark:text-slate-400 dark:hover:bg-slate-800"
                                >
                                    <Pencil class="size-4" />
                                </Link>
                                <button
                                    type="button"
                                    :title="otb.estado === 'Activa' ? 'Pasar a inactiva' : 'Pasar a activa'"
                                    class="rounded p-1"
                                    :class="
                                        otb.estado === 'Activa'
                                            ? 'text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/40'
                                            : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800'
                                    "
                                    @click="cambiarEstado(otb)"
                                >
                                    <Power class="size-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400">
            El total de afiliados ({{ resumen.afiliados }}) coincide con el módulo Afiliados.
            La suma por OTB puede ser mayor si una persona tiene lotes en más de una OTB.
        </p>
    </div>
</template>
