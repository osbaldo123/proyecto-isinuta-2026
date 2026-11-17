<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ClipboardList, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { usePermissions } from '@/composables/usePermissions';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';

const { canManage } = usePermissions();
const { confirm: confirmar } = useConfirmDialog();
const page = usePage();

type Trabajo = {
    id: number;
    otb_id: number;
    otb: string;
    tipo: string;
    descripcion: string;
    fecha_inicio: string;
    monto_multa: number;
    presentes: number;
    ausentes: number;
    multas: number;
};

type Paginated = {
    data: Trabajo[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    last_page: number;
};

const props = defineProps<{
    trabajos: Paginated;
    filtros: { q: string };
    formOptions: {
        otbs: Array<{ id: number; nombre: string }>;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Trabajos', href: '/trabajos' }],
    }),
});

const busqueda = ref(props.filtros.q ?? '');
const modalAbierto = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success);
const flashError = computed(() => (page.props.flash as { error?: string } | undefined)?.error);

const form = useForm({
    otb_id: '' as number | '',
    tipo: '',
    descripcion: '',
    fecha_inicio: new Date().toISOString().slice(0, 10),
    monto_multa: 20 as number | null,
});

const aplicarFiltros = () => {
    router.get(
        '/trabajos',
        { q: busqueda.value || undefined },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

watch(busqueda, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => aplicarFiltros(), 300);
});

const formatBs = (n: number) => new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);

const abrirCrear = () => {
    form.reset();
    form.clearErrors();
    form.tipo = '';
    form.descripcion = '';
    form.fecha_inicio = new Date().toISOString().slice(0, 10);
    form.monto_multa = 20;
    modalAbierto.value = true;
};

const guardar = () => {
    form.transform((data) => ({
        ...data,
        otb_id: Number(data.otb_id),
        monto_multa: Number(data.monto_multa),
    }));

    form.post('/trabajos', {
        preserveScroll: true,
        onSuccess: () => {
            modalAbierto.value = false;
        },
    });
};

const eliminar = async (trabajo: Trabajo) => {
    const ok = await confirmar({
        title: 'Eliminar trabajo',
        message: `¿Eliminar el trabajo de ${trabajo.otb} (${trabajo.tipo})?`,
        confirmLabel: 'Eliminar',
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(`/trabajos/${trabajo.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Trabajos" />

    <div :class="pageShellClass">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                <ClipboardList class="size-5 text-white" />
            </div>
            <div>
                <h1 :class="pageTitleClass">Trabajos</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Registro del trabajo de la OTB que indica la secretaria, con lista por afiliado.
                </p>
            </div>
        </div>

        <div v-if="flashSuccess" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ flashError }}
        </div>

        <div class="flex w-full flex-col gap-2 sm:max-w-3xl sm:flex-row sm:items-center">
            <div class="relative min-w-0 flex-1">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar por OTB, tipo o descripción..."
                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-11 pr-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none [color-scheme:light] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-50 dark:[color-scheme:dark]"
                />
            </div>
            <button
                v-if="canManage('trabajos.gestionar')"
                type="button"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-600"
                @click="abrirCrear"
            >
                <Plus class="size-4" />
                Registrar trabajo
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table v-tabla-responsiva class="w-full md:min-w-[860px]">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">OTB</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Inicio</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-slate-500">Multa</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-500">Lista</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-slate-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <tr v-if="trabajos.data.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                                No hay trabajos registrados.
                            </td>
                        </tr>
                        <tr
                            v-for="t in trabajos.data"
                            :key="t.id"
                            class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800"
                            @click="router.visit(`/trabajos/${t.id}`)"
                        >
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium">{{ t.otb }}</p>
                                <p class="text-xs text-slate-500">{{ t.descripcion }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ t.tipo }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">{{ t.fecha_inicio }}</td>
                            <td class="px-4 py-3 text-right text-sm">{{ formatBs(t.monto_multa) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                <span v-if="t.presentes + t.ausentes === 0">Sin pasar lista</span>
                                <span v-else>{{ t.presentes }} presente(s) · {{ t.ausentes }} falta(s)</span>
                            </td>
                            <td class="px-4 py-3" @click.stop>
                                <div class="flex justify-end gap-2">
                                    <Link :href="`/trabajos/${t.id}`" class="rounded-lg px-2 py-1 text-sm font-medium text-cyan-700 hover:bg-cyan-50">
                                        Lista
                                    </Link>
                                    <button
                                        v-if="canManage('trabajos.gestionar')"
                                        type="button"
                                        class="rounded p-1 text-slate-600 hover:bg-slate-100 hover:text-red-600 dark:text-slate-400 dark:hover:bg-slate-800"
                                        @click="eliminar(t)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="trabajos.last_page > 1" class="flex flex-wrap gap-1">
            <template v-for="link in trabajos.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md px-3 py-1 text-sm"
                    :class="link.active ? 'bg-cyan-500 text-white' : 'border border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'"
                    preserve-scroll
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>

        <div
            v-if="modalAbierto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
            @click.self="modalAbierto = false"
        >
            <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">Registrar trabajo</h2>
                        <p class="text-sm text-slate-500">La secretaria indica la OTB. El sistema no elige el turno.</p>
                    </div>
                    <button type="button" class="rounded p-1 text-slate-600 hover:bg-slate-100 dark:text-slate-400" @click="modalAbierto = false">
                        <X class="size-5" />
                    </button>
                </div>

                <form class="space-y-4 px-5 py-5" @submit.prevent="guardar">
                    <div>
                        <label class="mb-1 block text-sm font-medium">OTB</label>
                        <select v-model="form.otb_id" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50">
                            <option disabled value="">Seleccionar OTB</option>
                            <option v-for="otb in formOptions.otbs" :key="otb.id" :value="otb.id">{{ otb.nombre }}</option>
                        </select>
                        <p v-if="form.errors.otb_id" class="mt-1 text-xs text-red-600">{{ form.errors.otb_id }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Tipo</label>
                            <input
                                v-model="form.tipo"
                                type="text"
                                required
                                maxlength="100"
                                placeholder="Escríbalo según la situación"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50"
                            />
                            <p v-if="form.errors.tipo" class="mt-1 text-xs text-red-600">{{ form.errors.tipo }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Fecha de inicio</label>
                            <input v-model="form.fecha_inicio" type="date" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50" />
                            <p v-if="form.errors.fecha_inicio" class="mt-1 text-xs text-red-600">{{ form.errors.fecha_inicio }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Descripción</label>
                        <input v-model="form.descripcion" type="text" required maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50" placeholder="Motivo del trabajo" />
                        <p v-if="form.errors.descripcion" class="mt-1 text-xs text-red-600">{{ form.errors.descripcion }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Monto de la multa (Bs)</label>
                        <input v-model.number="form.monto_multa" v-numeric.decimal type="number" min="0.01" step="0.01" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 [color-scheme:light] dark:border-slate-700 dark:bg-slate-800 dark:text-slate-50" />
                        <p class="mt-1 text-xs text-slate-500">Se aplica a cada lote del afiliado que falte en esta OTB.</p>
                        <p v-if="form.errors.monto_multa" class="mt-1 text-xs text-red-600">{{ form.errors.monto_multa }}</p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <button type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" @click="modalAbierto = false">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-600 disabled:opacity-50">
                            {{ form.processing ? 'Guardando…' : 'Registrar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
