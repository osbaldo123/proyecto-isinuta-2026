<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BadgeDollarSign, Pencil, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const { canManage } = usePermissions();

type Tipo = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string;
    modalidad: string;
    monto: number;
    monto_plazo: number;
    lotes: number;
    estado: string;
    orden: number;
};

const props = defineProps<{
    tipos: Tipo[];
    filtros?: { q?: string };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Afiliaciones', href: '/afiliaciones' }],
    }),
});

const busqueda = ref(props.filtros?.q ?? '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const buscar = (replace = true) => {
    router.get(
        '/afiliaciones',
        { q: busqueda.value || undefined },
        { preserveState: true, replace, preserveScroll: true },
    );
};

watch(busqueda, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => buscar(), 300);
});

const formatBs = (valor: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(valor);

const esBase = (codigo: string) => codigo === 'agua' || codigo === 'alcantarillado';

/** Una fila por afiliación; modalidades/montos se dividen dentro del formulario. */
const filas = computed(() =>
    (props.tipos ?? []).map((tipo) => {
        const contado = Number(tipo.monto) || 0;
        const plazo = Number(tipo.monto_plazo) || 0;
        const etiqueta = (tipo.modalidad || '').trim();
        const tieneAmbos = contado > 0 && plazo > 0;

        let modalidad = etiqueta;
        if (!modalidad) {
            if (esBase(tipo.codigo) || tieneAmbos) {
                modalidad = 'Al contado / A plazo';
            } else if (plazo > 0 && contado === 0) {
                modalidad = 'A plazo';
            } else {
                modalidad = 'Al contado';
            }
        }

        let montoTexto: string;
        if (tieneAmbos || esBase(tipo.codigo)) {
            montoTexto = `${formatBs(contado)} / ${formatBs(plazo)}`;
        } else if (plazo > 0 && contado === 0) {
            montoTexto = formatBs(plazo);
        } else {
            montoTexto = formatBs(contado || plazo);
        }

        return {
            key: `${tipo.id}-unico`,
            tipo,
            modalidad,
            montoTexto,
            editHref: `/afiliaciones/${tipo.id}/edit`,
        };
    }),
);
</script>

<template>
    <Head title="Afiliaciones" />

    <div :class="pageShellClass">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <BadgeDollarSign class="size-5 text-white" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">Afiliaciones</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Precios de instalación al contado y a plazo.
                    </p>
                </div>
            </div>
        </div>

        <form class="flex w-full flex-col gap-2 sm:max-w-2xl sm:flex-row sm:items-center" @submit.prevent="buscar(false)">
            <div class="relative min-w-0 flex-1">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar por nombre, código o estado..."
                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-11 pr-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
                />
            </div>
            <Link
                v-if="canManage('afiliaciones.gestionar')"
                href="/afiliaciones/create"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
            >
                <Plus class="size-4" />
                Nueva afiliación
            </Link>
        </form>

        <div class="min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table v-tabla-responsiva class="w-full md:min-w-[760px]">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Afiliación</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Modalidad</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-medium uppercase text-slate-500">
                            Monto (contado / plazo)
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Lotes</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Estado</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-medium uppercase text-slate-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-if="filas.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                            No hay tipos de afiliación registrados.
                        </td>
                    </tr>
                    <tr
                        v-for="fila in filas"
                        :key="fila.key"
                        class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900 dark:text-slate-50">
                                {{ fila.tipo.nombre }}
                            </p>
                            <p class="mt-0.5 max-w-md text-xs text-slate-500">{{ fila.tipo.descripcion || fila.tipo.codigo }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ fila.modalidad }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-cyan-700 dark:text-cyan-300">
                            {{ fila.montoTexto }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-slate-700 dark:text-slate-200">{{ fila.tipo.lotes }}</td>
                        <td class="px-4 py-3 text-center text-sm">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    fila.tipo.estado === 'Activa'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                {{ fila.tipo.estado }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <Link
                                    v-if="canManage('afiliaciones.gestionar')"
                                    :href="fila.editHref"
                                    title="Editar"
                                    aria-label="Editar"
                                    class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    <Pencil class="size-3.5" />
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
