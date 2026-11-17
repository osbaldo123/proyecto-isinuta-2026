<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, Save, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const { canManage } = usePermissions();
const { confirm: confirmar } = useConfirmDialog();
const page = usePage();

type Fila = {
    lote_afiliado_id: number;
    afiliado_id: number;
    nombre: string;
    ci: string;
    presente: boolean;
    guardado: boolean;
    numero_lote: string;
    otb: string | null;
    categoria: string | null;
    servicios: string[];
};

type Trabajo = {
    id: number;
    otb_id: number;
    otb: string;
    tipo: string;
    descripcion: string;
    fecha_inicio: string;
    monto_multa: number;
    lista_cerrada: boolean;
};

const props = defineProps<{
    trabajo: Trabajo;
    filas: Fila[];
    listaGuardada: boolean;
    formOptions: {
        otbs: Array<{ id: number; nombre: string }>;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Trabajos', href: '/trabajos' },
            { title: 'Lista', href: '/trabajos' },
        ],
    }),
});

const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success);
const flashError = computed(() => (page.props.flash as { error?: string } | undefined)?.error);

const datos = useForm({
    otb_id: props.trabajo.otb_id,
    tipo: props.trabajo.tipo,
    descripcion: props.trabajo.descripcion,
    fecha_inicio: props.trabajo.fecha_inicio,
    monto_multa: props.trabajo.monto_multa,
});

/** presente=true asistió; false = no marcado → falta (multa al guardar). */
const lista = useForm({
    asistencias: props.filas.map((fila) => ({
        lote_afiliado_id: fila.lote_afiliado_id,
        afiliado_id: fila.afiliado_id,
        presente: fila.guardado ? fila.presente : false,
        nombre: fila.nombre,
        ci: fila.ci,
        numero_lote: fila.numero_lote,
        otb: fila.otb,
        categoria: fila.categoria,
        servicios: fila.servicios,
    })),
});

const formatBs = (n: number) => new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);
const montoVigente = computed(() => Number(props.trabajo.monto_multa));

const busqueda = ref('');
const normalizar = (texto: string) => texto.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

const asistenciasVisibles = computed(() => {
    const termino = normalizar(busqueda.value.trim());
    if (termino === '') {
        return lista.asistencias;
    }

    return lista.asistencias.filter(
        (fila) =>
            normalizar(fila.nombre).includes(termino) ||
            normalizar(`${fila.numero_lote} ${fila.categoria ?? ''} ${fila.ci}`).includes(termino),
    );
});

const presentes = computed(() => lista.asistencias.filter((f) => f.presente).length);
const ausentes = computed(() => lista.asistencias.filter((f) => !f.presente).length);

const guardarDatos = () => {
    datos.transform((data) => ({
        ...data,
        otb_id: Number(data.otb_id),
        monto_multa: Number(data.monto_multa),
    }));
    datos.put(`/trabajos/${props.trabajo.id}`, { preserveScroll: true });
};

const guardarLista = async () => {
    if (datos.isDirty) {
        await confirmar({
            title: 'Datos sin guardar',
            message: 'Guarde los datos del trabajo antes de pasar lista. La multa usa el monto y la descripción ya registrados.',
            confirmLabel: 'Entendido',
        });
        return;
    }

    if (ausentes.value > 0) {
        const ok = await confirmar({
            title: 'Guardar lista',
            message: `${ausentes.value} lote(s) sin presente. Se registra una multa de ${formatBs(montoVigente.value)} en cada uno. El mismo afiliado puede tener presente en un lote y multa en otro.`,
            confirmLabel: 'Guardar lista',
        });
        if (!ok) return;
    }

    lista.transform((data) => ({
        asistencias: data.asistencias.map((fila) => ({
            lote_afiliado_id: fila.lote_afiliado_id,
            presente: fila.presente,
        })),
    }));
    lista.put(`/trabajos/${props.trabajo.id}/lista`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Lista · ${trabajo.otb}`" />

    <div :class="pageShellClass">
        <div class="flex items-center gap-3">
            <Link href="/trabajos" class="rounded-xl border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">
                <ArrowLeft class="size-4" />
            </Link>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                <ClipboardList class="size-5 text-white" />
            </div>
            <div>
                <h1 :class="pageTitleClass">{{ trabajo.otb }}</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Marque Presente por lote. Si no se marca, se entiende falta y se aplica multa a ese lote.
                </p>
            </div>
        </div>

        <div v-if="flashSuccess" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ flashError }}
        </div>
        <div v-if="lista.errors.asistencias" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ lista.errors.asistencias }}
        </div>

        <form v-if="canManage('trabajos.gestionar')" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 md:grid-cols-5" @submit.prevent="guardarDatos">
            <div>
                <label class="mb-1 block text-xs font-medium uppercase text-slate-500">OTB</label>
                <select v-model="datos.otb_id" :disabled="listaGuardada" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm disabled:opacity-60 [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50">
                    <option v-for="otb in formOptions.otbs" :key="otb.id" :value="otb.id">{{ otb.nombre }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase text-slate-500">Tipo</label>
                <input v-model="datos.tipo" type="text" required maxlength="100" placeholder="Según la situación" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase text-slate-500">Inicio</label>
                <input v-model="datos.fecha_inicio" type="date" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase text-slate-500">Multa (Bs)</label>
                <input v-model.number="datos.monto_multa" v-numeric.decimal type="number" min="0.01" step="0.01" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50" />
            </div>
            <div class="md:col-span-2 md:max-w-sm">
                <label class="mb-1 block text-xs font-medium uppercase text-slate-500">Descripción</label>
                <input v-model="datos.descripcion" type="text" required maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50" />
            </div>
            <div class="flex items-end">
                <button type="submit" :disabled="datos.processing" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200">
                    Guardar datos
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <div class="relative w-full max-w-xs">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="busqueda"
                        type="search"
                        placeholder="Buscar por nombre, CI o lote"
                        class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50"
                    />
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    <span v-if="filas.length === 0">Esta OTB no tiene lotes activos.</span>
                    <span v-else>{{ presentes }} presente(s) · {{ ausentes }} sin marcar</span>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table v-tabla-responsiva class="w-full md:min-w-[640px]">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Presente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Afiliado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Lote</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <tr v-if="lista.asistencias.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">No hay lotes para pasar lista.</td>
                        </tr>
                        <tr v-else-if="asistenciasVisibles.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">Ningún lote coincide con la búsqueda.</td>
                        </tr>
                        <tr v-for="fila in asistenciasVisibles" :key="fila.lote_afiliado_id">
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input
                                        v-model="fila.presente"
                                        type="checkbox"
                                        class="size-4 rounded border-slate-300 text-cyan-600"
                                        :disabled="!canManage('trabajos.gestionar')"
                                    />
                                    Presente
                                </label>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium">{{ fila.nombre }}</p>
                                <p class="text-xs text-slate-500">CI {{ fila.ci }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-slate-700 dark:text-slate-300">
                                    Lote {{ fila.numero_lote }}
                                    <span v-if="fila.categoria"> · {{ fila.categoria }}</span>
                                    <span v-if="fila.otb"> · {{ fila.otb }}</span>
                                    <span v-if="fila.servicios.length"> · {{ fila.servicios.join(', ') }}</span>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="canManage('trabajos.gestionar')" class="flex justify-end">
            <button
                type="button"
                :disabled="lista.processing"
                class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-600 disabled:opacity-50"
                @click="guardarLista"
            >
                <Save class="size-4" />
                {{ lista.processing ? 'Guardando…' : 'Guardar lista' }}
            </button>
        </div>
    </div>
</template>
