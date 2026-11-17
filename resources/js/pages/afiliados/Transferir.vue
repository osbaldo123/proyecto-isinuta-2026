<script setup lang="ts">
import FormPageShell from '@/components/FormPageShell.vue';
import InputError from '@/components/InputError.vue';
import { usePermissions } from '@/composables/usePermissions';
import {
    formCardBodyClass,
    formCardClass,
    formInputClass,
    formLabelClass,
    formPrimaryButtonClass,
    formSecondaryButtonClass,
} from '@/lib/formStyles';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    ArrowRightLeft,
    CalendarDays,
    CheckCircle2,
    CreditCard,
    Loader2,
    Search,
    UserPlus,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type LoteResumen = { numero_lote: string | null; otb: string | null };

const props = defineProps<{
    lote: {
        id: number;
        numero_lote: string;
        otb: string | null;
        direccion: string | null;
        categoria: string | null;
        estado: string;
        servicios: string[];
    };
    titular: {
        id: number;
        nombre_completo: string;
        ci: string;
        otros_lotes: LoteResumen[];
    };
    deuda: {
        cuotas: number;
        meses: number;
        rango_meses: string | null;
        multas: number;
        cantidad_multas: number;
        afiliacion: number;
        total: number;
        al_dia: boolean;
    };
    bloqueo: string | null;
    hoy: string;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Afiliados', href: '/afiliados' },
            { title: 'Transferir lote', href: '#' },
        ],
    }),
});

const { canManage, can } = usePermissions();

const formatBs = (n: number) => new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);

const etiquetaLote = (l: LoteResumen) => `L-${l.numero_lote || '—'}`;

const listaLotes = (lotes: LoteResumen[]) => lotes.map(etiquetaLote).join(', ');

/* ─────────────── Pasos ─────────────── */

const pasos = [
    { numero: 1, titulo: 'Lote', detalle: 'Revisar deudas' },
    { numero: 2, titulo: 'Nuevo titular', detalle: 'Buscar por CI' },
    { numero: 3, titulo: 'Confirmar', detalle: 'Fecha y motivo' },
] as const;

const paso = ref<1 | 2 | 3>(1);

/* ─────────────── Paso 2: nuevo titular ─────────────── */

type PersonaEncontrada = {
    nombre_completo: string;
    nombres: string;
    apellidos: string;
    ci: string;
    telefono: string | null;
    lotes: LoteResumen[];
};

const form = useForm({
    ci: '',
    nombres: '',
    apellidos: '',
    telefono: '',
    fecha_cesion: props.hoy,
    motivo: '',
    documento_respaldo: '',
});

const buscando = ref(false);
const busquedaHecha = ref(false);
const persona = ref<PersonaEncontrada | null>(null);
const esTitularActual = ref(false);
const errorBusqueda = ref<string | null>(null);

const ciCompleto = computed(() => form.ci.replace(/\D/g, '').length === 8);

watch(
    () => form.ci,
    (valor) => {
        const limpio = valor.replace(/\D/g, '').slice(0, 8);
        if (limpio !== valor) {
            form.ci = limpio;
        }
        // Si cambia el CI, la búsqueda anterior ya no vale.
        busquedaHecha.value = false;
        persona.value = null;
        esTitularActual.value = false;
        errorBusqueda.value = null;
    },
);

const buscarTitular = async () => {
    if (!ciCompleto.value) {
        errorBusqueda.value = 'El CI debe tener 8 dígitos.';
        return;
    }

    buscando.value = true;
    errorBusqueda.value = null;

    try {
        const res = await fetch(`/afiliados/${props.lote.id}/transferir/buscar-titular?ci=${encodeURIComponent(form.ci)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!res.ok) {
            throw new Error(String(res.status));
        }

        const data = (await res.json()) as {
            valido: boolean;
            encontrado: boolean;
            es_titular_actual?: boolean;
            persona?: PersonaEncontrada;
        };

        busquedaHecha.value = true;
        persona.value = data.encontrado ? (data.persona ?? null) : null;
        esTitularActual.value = Boolean(data.es_titular_actual);

        if (persona.value) {
            form.nombres = persona.value.nombres;
            form.apellidos = persona.value.apellidos;
            form.telefono = persona.value.telefono ?? '';
        } else {
            form.nombres = '';
            form.apellidos = '';
            form.telefono = '';
        }
    } catch {
        errorBusqueda.value = 'No se pudo buscar el CI. Intente de nuevo.';
    } finally {
        buscando.value = false;
    }
};

const nombreNuevoTitular = computed(() =>
    persona.value ? persona.value.nombre_completo : `${form.nombres} ${form.apellidos}`.replace(/\s+/g, ' ').trim(),
);

const puedeIrAPaso3 = computed(() => {
    if (!busquedaHecha.value || esTitularActual.value) {
        return false;
    }

    return persona.value !== null || (form.nombres.trim() !== '' && form.apellidos.trim() !== '');
});

/* ─────────────── Paso 3: confirmar ─────────────── */

const fechaLegible = computed(() => {
    if (!form.fecha_cesion) {
        return '';
    }
    const [anio, mes, dia] = form.fecha_cesion.split('-');

    return `${dia}/${mes}/${anio}`;
});

const resumenCedente = computed(() => {
    const otros = props.titular.otros_lotes;

    if (otros.length === 0) {
        return `${props.titular.nombre_completo} se quedará sin lotes (su registro y su historial se conservan).`;
    }

    return `${props.titular.nombre_completo} conservará ${otros.length} ${otros.length === 1 ? 'lote' : 'lotes'}: ${listaLotes(otros)}.`;
});

const resumenNuevo = computed(() => {
    const lotesActuales = persona.value?.lotes ?? [];
    const base = `El lote L-${props.lote.numero_lote} pasará a ${nombreNuevoTitular.value || 'la nueva persona'}`;

    if (lotesActuales.length > 0) {
        return `${base}, que ya tiene ${lotesActuales.length === 1 ? 'el lote' : 'los lotes'} ${listaLotes(lotesActuales)}.`;
    }

    return `${base}${persona.value ? '' : ' (se registrará como nuevo afiliado)'}.`;
});

const puedeConfirmar = computed(
    () => form.fecha_cesion !== '' && form.motivo.trim().length >= 5 && !form.processing,
);

// Los errores del servidor llevan de vuelta al paso que corresponde.
watch(
    () => form.errors,
    (errores) => {
        if (errores.lote) {
            paso.value = 1;
        } else if (errores.ci || errores.nombres || errores.apellidos || errores.telefono) {
            paso.value = 2;
        }
    },
);

const enviar = () => {
    if (!puedeConfirmar.value) {
        return;
    }

    form.post(`/afiliados/${props.lote.id}/transferir`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Transferir lote ${lote.numero_lote}`" />

    <FormPageShell
        title="Transferir lote"
        :subtitle="`Lote ${lote.numero_lote} · titular actual: ${titular.nombre_completo}`"
        :back-href="`/afiliados/${lote.id}`"
        :icon="ArrowRightLeft"
        max-width="3xl"
    >
        <!-- Indicador de pasos -->
        <ol class="grid grid-cols-3 gap-2" aria-label="Pasos de la transferencia">
            <li v-for="item in pasos" :key="item.numero">
                <div
                    class="flex h-full flex-col gap-1 rounded-xl border px-3 py-2.5 sm:flex-row sm:items-center sm:gap-3"
                    :class="
                        paso === item.numero
                            ? 'border-cyan-500 bg-cyan-500/10 dark:border-cyan-400'
                            : paso > item.numero
                              ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-700/60 dark:bg-emerald-900/20'
                              : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'
                    "
                    :aria-current="paso === item.numero ? 'step' : undefined"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                        :class="
                            paso === item.numero
                                ? 'bg-cyan-500 text-white'
                                : paso > item.numero
                                  ? 'bg-emerald-500 text-white'
                                  : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                        "
                    >
                        <CheckCircle2 v-if="paso > item.numero" class="size-4" />
                        <template v-else>{{ item.numero }}</template>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900 dark:text-slate-50">{{ item.titulo }}</span>
                        <span class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">{{ item.detalle }}</span>
                    </span>
                </div>
            </li>
        </ol>

        <!-- ═════════ Paso 1: Lote y deudas ═════════ -->
        <section v-if="paso === 1" :class="formCardClass">
            <div :class="formCardBodyClass">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">1. Lote a transferir</h2>

                <dl class="grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2 dark:bg-slate-800/60">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lote</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-50">L-{{ lote.numero_lote }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">OTB</dt>
                        <dd class="text-slate-800 dark:text-slate-100">{{ lote.otb || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dirección</dt>
                        <dd class="text-slate-800 dark:text-slate-100">{{ lote.direccion || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Servicios</dt>
                        <dd class="text-slate-800 dark:text-slate-100">{{ lote.servicios.length ? lote.servicios.join(', ') : 'Sin servicios' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Titular actual</dt>
                        <dd class="text-slate-800 dark:text-slate-100">
                            {{ titular.nombre_completo }} · CI {{ titular.ci }}
                            <span v-if="titular.otros_lotes.length" class="block text-xs text-slate-500 dark:text-slate-400">
                                Otros lotes: {{ listaLotes(titular.otros_lotes) }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Estado de deuda del lote</h3>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cuotas mensuales</p>
                        <p class="text-lg font-bold" :class="deuda.cuotas > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                            {{ formatBs(deuda.cuotas) }}
                        </p>
                        <p v-if="deuda.meses > 0" class="text-xs text-slate-500 dark:text-slate-400">
                            {{ deuda.meses }} {{ deuda.meses === 1 ? 'mes' : 'meses' }}<template v-if="deuda.rango_meses">: {{ deuda.rango_meses }}</template>
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Multas pendientes</p>
                        <p class="text-lg font-bold" :class="deuda.multas > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                            {{ formatBs(deuda.multas) }}
                        </p>
                        <p v-if="deuda.cantidad_multas > 0" class="text-xs text-slate-500 dark:text-slate-400">
                            {{ deuda.cantidad_multas }} {{ deuda.cantidad_multas === 1 ? 'multa' : 'multas' }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo de afiliación</p>
                        <p class="text-lg font-bold" :class="deuda.afiliacion > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                            {{ formatBs(deuda.afiliacion) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="bloqueo"
                    role="alert"
                    class="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200"
                >
                    <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                    <div class="space-y-1">
                        <p class="font-semibold">No se puede transferir este lote todavía.</p>
                        <p>{{ bloqueo }}</p>
                        <p v-if="!deuda.al_dia" class="font-semibold">Deuda total: {{ formatBs(deuda.total) }}</p>
                    </div>
                </div>
                <div
                    v-else
                    class="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200"
                >
                    <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                    <p>El lote no tiene deudas pendientes. Puede continuar con la transferencia.</p>
                </div>

                <InputError :message="form.errors.lote" />
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end sm:px-6 dark:border-slate-800 dark:bg-slate-950/40">
                <Link :href="`/afiliados/${lote.id}`" :class="formSecondaryButtonClass">Cancelar</Link>
                <template v-if="bloqueo && !deuda.al_dia">
                    <Link v-if="can('pagos.ver')" :href="`/pagos/estado-cuenta/${lote.id}`" :class="formSecondaryButtonClass">
                        <CalendarDays class="size-4" />
                        Ver estado de cuenta
                    </Link>
                    <Link v-if="canManage('pagos.gestionar')" :href="`/pagos/registrar/nuevo?afiliado_id=${lote.id}`" :class="formPrimaryButtonClass">
                        <CreditCard class="size-4" />
                        Cobrar deuda
                    </Link>
                </template>
                <button v-else type="button" :class="formPrimaryButtonClass" :disabled="!!bloqueo" @click="paso = 2">
                    Siguiente
                    <ArrowRight class="size-4" />
                </button>
            </div>
        </section>

        <!-- ═════════ Paso 2: Nuevo titular ═════════ -->
        <section v-else-if="paso === 2" :class="formCardClass">
            <div :class="formCardBodyClass">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">2. Nuevo titular</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Busque por CI. Si la persona ya está registrada se le asigna el lote; si no, regístrela aquí mismo.
                </p>

                <form class="flex flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="buscarTitular">
                    <div class="min-w-0 flex-1">
                        <label for="ci" :class="formLabelClass">CI del nuevo titular</label>
                        <input
                            id="ci"
                            v-model="form.ci"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="8"
                            placeholder="8 dígitos"
                            :class="formInputClass"
                        />
                    </div>
                    <button type="submit" :class="formPrimaryButtonClass" :disabled="buscando || !ciCompleto">
                        <Loader2 v-if="buscando" class="size-4 animate-spin" />
                        <Search v-else class="size-4" />
                        Buscar
                    </button>
                </form>
                <InputError :message="errorBusqueda || form.errors.ci" />

                <!-- Es el mismo titular -->
                <div
                    v-if="busquedaHecha && esTitularActual"
                    role="alert"
                    class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                    <p>Ese CI es del titular actual del lote. Ingrese el CI de la persona que recibirá el lote.</p>
                </div>

                <!-- Persona ya registrada -->
                <div
                    v-else-if="busquedaHecha && persona"
                    class="flex gap-3 rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-sm dark:border-cyan-900/60 dark:bg-cyan-950/30"
                >
                    <UserRound class="mt-0.5 size-5 shrink-0 text-cyan-600 dark:text-cyan-300" />
                    <div class="min-w-0 space-y-1 text-slate-800 dark:text-slate-100">
                        <p class="font-semibold">{{ persona.nombre_completo }} · CI {{ persona.ci }}</p>
                        <p class="text-slate-600 dark:text-slate-300">
                            Ya está registrada.
                            <template v-if="persona.lotes.length">Tiene {{ persona.lotes.length === 1 ? 'el lote' : 'los lotes' }} {{ listaLotes(persona.lotes) }}.</template>
                            <template v-else>Actualmente no tiene lotes.</template>
                            Se le asignará el lote L-{{ lote.numero_lote }}.
                        </p>
                        <div class="pt-2">
                            <label for="telefono-existente" :class="formLabelClass">Teléfono para este lote (opcional)</label>
                            <input id="telefono-existente" v-model="form.telefono" type="tel" inputmode="numeric" :class="formInputClass" />
                            <InputError :message="form.errors.telefono" />
                        </div>
                    </div>
                </div>

                <!-- Persona nueva -->
                <div v-else-if="busquedaHecha" class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-50">
                        <UserPlus class="size-4 text-cyan-600" />
                        CI no registrado: registre a la nueva persona
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="nombres" :class="formLabelClass">Nombres</label>
                            <input id="nombres" v-model="form.nombres" type="text" maxlength="32" autocomplete="off" :class="formInputClass" />
                            <InputError :message="form.errors.nombres" />
                        </div>
                        <div>
                            <label for="apellidos" :class="formLabelClass">Apellidos</label>
                            <input id="apellidos" v-model="form.apellidos" type="text" maxlength="32" autocomplete="off" :class="formInputClass" />
                            <InputError :message="form.errors.apellidos" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="telefono" :class="formLabelClass">Teléfono (opcional)</label>
                            <input id="telefono" v-model="form.telefono" type="tel" inputmode="numeric" :class="formInputClass" />
                            <InputError :message="form.errors.telefono" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end sm:px-6 dark:border-slate-800 dark:bg-slate-950/40">
                <button type="button" :class="formSecondaryButtonClass" @click="paso = 1">
                    <ArrowLeft class="size-4" />
                    Atrás
                </button>
                <button type="button" :class="formPrimaryButtonClass" :disabled="!puedeIrAPaso3" @click="paso = 3">
                    Siguiente
                    <ArrowRight class="size-4" />
                </button>
            </div>
        </section>

        <!-- ═════════ Paso 3: Confirmar ═════════ -->
        <form v-else :class="formCardClass" @submit.prevent="enviar">
            <div :class="formCardBodyClass">
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">3. Confirmar transferencia</h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="fecha_cesion" :class="formLabelClass">Fecha de la transferencia</label>
                        <input id="fecha_cesion" v-model="form.fecha_cesion" type="date" :max="hoy" :class="formInputClass" />
                        <InputError :message="form.errors.fecha_cesion" />
                    </div>
                    <div>
                        <label for="documento" :class="formLabelClass">Documento de respaldo (opcional)</label>
                        <input
                            id="documento"
                            v-model="form.documento_respaldo"
                            type="text"
                            maxlength="120"
                            placeholder="Ej.: Minuta N° 45/2026"
                            :class="formInputClass"
                        />
                        <InputError :message="form.errors.documento_respaldo" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="motivo" :class="formLabelClass">Motivo</label>
                        <textarea
                            id="motivo"
                            v-model="form.motivo"
                            rows="3"
                            maxlength="500"
                            placeholder="Ej.: Venta del lote, herencia, donación…"
                            :class="[formInputClass, '!h-auto min-h-[5.5rem]']"
                        />
                        <InputError :message="form.errors.motivo" />
                    </div>
                </div>

                <div class="space-y-2 rounded-xl border border-cyan-500/30 bg-cyan-500/5 p-4 text-sm dark:border-cyan-400/25 dark:bg-cyan-500/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">Resumen</p>
                    <p class="text-slate-800 dark:text-slate-100">{{ resumenCedente }}</p>
                    <p class="font-semibold text-slate-900 dark:text-slate-50">{{ resumenNuevo }}</p>
                    <p class="text-slate-600 dark:text-slate-300">
                        Fecha: {{ fechaLegible }}<template v-if="form.documento_respaldo"> · Documento: {{ form.documento_respaldo }}</template>
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Los recibos y multas anteriores seguirán a nombre de quien los pagó. Desde ahora las cuotas del lote
                        corresponden al nuevo titular.
                    </p>
                </div>

                <InputError :message="form.errors.lote" />
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end sm:px-6 dark:border-slate-800 dark:bg-slate-950/40">
                <button type="button" :class="formSecondaryButtonClass" :disabled="form.processing" @click="paso = 2">
                    <ArrowLeft class="size-4" />
                    Atrás
                </button>
                <button type="submit" :class="formPrimaryButtonClass" :disabled="!puedeConfirmar">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <ArrowRightLeft v-else class="size-4" />
                    Confirmar transferencia
                </button>
            </div>
        </form>
    </FormPageShell>
</template>
