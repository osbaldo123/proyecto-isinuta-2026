<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ClipboardList, Droplets, Home, Save, UserPlus, UserRound } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue';
import CiIdentidadInput from '@/components/CiIdentidadInput.vue';
import DateMaskedInput from '@/components/DateMaskedInput.vue';
import FormPageShell from '@/components/FormPageShell.vue';
import { useCategoriaTarifas } from '@/composables/useCategoriaTarifas';
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
type MontosAfiliacion = { agua: number; alcantarillado: number };
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
    otbs: OtbItem[];
    servicios: Servicio[];
    montos_afiliacion: MontosAfiliacion;
    tipos_afiliacion: TipoAfiliacionOption[];
    categorias: Categoria[];
    numero_lote_sugerido?: string;
    prefijo_nuevo_lote?: {
        desde_id: number;
        nombres: string;
        apellidos: string;
        ci: string;
        telefono: string | null;
        nombre_completo: string;
        numero_lote: string;
        lotes_usados: string[];
        propiedades: number;
    } | null;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Afiliados', href: '/afiliados' },
            { title: 'Nuevo afiliado', href: '/afiliados/create' },
        ],
    }),
});

const esNuevoLote = computed(() => Boolean(props.prefijo_nuevo_lote));

const form = useForm({
    nombres: props.prefijo_nuevo_lote?.nombres ?? '',
    apellidos: props.prefijo_nuevo_lote?.apellidos ?? '',
    ci: props.prefijo_nuevo_lote?.ci ?? '',
    telefono: props.prefijo_nuevo_lote?.telefono ?? '',
    direccion: '',
    numero_lote: props.prefijo_nuevo_lote?.numero_lote ?? props.numero_lote_sugerido ?? '1',
    otb_id: null as number | null,
    fecha_afiliacion: new Date().toISOString().slice(0, 10),
    forma_pago_afiliacion: 'contado' as 'contado' | 'plazo',
    tipos_afiliacion: [] as number[],
    monto_afiliacion_agua: 0,
    monto_afiliacion_alcantarillado: 0,
    monto_afiliacion: 0,
    saldo_afiliacion_agua: 0,
    saldo_afiliacion_alcantarillado: 0,
    saldo_afiliacion: 0,
    estado: 'activo' as 'activo' | 'cortado' | 'baja',
    categoria_id: props.categorias[0]?.id ?? null,
    observaciones: '',
    servicios: [] as number[],
});

const tipoAgua = computed(() => props.tipos_afiliacion.find((t) => t.codigo === 'agua') ?? null);
const tipoAlcantarillado = computed(() => props.tipos_afiliacion.find((t) => t.codigo === 'alcantarillado') ?? null);

const afiliacionAgua = ref(true);
const afiliacionAlcantarillado = ref(true);
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

const sincronizarAfiliacion = (opciones?: { resetMontos?: boolean; syncServicios?: boolean }) => {
    const resetMontos = opciones?.resetMontos ?? true;
    const syncServicios = opciones?.syncServicios ?? true;

    if (afiliacionAgua.value && tipoAgua.value) {
        if (resetMontos || !form.monto_afiliacion_agua) {
            form.monto_afiliacion_agua = montoAguaSegunForma.value;
        }
    } else {
        form.monto_afiliacion_agua = 0;
    }

    if (afiliacionAlcantarillado.value && tipoAlcantarillado.value) {
        if (resetMontos || !form.monto_afiliacion_alcantarillado) {
            form.monto_afiliacion_alcantarillado = montoAlcSegunForma.value;
        }
    } else {
        form.monto_afiliacion_alcantarillado = 0;
    }

    actualizarTiposAfiliacion();
    recalcularTotalAfiliacion();

    if (syncServicios) {
        syncingAfiliacionServicios.value = true;
        setServiciosPorTipo('agua', afiliacionAgua.value);
        setServiciosPorTipo('alcantarillado', afiliacionAlcantarillado.value);
        syncingAfiliacionServicios.value = false;
    }
};

sincronizarAfiliacion({ resetMontos: true, syncServicios: true });


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

type SiguienteLoteInfo = {
    existe: boolean;
    siguiente_lote: string;
    propiedades: number;
    nombre_completo: string | null;
    nombres?: string | null;
    apellidos?: string | null;
    telefono?: string | null;
    primer_id?: number | null;
    lotes_usados: string[];
};

const ciExistente = ref<SiguienteLoteInfo | null>(null);
const loteEditadoManualmente = ref(false);
let loteLookupTimer: ReturnType<typeof setTimeout> | null = null;

const aplicarDatosPersonaExistente = (data: SiguienteLoteInfo) => {
    if (!data.existe) {
        return;
    }

    if (data.nombres) {
        form.nombres = data.nombres;
    }
    if (data.apellidos) {
        form.apellidos = data.apellidos;
    }
    if (data.telefono) {
        form.telefono = data.telefono;
    }
    form.numero_lote = data.siguiente_lote;
};

const consultarSiguienteLote = async () => {
    const ci = String(form.ci ?? '').replace(/\D/g, '');
    if (ci.length !== 8) {
        if (!esNuevoLote.value) {
            ciExistente.value = null;
        }
        return;
    }

    const params = new URLSearchParams({ ci });
    if (form.otb_id) {
        params.set('otb_id', String(form.otb_id));
    }

    try {
        const res = await fetch(`/afiliados/siguiente-lote?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) return;

        const data = (await res.json()) as SiguienteLoteInfo;
        ciExistente.value = data;

        if (!loteEditadoManualmente.value) {
            form.numero_lote = data.siguiente_lote;
        }
    } catch {
        // Silencioso: la validación del servidor sigue protegiendo duplicados.
    }
};

const usarComoNuevoLote = () => {
    if (!ciExistente.value?.existe) {
        return;
    }

    if (ciExistente.value.primer_id) {
        router.visit(`/afiliados/create?desde=${ciExistente.value.primer_id}`);
        return;
    }

    aplicarDatosPersonaExistente(ciExistente.value);
};

watch(
    () => [form.ci, form.otb_id] as const,
    () => {
        if (loteLookupTimer) {
            clearTimeout(loteLookupTimer);
        }
        loteLookupTimer = setTimeout(() => {
            void consultarSiguienteLote();
        }, 350);
    },
);

onMounted(() => {
    if (esNuevoLote.value && props.prefijo_nuevo_lote) {
        ciExistente.value = {
            existe: true,
            siguiente_lote: props.prefijo_nuevo_lote.numero_lote,
            propiedades: props.prefijo_nuevo_lote.propiedades,
            nombre_completo: props.prefijo_nuevo_lote.nombre_completo,
            nombres: props.prefijo_nuevo_lote.nombres,
            apellidos: props.prefijo_nuevo_lote.apellidos,
            telefono: props.prefijo_nuevo_lote.telefono,
            primer_id: props.prefijo_nuevo_lote.desde_id,
            lotes_usados: props.prefijo_nuevo_lote.lotes_usados,
        };
    }
});

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
    form.post('/afiliados', {
        onError: async () => {
            await nextTick();
            document.getElementById('form-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
    });
};
</script>

<template>
    <Head :title="esNuevoLote ? 'Nuevo lote del afiliado' : 'Nuevo afiliado'" />

    <FormPageShell
        :title="esNuevoLote ? 'Agregar nuevo lote' : 'Nuevo afiliado'"
        :subtitle="
            esNuevoLote
                ? `Mismo afiliado (${prefijo_nuevo_lote?.nombre_completo}) · lote sugerido ${form.numero_lote || prefijo_nuevo_lote?.numero_lote}`
                : 'Registre los datos personales y del lote'
        "
        back-href="/afiliados"
        max-width="5xl"
        :icon="esNuevoLote ? Home : UserPlus"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div
                v-if="esNuevoLote && prefijo_nuevo_lote"
                class="rounded-xl border border-cyan-300 bg-cyan-50 px-4 py-3 text-sm text-cyan-950 dark:border-cyan-700 dark:bg-cyan-950 dark:text-cyan-100"
            >
                <p class="font-semibold">Nuevo lote para el mismo afiliado</p>
                <p class="mt-1">
                    Se mantiene el nombre y CI
                    <strong>{{ prefijo_nuevo_lote.ci }}</strong>
                    de <strong>{{ prefijo_nuevo_lote.nombre_completo }}</strong>.
                    Lotes actuales:
                    <strong>{{ prefijo_nuevo_lote.lotes_usados.join(', ') || '—' }}</strong>.
                    El número de lote se asignó automáticamente
                    (<strong>{{ form.numero_lote || prefijo_nuevo_lote.numero_lote }}</strong>)
                    y puede modificarlo. Complete OTB, dirección, afiliación y servicios.
                </p>
            </div>

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
                    <h2 :class="formCardTitleClass">
                        <span class="inline-flex items-center gap-2">
                            <UserRound class="size-4 text-cyan-500" />
                            Datos personales
                        </span>
                    </h2>

                    <div class="flex flex-wrap items-start gap-4">
                        <div class="w-full max-w-[14rem]">
                            <label :class="formLabelClass">Nombres</label>
                            <input
                                v-model="form.nombres"
                                maxlength="32"
                                type="text"
                                required
                                :readonly="esNuevoLote"
                                :class="[inputClass('nombres'), esNuevoLote ? 'bg-slate-100 dark:bg-slate-800' : '']"
                                placeholder="Inserte su nombre"
                            />
                            <p v-if="form.errors.nombres" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.nombres }}</p>
                        </div>
                        <div class="w-full min-w-[14rem] max-w-[22rem] flex-1">
                            <label :class="formLabelClass">Apellidos</label>
                            <input
                                v-model="form.apellidos"
                                maxlength="32"
                                type="text"
                                required
                                :readonly="esNuevoLote"
                                :class="[inputClass('apellidos'), esNuevoLote ? 'bg-slate-100 dark:bg-slate-800' : '']"
                                placeholder="Inserte su apellido"
                            />
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
                            <p v-else-if="esNuevoLote || ciExistente?.existe" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Este lote puede usar el mismo teléfono o uno distinto.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start">
                        <div id="seccion-ci-afiliado" class="shrink-0">
                            <CiIdentidadInput
                                v-model:ci="form.ci"
                                :ci-error="form.errors.ci"
                                :readonly="esNuevoLote"
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
                        <div class="w-full max-w-[14rem]">
                            <label :class="formLabelClass">Número de lote</label>
                            <input
                                v-model="form.numero_lote"
                                v-numeric
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                required
                                :class="inputClass('numero_lote')"
                                placeholder="Ej: 1, 2, 3"
                                @input="
                                    loteEditadoManualmente = true;
                                    form.numero_lote = String(($event.target as HTMLInputElement).value).replace(/\D/g, '');
                                "
                            />
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Asignado automáticamente. Puede cambiarlo.
                            </p>
                            <p v-if="form.errors.numero_lote" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.numero_lote }}</p>
                        </div>
                        <div class="w-full min-w-0 flex-1">
                            <label :class="formLabelClass">Dirección</label>
                            <input v-model="form.direccion" type="text" :class="inputClass('direccion')" placeholder="Calle, número, referencia de este lote" />
                            <p v-if="form.errors.direccion" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.direccion }}</p>
                        </div>
                    </div>

                    <div
                        v-if="!esNuevoLote && ciExistente?.existe"
                        class="mt-4 rounded-xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-900 dark:text-cyan-100"
                    >
                        <p>
                            Este CI ya pertenece a
                            <strong>{{ ciExistente.nombre_completo }}</strong>
                            ({{ ciExistente.propiedades }} propiedad(es), lotes:
                            {{ ciExistente.lotes_usados.join(', ') }}).
                        </p>
                        <p class="mt-2">
                            Puede registrar <strong>otro lote</strong> con el mismo nombre y CI.
                            Siguiente lote sugerido:
                            <strong>{{ ciExistente.siguiente_lote }}</strong>.
                        </p>
                        <button
                            type="button"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-500"
                            @click="usarComoNuevoLote"
                        >
                            <Home class="size-4" />
                            Agregar nuevo lote
                        </button>
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
                        <div class="w-full max-w-[14rem]">
                            <label :class="formLabelClass">Estado</label>
                            <div
                                class="flex min-h-10 items-center rounded-xl border border-emerald-300 bg-emerald-50 px-3 text-sm font-semibold text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200"
                            >
                                Activo
                            </div>
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
                <Link href="/afiliados" :class="formSecondaryButtonClass">Cancelar</Link>
                <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                    <Save class="size-4" />
                    {{ esNuevoLote ? 'Guardar nuevo lote' : 'Guardar' }}
                </button>
            </div>
        </form>
    </FormPageShell>
</template>
