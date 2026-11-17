<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ClipboardList, Droplets, Home, Save, UserRound } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue';
import CiIdentidadInput from '@/components/CiIdentidadInput.vue';
import DateMaskedInput from '@/components/DateMaskedInput.vue';
import FormPageShell from '@/components/FormPageShell.vue';
import { useCategoriaTarifas } from '@/composables/useCategoriaTarifas';
import { showToast } from '@/composables/useFlashToast';
import {
    formCardBodyClass,
    formCardClass,
    formCardTitleClass,
    formInputClass,
    formLabelClass,
    formPrimaryButtonClass,
    formSecondaryButtonClass,
} from '@/lib/formStyles';
import { parseNumeric } from '@/lib/numericInput';

type OtbItem = { id: number; nombre: string };
type Servicio = { id: number; codigo: string; nombre: string; tipo: string; tarifa_mensual: number };
type TipoAfiliacionOption = {
    id: number;
    codigo: string;
    nombre: string;
    descripcion: string;
    monto: number;
    monto_plazo: number;
    concepto: string;
};

type Categoria = {
    id: number;
    nombre: string;
    descripcion: string;
    tarifa_agua: number;
    tarifa_alcantarillado: number;
    total_mensual: number;
};

type AfiliadoForm = {
    id: number;
    afiliado_id?: number;
    codigo: string;
    nombres: string;
    apellidos: string;
    ci: string;
    telefono: string | null;
    direccion: string | null;
    numero_lote: string;
    otb_id: number | null;
    fecha_afiliacion: string;
    forma_pago_afiliacion: 'contado' | 'plazo';
    monto_afiliacion: number;
    monto_afiliacion_agua: number;
    monto_afiliacion_alcantarillado: number;
    saldo_afiliacion_agua?: number;
    saldo_afiliacion_alcantarillado?: number;
    saldo_afiliacion: number;
    categoria_id: number | null;
    estado: 'activo' | 'cortado' | 'baja';
    observaciones: string | null;
    servicios: number[];
    tipos_afiliacion: number[];
    cantidad_lotes?: number;
};

const FIELD_LABELS: Record<string, string> = {
    nombres: 'Nombres',
    apellidos: 'Apellidos',
    ci: 'Número de CI',
    telefono: 'Teléfono',
    direccion: 'Dirección',
    otb_id: 'OTB',
    numero_lote: 'Número de lote',
    fecha_afiliacion: 'Fecha de afiliación',
    forma_pago_afiliacion: 'Forma de pago',
    monto_afiliacion_agua: 'Afiliación de agua potable',
    monto_afiliacion_alcantarillado: 'Afiliación de alcantarillado',
    monto_afiliacion: 'Monto de afiliación',
    saldo_afiliacion: 'Saldo pendiente',
    categoria_id: 'Categoría de propiedad',
    estado: 'Estado',
    observaciones: 'Observaciones',
    servicios: 'Servicios',
};

const props = defineProps<{
    afiliado: AfiliadoForm;
    otbs: OtbItem[];
    servicios: Servicio[];
    montos_afiliacion: { agua: number; alcantarillado: number };
    tipos_afiliacion: TipoAfiliacionOption[];
    categorias: Categoria[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Afiliados', href: '/afiliados' },
            { title: 'Editar', href: '' },
        ],
    }),
});

const form = useForm({
    nombres: props.afiliado.nombres,
    apellidos: props.afiliado.apellidos,
    ci: props.afiliado.ci,
    telefono: props.afiliado.telefono ?? '',
    direccion: props.afiliado.direccion ?? '',
    numero_lote: props.afiliado.numero_lote ?? '1',
    otb_id: props.afiliado.otb_id,
    fecha_afiliacion: props.afiliado.fecha_afiliacion,
    forma_pago_afiliacion: props.afiliado.forma_pago_afiliacion,
    tipos_afiliacion: [...(props.afiliado.tipos_afiliacion ?? [])] as number[],
    monto_afiliacion_agua: props.afiliado.monto_afiliacion_agua,
    monto_afiliacion_alcantarillado: props.afiliado.monto_afiliacion_alcantarillado,
    monto_afiliacion: props.afiliado.monto_afiliacion,
    saldo_afiliacion_agua: props.afiliado.saldo_afiliacion_agua ?? 0,
    saldo_afiliacion_alcantarillado: props.afiliado.saldo_afiliacion_alcantarillado ?? 0,
    saldo_afiliacion: props.afiliado.saldo_afiliacion,
    categoria_id: props.afiliado.categoria_id,
    estado: props.afiliado.estado,
    observaciones: props.afiliado.observaciones ?? '',
    servicios: [...props.afiliado.servicios] as number[],
});

const tipoAgua = computed(() => props.tipos_afiliacion.find((t) => t.codigo === 'agua') ?? null);
const tipoAlcantarillado = computed(() => props.tipos_afiliacion.find((t) => t.codigo === 'alcantarillado') ?? null);

const afiliacionAgua = ref(
    props.afiliado.monto_afiliacion_agua > 0
        || Boolean(tipoAgua.value && props.afiliado.tipos_afiliacion?.includes(tipoAgua.value.id)),
);
const afiliacionAlcantarillado = ref(
    props.afiliado.monto_afiliacion_alcantarillado > 0
        || Boolean(tipoAlcantarillado.value && props.afiliado.tipos_afiliacion?.includes(tipoAlcantarillado.value.id)),
);
const syncingAfiliacionServicios = ref(false);

const formatBs = (n: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);

const parseMonto = (raw: string | number) => parseNumeric(raw, 'decimal');

const esServicioAgua = (s: Servicio) => s.tipo === 'agua';

const esServicioAlcantarillado = (s: Servicio) => s.tipo === 'alcantarillado';

const idsServicioAgua = computed(() => props.servicios.filter(esServicioAgua).map((s) => s.id));
const idsServicioAlcantarillado = computed(() => props.servicios.filter(esServicioAlcantarillado).map((s) => s.id));

/** En a plazo: afiliación = abono; saldo = catálogo − abono (automático). */
const pagoInicialAgua = ref(0);
const pagoInicialAlc = ref(0);
const saldoPendienteAgua = ref(0);
const saldoPendienteAlc = ref(0);

const montoSegunForma = (tipo: TipoAfiliacionOption | null) => {
    if (!tipo) {
        return 0;
    }

    const contado = Number(tipo.monto) || 0;
    const plazo = Number(tipo.monto_plazo) || 0;

    return form.forma_pago_afiliacion === 'plazo' ? plazo || contado : contado || plazo;
};

const montoAguaSegunForma = computed(() => montoSegunForma(tipoAgua.value));
const montoAlcSegunForma = computed(() => montoSegunForma(tipoAlcantarillado.value));

const montoCatalogoAgua = computed(() =>
    afiliacionAgua.value && tipoAgua.value ? montoAguaSegunForma.value : 0,
);
const montoCatalogoAlc = computed(() =>
    afiliacionAlcantarillado.value && tipoAlcantarillado.value ? montoAlcSegunForma.value : 0,
);

const sincronizarSaldoTotal = () => {
    form.saldo_afiliacion_agua = afiliacionAgua.value
        ? Math.round(Number(saldoPendienteAgua.value || 0) * 100) / 100
        : 0;
    form.saldo_afiliacion_alcantarillado = afiliacionAlcantarillado.value
        ? Math.round(Number(saldoPendienteAlc.value || 0) * 100) / 100
        : 0;
    form.saldo_afiliacion = Math.round(
        (Number(form.saldo_afiliacion_agua || 0) + Number(form.saldo_afiliacion_alcantarillado || 0)) * 100,
    ) / 100;
};

const aplicarSaldoSegunFormaPago = () => {
    if (form.forma_pago_afiliacion !== 'plazo') {
        pagoInicialAgua.value = 0;
        pagoInicialAlc.value = 0;
        saldoPendienteAgua.value = 0;
        saldoPendienteAlc.value = 0;
        form.saldo_afiliacion_agua = 0;
        form.saldo_afiliacion_alcantarillado = 0;
        form.saldo_afiliacion = 0;

        return;
    }

    const catAgua = montoCatalogoAgua.value;
    const catAlc = montoCatalogoAlc.value;
    const pagoAgua = Math.min(catAgua, Math.max(0, Number(pagoInicialAgua.value || 0)));
    const pagoAlc = Math.min(catAlc, Math.max(0, Number(pagoInicialAlc.value || 0)));

    pagoInicialAgua.value = pagoAgua;
    pagoInicialAlc.value = pagoAlc;
    saldoPendienteAgua.value = afiliacionAgua.value
        ? Math.round((catAgua - pagoAgua) * 100) / 100
        : 0;
    saldoPendienteAlc.value = afiliacionAlcantarillado.value
        ? Math.round((catAlc - pagoAlc) * 100) / 100
        : 0;
    sincronizarSaldoTotal();
};

const recalcularTotalAfiliacion = () => {
    if (form.forma_pago_afiliacion === 'plazo') {
        form.monto_afiliacion_agua = montoCatalogoAgua.value;
        form.monto_afiliacion_alcantarillado = montoCatalogoAlc.value;
        form.monto_afiliacion = Math.round((montoCatalogoAgua.value + montoCatalogoAlc.value) * 100) / 100;
    } else {
        form.monto_afiliacion = Number(form.monto_afiliacion_agua || 0) + Number(form.monto_afiliacion_alcantarillado || 0);
    }
    aplicarSaldoSegunFormaPago();
};

const onInputMontoAgua = (raw: string) => {
    const valor = parseMonto(raw);
    if (form.forma_pago_afiliacion === 'plazo') {
        pagoInicialAgua.value = valor;
    } else {
        form.monto_afiliacion_agua = valor;
    }
    recalcularTotalAfiliacion();
};

const onInputMontoAlc = (raw: string) => {
    const valor = parseMonto(raw);
    if (form.forma_pago_afiliacion === 'plazo') {
        pagoInicialAlc.value = valor;
    } else {
        form.monto_afiliacion_alcantarillado = valor;
    }
    recalcularTotalAfiliacion();
};

const actualizarTiposAfiliacion = () => {
    const ids: number[] = [];
    if (afiliacionAgua.value && tipoAgua.value) {
        ids.push(tipoAgua.value.id);
    }
    if (afiliacionAlcantarillado.value && tipoAlcantarillado.value) {
        ids.push(tipoAlcantarillado.value.id);
    }
    form.tipos_afiliacion = ids;
};

const setServiciosPorTipo = (tipo: 'agua' | 'alcantarillado', activo: boolean) => {
    const ids = tipo === 'agua' ? idsServicioAgua.value : idsServicioAlcantarillado.value;
    if (ids.length === 0) {
        return;
    }

    const actuales = new Set(form.servicios);
    if (activo) {
        ids.forEach((id) => actuales.add(id));
    } else {
        ids.forEach((id) => actuales.delete(id));
    }
    form.servicios = [...actuales];
};


actualizarTiposAfiliacion();

if (form.forma_pago_afiliacion === 'plazo') {
    const agua = Number(props.afiliado.monto_afiliacion_agua || 0);
    const alc = Number(props.afiliado.monto_afiliacion_alcantarillado || 0);
    const saldoAgua = Math.min(agua, Math.max(0, Number(props.afiliado.saldo_afiliacion_agua ?? 0)));
    const saldoAlc = Math.min(alc, Math.max(0, Number(props.afiliado.saldo_afiliacion_alcantarillado ?? 0)));
    saldoPendienteAgua.value = saldoAgua;
    saldoPendienteAlc.value = saldoAlc;
    pagoInicialAgua.value = Math.round((agua - saldoAgua) * 100) / 100;
    pagoInicialAlc.value = Math.round((alc - saldoAlc) * 100) / 100;
}
recalcularTotalAfiliacion();

watch(afiliacionAgua, (activo, estabaActivo) => {
    if (syncingAfiliacionServicios.value) {
        return;
    }

    syncingAfiliacionServicios.value = true;
    if (activo && tipoAgua.value && !estabaActivo) {
        form.monto_afiliacion_agua = montoAguaSegunForma.value;
        if (form.forma_pago_afiliacion === 'plazo') {
            pagoInicialAgua.value = 0;
        }
    } else if (!activo) {
        form.monto_afiliacion_agua = 0;
        pagoInicialAgua.value = 0;
        saldoPendienteAgua.value = 0;
    }
    actualizarTiposAfiliacion();
    recalcularTotalAfiliacion();
    setServiciosPorTipo('agua', activo);
    syncingAfiliacionServicios.value = false;
});

watch(afiliacionAlcantarillado, (activo, estabaActivo) => {
    if (syncingAfiliacionServicios.value) {
        return;
    }

    syncingAfiliacionServicios.value = true;
    if (activo && tipoAlcantarillado.value && !estabaActivo) {
        form.monto_afiliacion_alcantarillado = montoAlcSegunForma.value;
        if (form.forma_pago_afiliacion === 'plazo') {
            pagoInicialAlc.value = 0;
        }
    } else if (!activo) {
        form.monto_afiliacion_alcantarillado = 0;
        pagoInicialAlc.value = 0;
        saldoPendienteAlc.value = 0;
    }
    actualizarTiposAfiliacion();
    recalcularTotalAfiliacion();
    setServiciosPorTipo('alcantarillado', activo);
    syncingAfiliacionServicios.value = false;
});

watch(
    () => form.forma_pago_afiliacion,
    () => {
        if (form.forma_pago_afiliacion === 'plazo') {
            pagoInicialAgua.value = 0;
            pagoInicialAlc.value = 0;
        }
        if (afiliacionAgua.value && tipoAgua.value) {
            form.monto_afiliacion_agua = montoAguaSegunForma.value;
        }
        if (afiliacionAlcantarillado.value && tipoAlcantarillado.value) {
            form.monto_afiliacion_alcantarillado = montoAlcSegunForma.value;
        }
        recalcularTotalAfiliacion();
    },
);

const { categoriaSeleccionada, tarifaServicio, totalMensualServicios: total } = useCategoriaTarifas(
    props.categorias,
    toRef(form, 'categoria_id'),
    props.servicios,
    toRef(form, 'servicios'),
);

watch(
    () => [...form.servicios],
    (ids) => {
        if (syncingAfiliacionServicios.value) {
            return;
        }

        const tieneAgua = idsServicioAgua.value.some((id) => ids.includes(id));
        const tieneAlc = idsServicioAlcantarillado.value.some((id) => ids.includes(id));

        syncingAfiliacionServicios.value = true;

        if (afiliacionAgua.value !== tieneAgua) {
            afiliacionAgua.value = tieneAgua;
            if (tieneAgua && tipoAgua.value) {
                form.monto_afiliacion_agua = montoAguaSegunForma.value;
            } else {
                form.monto_afiliacion_agua = 0;
            }
        }

        if (afiliacionAlcantarillado.value !== tieneAlc) {
            afiliacionAlcantarillado.value = tieneAlc;
            if (tieneAlc && tipoAlcantarillado.value) {
                form.monto_afiliacion_alcantarillado = montoAlcSegunForma.value;
            } else {
                form.monto_afiliacion_alcantarillado = 0;
            }
        }

        actualizarTiposAfiliacion();
        recalcularTotalAfiliacion();
        syncingAfiliacionServicios.value = false;
    },
);

const errorList = computed(() =>
    Object.entries(form.errors)
        .map(([field, message]) => {
            const label = FIELD_LABELS[field] ?? field;
            return { field, label, message: String(message) };
        })
        .filter((item) => item.message),
);

const inputClass = (field: string) =>
    [
        formInputClass,
        form.errors[field]
            ? 'border-red-400 focus:border-red-500 focus:ring-red-400/30 dark:border-red-500'
            : '',
    ].join(' ');

const submit = () => {
    form.put(`/afiliados/${props.afiliado.id}`, {
        onError: async () => {
            await nextTick();
            document.getElementById('form-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
    });
};

onMounted(() => {
    showToast(
        'Si este afiliado tiene otra casa/propiedad, use Agregar nuevo lote: se reutiliza el mismo nombre y CI, y solo cambia el número de lote y los datos de esa propiedad.',
        'info',
        5000,
    );
});
</script>

<template>
    <Head :title="`Editar ${afiliado.nombres} ${afiliado.apellidos}`" />

    <FormPageShell
        title="Editar afiliado"
        :subtitle="`${afiliado.nombres} ${afiliado.apellidos}`"
        :back-href="`/afiliados/${afiliado.id}`"
        max-width="5xl"
        :icon="UserRound"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div
                v-if="errorList.length"
                id="form-errors"
                class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"
            >
                <p class="font-semibold">Corrija los siguientes datos:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="item in errorList" :key="item.field">
                        <strong>{{ item.label }}:</strong> {{ item.message }}
                    </li>
                </ul>
            </div>

            <div :class="formCardClass">
                <div :class="formCardBodyClass">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-cyan-500/10 to-slate-100 px-4 py-3 dark:from-cyan-500/15 dark:to-slate-800">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">CI</p>
                            <p class="font-mono text-lg font-bold text-slate-900 dark:text-slate-50">{{ afiliado.ci }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Lote actual: <strong>{{ afiliado.numero_lote }}</strong>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                :href="`/afiliados/create?desde=${afiliado.afiliado_id ?? afiliado.id}`"
                                class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-cyan-500 sm:text-sm"
                            >
                                <Home class="size-4" />
                                Agregar nuevo lote
                            </Link>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-semibold"
                                :class="{
                                    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300': afiliado.estado === 'activo',
                                    'bg-amber-500/15 text-amber-700 dark:text-amber-300': afiliado.estado === 'cortado',
                                    'bg-slate-500/15 text-slate-600 dark:text-slate-300': afiliado.estado === 'baja',
                                }"
                            >
                                {{ afiliado.estado }}
                            </span>
                        </div>
                    </div>

                    <h2 :class="formCardTitleClass">
                        <span class="inline-flex items-center gap-2">
                            <UserRound class="size-4 text-cyan-500" />
                            Datos personales
                        </span>
                    </h2>

                    <div class="flex flex-wrap items-start gap-4">
                        <div class="w-full max-w-[14rem]">
                            <label :class="formLabelClass">Nombres</label>
                            <input v-model="form.nombres" type="text" required maxlength="32" :class="inputClass('nombres')" placeholder="Inserte su nombre" />
                            <p v-if="form.errors.nombres" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.nombres }}</p>
                        </div>
                        <div class="w-full min-w-[14rem] max-w-[22rem] flex-1">
                            <label :class="formLabelClass">Apellidos</label>
                            <input v-model="form.apellidos" type="text" required maxlength="32" :class="inputClass('apellidos')" placeholder="Inserte su apellido" />
                            <p v-if="form.errors.apellidos" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.apellidos }}</p>
                        </div>
                        <div class="w-full max-w-[11rem]">
                            <label :class="formLabelClass">Teléfono</label>
                            <input
                                v-model="form.telefono"
                                v-numeric
                                type="tel"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="15"
                                :class="inputClass('telefono')"
                                placeholder="Solo números"
                                @input="form.telefono = String(($event.target as HTMLInputElement).value).replace(/\D/g, '')"
                            />
                            <p v-if="form.errors.telefono" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.telefono }}</p>
                            <p v-else-if="(afiliado.cantidad_lotes ?? 1) > 1" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Este teléfono es de este lote. Los otros lotes pueden repetirlo o tener otro.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start">
                        <div class="shrink-0">
                            <CiIdentidadInput
                                v-model:ci="form.ci"
                                :ci-error="form.errors.ci"
                            />
                        </div>
                        <div class="w-full min-w-[12rem] max-w-[18rem]">
                            <label :class="formLabelClass">OTB / Sindicato</label>
                            <select v-model="form.otb_id" required :class="inputClass('otb_id')">
                                <option :value="null" disabled>Seleccione OTB</option>
                                <option v-for="z in otbs" :key="z.id" :value="z.id">{{ z.nombre }}</option>
                            </select>
                            <p v-if="form.errors.otb_id" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.otb_id }}</p>
                        </div>
                        <div class="w-full max-w-[7.5rem]">
                            <label :class="formLabelClass">Número de lote</label>
                            <input
                                v-model="form.numero_lote"
                                v-numeric
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                required
                                :class="inputClass('numero_lote')"
                                placeholder="Ej: 1, 2, 12"
                                @input="form.numero_lote = String(($event.target as HTMLInputElement).value).replace(/\D/g, '')"
                            />
                            <p v-if="form.errors.numero_lote" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.numero_lote }}</p>
                        </div>
                        <div class="w-full min-w-0 flex-1">
                            <label :class="formLabelClass">Dirección</label>
                            <input v-model="form.direccion" type="text" :class="inputClass('direccion')" placeholder="Calle, número, referencia" />
                            <p v-if="form.errors.direccion" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.direccion }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div :class="formCardClass">
                <div :class="formCardBodyClass">
                    <h2 :class="formCardTitleClass">
                        <span class="inline-flex items-center gap-2">
                            <ClipboardList class="size-4 text-cyan-500" />
                            Afiliación
                        </span>
                    </h2>
                    <div class="flex flex-wrap items-start gap-4">
                        <div class="w-full max-w-[11rem]">
                            <label :class="formLabelClass">Fecha de afiliación</label>
                            <DateMaskedInput v-model="form.fecha_afiliacion" required />
                            <p v-if="form.errors.fecha_afiliacion" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.fecha_afiliacion }}</p>
                        </div>
                        <div class="w-full max-w-[16rem]">
                            <label :class="formLabelClass">Estado</label>
                            <div
                                class="flex min-h-10 items-center rounded-xl border px-3 text-sm font-semibold"
                                :class="
                                    form.estado === 'baja'
                                        ? 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200'
                                        : form.estado === 'cortado'
                                          ? 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-500/40 dark:bg-orange-500/10 dark:text-orange-200'
                                          : 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200'
                                "
                            >
                                {{ form.estado === 'baja' ? 'Baja' : form.estado === 'cortado' ? 'Cortado' : 'Activo' }}
                            </div>
                            <label class="mt-2 inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                                    :checked="form.estado === 'baja'"
                                    @change="form.estado = ($event.target as HTMLInputElement).checked ? 'baja' : afiliado.estado === 'cortado' ? 'cortado' : 'activo'"
                                />
                                Dar de baja (manual)
                            </label>
                            <p class="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400">
                                Activo / Cortado los define el sistema por mora anual. No se editan a mano.
                            </p>
                            <p v-if="form.errors.estado" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.estado }}</p>
                        </div>
                        <div>
                            <p :class="formLabelClass">Forma de pago</p>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 transition"
                                    :class="
                                        form.forma_pago_afiliacion === 'contado'
                                            ? 'border-cyan-400 bg-cyan-50 dark:border-cyan-500 dark:bg-cyan-950/30'
                                            : 'border-slate-200 bg-slate-50/80 hover:border-cyan-300 dark:border-slate-700 dark:bg-slate-800/50'
                                    "
                                >
                                    <input v-model="form.forma_pago_afiliacion" type="radio" value="contado" class="size-4 border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-50">Al contado</span>
                                </label>
                                <label
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 transition"
                                    :class="
                                        form.forma_pago_afiliacion === 'plazo'
                                            ? 'border-cyan-400 bg-cyan-50 dark:border-cyan-500 dark:bg-cyan-950/30'
                                            : 'border-slate-200 bg-slate-50/80 hover:border-cyan-300 dark:border-slate-700 dark:bg-slate-800/50'
                                    "
                                >
                                    <input v-model="form.forma_pago_afiliacion" type="radio" value="plazo" class="size-4 border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-50">A plazo (mensual)</span>
                                </label>
                            </div>
                            <p v-if="form.errors.forma_pago_afiliacion" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.forma_pago_afiliacion }}</p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <p :class="formLabelClass">Servicio de afiliación (instalación)</p>
                        <div class="mt-2 flex flex-wrap gap-2.5">
                            <label
                                v-if="tipoAgua"
                                class="inline-flex w-[min(100%,14rem)] cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2.5 transition"
                                :class="
                                    afiliacionAgua
                                        ? 'border-cyan-400 bg-cyan-50 dark:border-cyan-500 dark:bg-cyan-950/30'
                                        : 'border-slate-200 bg-slate-50/80 hover:border-cyan-300 dark:border-slate-700 dark:bg-slate-800/50'
                                "
                            >
                                <input v-model="afiliacionAgua" type="checkbox" class="size-4 shrink-0 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-50">Agua potable</p>
                                    <p class="text-xs text-slate-500">Catálogo · {{ formatBs(montoAguaSegunForma) }}</p>
                                </div>
                            </label>
                            <label
                                v-if="tipoAlcantarillado"
                                class="inline-flex w-[min(100%,14rem)] cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2.5 transition"
                                :class="
                                    afiliacionAlcantarillado
                                        ? 'border-cyan-400 bg-cyan-50 dark:border-cyan-500 dark:bg-cyan-950/30'
                                        : 'border-slate-200 bg-slate-50/80 hover:border-cyan-300 dark:border-slate-700 dark:bg-slate-800/50'
                                "
                            >
                                <input v-model="afiliacionAlcantarillado" type="checkbox" class="size-4 shrink-0 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-50">Alcantarillado</p>
                                    <p class="text-xs text-slate-500">Catálogo · {{ formatBs(montoAlcSegunForma) }}</p>
                                </div>
                            </label>
                        </div>
                        <p v-if="form.errors.tipos_afiliacion" class="mt-2 text-xs font-medium text-red-600">{{ form.errors.tipos_afiliacion }}</p>

                        <div
                            v-if="afiliacionAgua || afiliacionAlcantarillado"
                            class="mt-3 space-y-2 rounded-xl border border-cyan-200/80 bg-cyan-50/70 p-3 dark:border-cyan-900/40 dark:bg-cyan-950/30"
                        >
                            <div class="grid items-stretch gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <div v-if="afiliacionAgua" class="flex flex-col rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/50">
                                    <label class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="monto_afiliacion_agua">Afiliación agua</label>
                                    <input
                                        id="monto_afiliacion_agua"
                                        :value="form.forma_pago_afiliacion === 'plazo' ? pagoInicialAgua : form.monto_afiliacion_agua"
                                        v-numeric.decimal
                                        type="text"
                                        inputmode="decimal"
                                        :class="inputClass('monto_afiliacion_agua')"
                                        @input="onInputMontoAgua(($event.target as HTMLInputElement).value)"
                                    />
                                    <p v-if="form.errors.monto_afiliacion_agua" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.monto_afiliacion_agua }}</p>
                                </div>
                                <div v-if="afiliacionAlcantarillado" class="flex flex-col rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/50">
                                    <label class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="monto_afiliacion_alcantarillado">Afiliación alcantarillado</label>
                                    <input
                                        id="monto_afiliacion_alcantarillado"
                                        :value="form.forma_pago_afiliacion === 'plazo' ? pagoInicialAlc : form.monto_afiliacion_alcantarillado"
                                        v-numeric.decimal
                                        type="text"
                                        inputmode="decimal"
                                        :class="inputClass('monto_afiliacion_alcantarillado')"
                                        @input="onInputMontoAlc(($event.target as HTMLInputElement).value)"
                                    />
                                    <p v-if="form.errors.monto_afiliacion_alcantarillado" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.monto_afiliacion_alcantarillado }}</p>
                                </div>
                                <div class="flex flex-col justify-center rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/50">
                                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total afiliación</p>
                                    <p class="text-base font-bold text-cyan-600 dark:text-cyan-400">
                                        {{ formatBs(form.monto_afiliacion) }}
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="form.forma_pago_afiliacion === 'plazo'"
                                class="grid items-stretch gap-2 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <div v-if="afiliacionAgua" class="flex flex-col rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/50">
                                    <label class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="saldo_pendiente_agua">Saldo pendiente agua</label>
                                    <input
                                        id="saldo_pendiente_agua"
                                        :value="saldoPendienteAgua"
                                        type="text"
                                        inputmode="decimal"
                                        readonly
                                        tabindex="-1"
                                        :class="[inputClass('saldo_afiliacion'), 'cursor-default bg-slate-50 dark:bg-slate-800/80']"
                                    />
                                    <p v-if="form.errors.saldo_afiliacion && !afiliacionAlcantarillado" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.saldo_afiliacion }}</p>
                                </div>
                                <div v-if="afiliacionAlcantarillado" class="flex flex-col rounded-lg bg-white/80 px-3 py-2 dark:bg-slate-900/50">
                                    <label class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="saldo_pendiente_alca">Saldo pendiente alcantarillado</label>
                                    <input
                                        id="saldo_pendiente_alca"
                                        :value="saldoPendienteAlc"
                                        type="text"
                                        inputmode="decimal"
                                        readonly
                                        tabindex="-1"
                                        :class="[inputClass('saldo_afiliacion'), 'cursor-default bg-slate-50 dark:bg-slate-800/80']"
                                    />
                                    <p v-if="form.errors.saldo_afiliacion" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.saldo_afiliacion }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div :class="formCardClass">
                    <div :class="formCardBodyClass">
                        <h2 :class="formCardTitleClass">
                            <span class="inline-flex items-center gap-2">
                                <Home class="size-4 text-cyan-500" />
                                Categoría
                            </span>
                        </h2>
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-[min(100%,13.5rem)]">
                                <label :class="[formLabelClass, 'text-center']">Categoría</label>
                                <select v-model.number="form.categoria_id" required :class="[inputClass('categoria_id'), 'text-center']">
                                    <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </select>
                                <p v-if="form.errors.categoria_id" class="mt-1 text-center text-xs font-medium text-red-600">{{ form.errors.categoria_id }}</p>
                            </div>
                            <div
                                v-if="categoriaSeleccionada"
                                class="w-[min(100%,13.5rem)] rounded-xl border border-cyan-200/80 bg-cyan-50/80 px-3 py-2.5 text-center dark:border-cyan-900/50 dark:bg-cyan-950/30"
                            >
                                <p class="text-sm text-slate-700 dark:text-slate-200">
                                    <template v-if="afiliacionAgua">
                                        Agua: <span class="font-semibold">Bs {{ categoriaSeleccionada.tarifa_agua.toFixed(2) }}</span>
                                    </template>
                                    <template v-if="afiliacionAgua && afiliacionAlcantarillado"><br /></template>
                                    <template v-if="afiliacionAlcantarillado">
                                        Alcantarillado: <span class="font-semibold">Bs {{ categoriaSeleccionada.tarifa_alcantarillado.toFixed(2) }}</span>
                                    </template>
                                    <template v-if="afiliacionAgua || afiliacionAlcantarillado">
                                        <br />
                                        Total: <span class="font-bold text-cyan-700 dark:text-cyan-300">Bs {{ total.toFixed(2) }}/mes</span>
                                    </template>
                                    <template v-else>
                                        Elija agua y/o alcantarillado para ver la tarifa.
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div :class="formCardClass">
                    <div :class="formCardBodyClass">
                        <h2 :class="formCardTitleClass">
                            <span class="inline-flex items-center gap-2">
                                <Droplets class="size-4 text-cyan-500" />
                                Servicios del lote
                            </span>
                        </h2>
                        <div class="flex flex-wrap items-stretch gap-2.5">
                            <label
                                v-for="s in servicios"
                                :key="s.id"
                                class="inline-flex min-w-[12rem] flex-1 cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 transition hover:border-cyan-300 hover:bg-cyan-50/50 has-[:checked]:border-cyan-400 has-[:checked]:bg-cyan-50 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-cyan-700 dark:has-[:checked]:border-cyan-500 dark:has-[:checked]:bg-cyan-950/30 sm:max-w-[16rem]"
                            >
                                <input
                                    v-model="form.servicios"
                                    type="checkbox"
                                    :value="s.id"
                                    class="size-4 shrink-0 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-50">{{ s.nombre }}</p>
                                    <p class="text-xs text-slate-500">
                                        Bs {{ tarifaServicio(s).toFixed(2) }}/mes
                                    </p>
                                </div>
                            </label>
                        </div>
                        <p v-if="form.errors.servicios" class="mt-2 text-xs font-medium text-red-600">{{ form.errors.servicios }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Link :href="`/afiliados/${afiliado.id}`" :class="formSecondaryButtonClass">Cancelar</Link>
                <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                    <Save class="size-4" />
                    Actualizar
                </button>
            </div>
        </form>
    </FormPageShell>
</template>
