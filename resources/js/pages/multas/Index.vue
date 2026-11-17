<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Receipt, Search, Settings } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import CiIdentidadInput from '@/components/CiIdentidadInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const { can, canManage } = usePermissions();

type Multa = {
    id: number;
    afiliado: { id: number; ci?: string; nombre_completo: string };
    lote_id?: number | null;
    numero_lote?: string | null;
    otb?: string | null;
    lote: { numero?: string | null; numero_lote?: string | null; otb: string | null } | null;
    fecha_generacion: string;
    motivo: string;
    descripcion: string | null;
    monto: number;
    estado: string;
    recibo_id?: number | null;
};

type Paginated = {
    data: Multa[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    last_page: number;
};

type OpcionFiltro = { value: string; label: string };

const props = defineProps<{
    modo: 'gestion' | 'consulta';
    multas: Paginated;
    filtros?: {
        estado?: string;
        motivo?: string;
        q?: string;
        ci?: string;
    };
    opcionesFiltro?: {
        estados: OpcionFiltro[];
        motivos: OpcionFiltro[];
    };
    afiliado: {
        id: number;
        codigo: string;
        nombre_completo: string;
        ci: string;
        estado: string;
        total_multas_pendientes: number;
    } | null;
    mensaje: string | null;
    busqueda: { ci: string; q?: string };
    consultaVinculada: boolean;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Multas', href: '/multas' }],
    }),
});

const ci = ref(props.busqueda.ci ?? '');
const busquedaGestion = ref(props.filtros?.q ?? props.busqueda.q ?? '');
const estado = ref(props.filtros?.estado ?? 'pendiente');
const motivo = ref(props.filtros?.motivo ?? '');

const estados = props.opcionesFiltro?.estados ?? [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'pagada', label: 'Pagada' },
    { value: 'anulada', label: 'Anulada' },
    { value: 'todas', label: 'Todas' },
];

const motivos = props.opcionesFiltro?.motivos ?? [
    { value: '', label: 'Todos los motivos' },
    { value: 'mora_trimestral', label: 'Mora trimestral' },
    { value: 'mora_anual', label: 'Mora anual' },
    { value: 'falta_trabajo', label: 'Falta de trabajo' },
    { value: 'individual', label: 'Multa individual' },
];

const campoSelect =
    'rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 [color-scheme:light] dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:[color-scheme:dark]';

const paramsFiltro = () => ({
    estado: estado.value !== 'pendiente' ? estado.value : undefined,
    motivo: motivo.value || undefined,
});

const buscar = () => {
    router.get(
        '/multas',
        {
            ci: ci.value || undefined,
            ...paramsFiltro(),
        },
        { preserveState: true, replace: true },
    );
};

const buscarGestion = () => {
    router.get(
        '/multas',
        {
            q: busquedaGestion.value.trim() || undefined,
            ...paramsFiltro(),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let debounceGestion: ReturnType<typeof setTimeout> | null = null;

watch(busquedaGestion, () => {
    if (debounceGestion) {
        clearTimeout(debounceGestion);
    }

    debounceGestion = setTimeout(() => buscarGestion(), 300);
});

watch([estado, motivo], () => {
    if (props.modo === 'gestion') {
        buscarGestion();
    }
});

const queryListado = () => {
    const params: Record<string, string> = {};

    if (props.modo === 'consulta' && ci.value) {
        params.ci = ci.value;
    }

    if (props.modo === 'gestion' && busquedaGestion.value.trim()) {
        params.q = busquedaGestion.value.trim();
    }

    if (estado.value !== 'pendiente') {
        params.estado = estado.value;
    }

    if (motivo.value) {
        params.motivo = motivo.value;
    }

    return params;
};

const withQuery = (path: string) => {
    const qs = new URLSearchParams(queryListado()).toString();

    return qs ? `${path}?${qs}` : path;
};

const detalleUrl = (id: number) => withQuery(`/multas/${id}`);

const pagarUrl = (multa: Multa) => {
    const params = new URLSearchParams();

    if (multa.lote_id) {
        params.set('afiliado_id', String(multa.lote_id));
    }

    params.set('multa_id', String(multa.id));

    return `/pagos/registrar/nuevo?${params.toString()}`;
};

const reciboUrl = (multa: Multa) => (multa.recibo_id ? `/pagos/${multa.recibo_id}` : null);

/** Entrar a la fila: pagar la deuda, o ver el recibo si ya está pagada. */
const abrirMulta = (multa: Multa) => {
    if (multa.estado === 'pendiente' && multa.lote_id && canManage('pagos.gestionar')) {
        router.visit(pagarUrl(multa));

        return;
    }

    const recibo = reciboUrl(multa);

    if (multa.estado === 'pagada' && recibo) {
        router.visit(recibo);

        return;
    }

    router.visit(detalleUrl(multa.id));
};

const numeroDeLote = (m: Multa) => m.numero_lote || m.lote?.numero || m.lote?.numero_lote || '';

const otbDeLote = (m: Multa) => m.otb || m.lote?.otb || '';

const formatBs = (n: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);

const motivoLabel = (m: string) => {
    const map: Record<string, string> = {
        mora_trimestral: 'Mora trimestral (Bs 50)',
        mora_anual: 'Mora anual (Bs 150)',
        individual: 'Multa individual',
        falta_trabajo: 'Falta de trabajo',
    };

    return map[m] ?? m;
};

const estadoBadge = (e: string) => {
    if (e === 'pagada') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    }

    if (e === 'anulada') {
        return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
    }

    return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200';
};

const mensajeVacio = () => {
    if (busquedaGestion.value.trim()) {
        return 'No hay multas que coincidan con la búsqueda y los filtros.';
    }

    if (estado.value === 'pendiente' && !motivo.value) {
        return 'No hay multas por pagar.';
    }

    return 'No hay multas con esos filtros.';
};
</script>

<template>
    <Head title="Multas" />

    <div :class="pageShellClass">
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <Receipt class="size-5 text-white" />
                </div>
                <div class="min-w-0">
                    <h1 :class="pageTitleClass">Multas</h1>
                    <p v-if="modo === 'consulta'" class="text-sm text-slate-600 dark:text-slate-400">
                        Consulta privada por CI. No se muestran datos de otros afiliados.
                    </p>
                </div>
            </div>
            <Link
                v-if="modo === 'gestion' && can('configuracion.ver')"
                href="/configuracion"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-cyan-500/40 bg-cyan-500/10 px-4 py-2.5 text-sm font-medium text-cyan-800 transition hover:bg-cyan-500 hover:text-white dark:text-cyan-200"
            >
                <Settings class="size-4" />
                Configurar multas por mora
            </Link>
        </div>

        <form
            v-if="modo === 'gestion'"
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
            @submit.prevent="buscarGestion"
        >
            <div class="relative min-w-0 flex-1 lg:max-w-md">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busquedaGestion"
                    type="search"
                    placeholder="Nombre, lote o CI"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
                />
            </div>
            <select v-model="estado" :class="[campoSelect, 'w-full sm:w-40']" aria-label="Estado">
                <option v-for="opcion in estados" :key="`estado-${opcion.value}`" :value="opcion.value">
                    {{ opcion.label }}
                </option>
            </select>
            <select v-model="motivo" :class="[campoSelect, 'w-full sm:w-52']" aria-label="Motivo">
                <option v-for="opcion in motivos" :key="`motivo-${opcion.value}`" :value="opcion.value">
                    {{ opcion.label }}
                </option>
            </select>
            <Link
                v-if="canManage('multas.gestionar')"
                href="/multas/create"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
            >
                <Plus class="size-4" />
                Registrar multa
            </Link>
        </form>

        <form
            v-if="modo === 'consulta' && !consultaVinculada"
            class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
            @submit.prevent="buscar"
        >
            <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-50">Buscar por CI</h2>
            <CiIdentidadInput v-model:ci="ci" />
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <label for="estado-consulta" class="text-xs font-semibold text-slate-600 dark:text-slate-400">Estado</label>
                    <select id="estado-consulta" v-model="estado" :class="[campoSelect, 'w-full']">
                        <option v-for="opcion in estados" :key="`c-estado-${opcion.value}`" :value="opcion.value">
                            {{ opcion.label }}
                        </option>
                    </select>
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <label for="motivo-consulta" class="text-xs font-semibold text-slate-600 dark:text-slate-400">Motivo</label>
                    <select id="motivo-consulta" v-model="motivo" :class="[campoSelect, 'w-full']">
                        <option v-for="opcion in motivos" :key="`c-motivo-${opcion.value}`" :value="opcion.value">
                            {{ opcion.label }}
                        </option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="flex items-center justify-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-cyan-600"
                >
                    <Search class="size-4" />
                    Consultar multas
                </button>
            </div>
        </form>

        <div
            v-if="modo === 'consulta' && (consultaVinculada || afiliado)"
            class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
        >
            <select v-model="estado" :class="[campoSelect, 'w-full sm:w-40']" aria-label="Estado" @change="buscar">
                <option v-for="opcion in estados" :key="`v-estado-${opcion.value}`" :value="opcion.value">
                    {{ opcion.label }}
                </option>
            </select>
            <select v-model="motivo" :class="[campoSelect, 'w-full sm:w-52']" aria-label="Motivo" @change="buscar">
                <option v-for="opcion in motivos" :key="`v-motivo-${opcion.value}`" :value="opcion.value">
                    {{ opcion.label }}
                </option>
            </select>
        </div>

        <p
            v-if="mensaje"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
            {{ mensaje }}
        </p>

        <div
            v-if="afiliado"
            class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-900/50 dark:bg-cyan-950/30"
        >
            <p class="font-semibold text-slate-900 dark:text-slate-50">{{ afiliado.nombre_completo }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                CI {{ afiliado.ci }}
            </p>
            <p class="mt-1 text-sm font-medium text-red-700 dark:text-red-300">
                Total multas pendientes: {{ formatBs(afiliado.total_multas_pendientes) }}
            </p>
        </div>

        <div
            v-if="modo === 'consulta' && !afiliado && !mensaje"
            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/50"
        >
            Ingrese el CI del afiliado para consultar sus multas.
        </div>

        <div
            v-if="modo === 'gestion' || afiliado"
            class="min-w-0 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        >
            <table v-tabla-responsiva class="w-full md:min-w-[720px]">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Fecha</th>
                        <th v-if="modo === 'gestion'" class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Afiliado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Lote</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Motivo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Descripción</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-slate-500">Por pagar</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500 w-px whitespace-nowrap">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-if="multas.data.length === 0">
                        <td :colspan="modo === 'gestion' ? 8 : 7" class="px-4 py-8 text-center text-sm text-slate-500">
                            {{ mensajeVacio() }}
                        </td>
                    </tr>
                    <tr
                        v-for="m in multas.data"
                        :key="m.id"
                        class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800"
                        role="link"
                        tabindex="0"
                        @click="abrirMulta(m)"
                        @keydown.enter="abrirMulta(m)"
                    >
                        <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-50">{{ m.fecha_generacion }}</td>
                        <td v-if="modo === 'gestion'" class="px-4 py-3">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-50">{{ m.afiliado.nombre_completo }}</p>
                            <p class="text-xs text-slate-500">
                                CI {{ m.afiliado.ci }}
                                <span v-if="numeroDeLote(m)"> · Lote {{ numeroDeLote(m) }}</span>
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-50">
                                {{ numeroDeLote(m) || '—' }}
                            </p>
                            <p v-if="otbDeLote(m)" class="text-xs text-slate-500">{{ otbDeLote(m) }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ motivoLabel(m.motivo) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ m.descripcion ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-red-600">{{ formatBs(m.monto) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium capitalize" :class="estadoBadge(m.estado)">
                                {{ m.estado }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <Link
                                    v-if="m.estado === 'pendiente' && m.lote_id && canManage('pagos.gestionar')"
                                    :href="pagarUrl(m)"
                                    class="rounded-md bg-cyan-500 px-2.5 py-1 text-xs font-medium text-white hover:bg-cyan-600"
                                    @click.stop
                                >
                                    Pagar
                                </Link>
                                <Link
                                    v-if="m.estado === 'pagada' && reciboUrl(m)"
                                    :href="reciboUrl(m)!"
                                    class="rounded-md border border-cyan-500/40 bg-cyan-500/10 px-2.5 py-1 text-xs font-medium text-cyan-800 hover:bg-cyan-500 hover:text-white dark:text-cyan-200"
                                    @click.stop
                                >
                                    Ver recibo
                                </Link>
                                <Link
                                    :href="detalleUrl(m.id)"
                                    class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                    @click.stop
                                >
                                    Detalle
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="multas.last_page > 1" class="flex flex-wrap items-center gap-1">
            <template v-for="link in multas.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md px-3 py-1 text-sm"
                    :class="
                        link.active
                            ? 'bg-cyan-500 text-white'
                            : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'
                    "
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>
    </div>
</template>
