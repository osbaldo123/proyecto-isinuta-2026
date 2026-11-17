<script setup lang="ts">
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    CalendarDays,
    CreditCard,
    History,
    Home,
    MapPin,
    Pencil,
    Phone,
    Receipt,
    UserRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const { can, canManage } = usePermissions();

type CambioTitular = {
    id: number;
    fecha: string | null;
    anterior: { nombre: string; ci: string | null };
    nuevo: { nombre: string; ci: string | null };
    motivo: string;
    documento: string | null;
    registrado_por: string | null;
};

type Propiedad = {
    id: number;
    numero_lote: string;
    otb: string | null;
    direccion: string | null;
    estado: string;
    activa: boolean;
};

const props = defineProps<{
    afiliado: {
        id: number;
        afiliado_id?: number;
        codigo: string;
        nombre_completo: string;
        nombres: string;
        apellidos: string;
        ci: string;
        telefono: string | null;
        direccion: string | null;
        numero_lote: string;
        otb: string | null;
        fecha_afiliacion: string;
        forma_pago_afiliacion: string;
        monto_afiliacion: number;
        monto_afiliacion_agua: number;
        monto_afiliacion_alcantarillado: number;
        saldo_afiliacion_agua: number;
        saldo_afiliacion_alcantarillado: number;
        saldo_afiliacion: number;
        categoria_nombre: string;
        tiene_agua: boolean;
        tiene_alcantarillado: boolean;
        tarifa_agua: number | null;
        tarifa_alcantarillado: number | null;
        monto_categorizado: number | null;
        estado: string;
        observaciones: string | null;
        servicios: Array<{
            id: number;
            codigo: string;
            nombre: string;
            tarifa: number;
        }>;
    };
    propiedades: Propiedad[];
    historial_titulares?: CambioTitular[];
    resumen: {
        total_deuda: number;
        total_cuotas_pendientes: number;
        total_multas_pendientes: number;
        meses_pagados: number;
        meses_esperados: number;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Afiliados', href: '/afiliados' },
            { title: 'Detalle', href: '#' },
        ],
    }),
});

const formatBs = (n: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);

const tieneVariasPropiedades = computed(() => (props.propiedades?.length ?? 0) > 1);

const estadoLabel = computed(() => {
    const map: Record<string, string> = {
        activo: 'Activo',
        cortado: 'Cortado',
        baja: 'Baja',
    };

    return map[props.afiliado.estado] ?? props.afiliado.estado;
});

const estadoBadge = computed(() => {
    if (props.afiliado.estado === 'activo') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200';
    }

    if (props.afiliado.estado === 'cortado') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
    }

    return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
});

const formaPagoLabel = computed(() =>
    props.afiliado.forma_pago_afiliacion === 'plazo' ? 'A plazo' : 'Contado',
);

const esPlazo = computed(() => props.afiliado.forma_pago_afiliacion === 'plazo');

const muestraAfiliacionAgua = computed(
    () => props.afiliado.tiene_agua || Number(props.afiliado.monto_afiliacion_agua) > 0,
);

const muestraAfiliacionAlcantarillado = computed(
    () => props.afiliado.tiene_alcantarillado || Number(props.afiliado.monto_afiliacion_alcantarillado) > 0,
);

/** En a plazo: abono y saldo pendiente por servicio (valores guardados del lote). */
const desgloseAfiliacion = computed(() => {
    const montoAgua = Number(props.afiliado.monto_afiliacion_agua || 0);
    const montoAlc = Number(props.afiliado.monto_afiliacion_alcantarillado || 0);
    const saldoAgua = Math.min(montoAgua, Math.max(0, Number(props.afiliado.saldo_afiliacion_agua || 0)));
    const saldoAlc = Math.min(montoAlc, Math.max(0, Number(props.afiliado.saldo_afiliacion_alcantarillado || 0)));

    if (!esPlazo.value) {
        return {
            agua: { abono: montoAgua, pendiente: 0 },
            alc: { abono: montoAlc, pendiente: 0 },
        };
    }

    return {
        agua: {
            abono: Math.round((montoAgua - saldoAgua) * 100) / 100,
            pendiente: saldoAgua,
        },
        alc: {
            abono: Math.round((montoAlc - saldoAlc) * 100) / 100,
            pendiente: saldoAlc,
        },
    };
});

const cambiarLote = (propiedadId: number) => {
    if (propiedadId === props.afiliado.id) {
        return;
    }

    router.visit(`/afiliados/${propiedadId}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Afiliado — ${afiliado.nombre_completo}`" />

    <div :class="[pageShellClass, 'mx-auto max-w-5xl']">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link href="/afiliados" class="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <ArrowLeft class="size-5 text-slate-600 dark:text-slate-400" />
                </Link>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                    <UserRound class="size-5 text-white" />
                </div>
                <div>
                    <h1 :class="pageTitleClass">Detalle del afiliado</h1>
                    <p class="font-mono text-sm text-cyan-600">CI {{ afiliado.ci }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="canManage('pagos.gestionar')"
                    :href="`/pagos/registrar/nuevo?afiliado_id=${afiliado.id}`"
                    class="inline-flex items-center gap-2 rounded-lg bg-cyan-500 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-600"
                >
                    <CreditCard class="size-4" />
                    Cobrar deuda
                </Link>
                <Link
                    v-if="can('pagos.ver')"
                    :href="`/pagos?afiliado_id=${afiliado.id}`"
                    class="inline-flex items-center gap-2 rounded-lg border border-cyan-500/35 bg-cyan-500/10 px-3 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-500 hover:text-white dark:text-cyan-300"
                >
                    <Receipt class="size-4" />
                    Ver recibos
                </Link>
                <Link
                    v-if="can('pagos.ver') && propiedades.length > 0"
                    :href="`/pagos/estado-cuenta/${afiliado.id}`"
                    class="inline-flex items-center gap-2 rounded-lg border border-cyan-500/35 bg-cyan-500/10 px-3 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-500 hover:text-white dark:text-cyan-300"
                >
                    <CalendarDays class="size-4" />
                    Estado de cuenta
                </Link>
                <Link
                    v-if="canManage('afiliados.gestionar')"
                    :href="`/afiliados/create?desde=${afiliado.afiliado_id ?? afiliado.id}`"
                    class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-500"
                >
                    <Home class="size-4" />
                    Agregar nuevo lote
                </Link>
                <Link
                    v-if="canManage('afiliados.gestionar') && afiliado.estado !== 'baja' && propiedades.length > 0"
                    :href="`/afiliados/${afiliado.id}/transferir`"
                    class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-200 dark:hover:bg-amber-900/40"
                    :title="`Transferir el lote ${afiliado.numero_lote} a otra persona`"
                >
                    <ArrowRightLeft class="size-4" />
                    Transferir lote
                </Link>
                <Link
                    v-if="canManage('afiliados.gestionar')"
                    :href="`/afiliados/${afiliado.id}/edit`"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                >
                    <Pencil class="size-4" />
                    Editar
                </Link>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-slate-50">
                        {{ afiliado.nombre_completo }}
                    </h2>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="estadoBadge">
                        {{ estadoLabel }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-600 dark:text-slate-400">
                    <p>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">CI:</span>
                        {{ afiliado.ci }}
                    </p>
                    <p class="inline-flex items-center gap-2">
                        <Phone class="size-4 shrink-0 text-slate-400" />
                        {{ afiliado.telefono || 'Sin teléfono' }}
                    </p>
                    <p class="inline-flex items-center gap-2">
                        <MapPin class="size-4 shrink-0 text-slate-400" />
                        {{ afiliado.direccion || 'Sin dirección' }}
                    </p>
                    <p>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">Afiliado desde:</span>
                        {{ afiliado.fecha_afiliacion }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-cyan-500/30 bg-cyan-500/5 p-3 dark:border-cyan-400/25 dark:bg-cyan-500/10"
        >
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">
                        {{ tieneVariasPropiedades ? 'Seleccionar lote / propiedad' : 'Lotes / propiedades' }}
                    </p>
                </div>
                <Link
                    v-if="canManage('afiliados.gestionar')"
                    :href="`/afiliados/create?desde=${afiliado.afiliado_id ?? afiliado.id}`"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-cyan-500"
                >
                    <Home class="size-3.5" />
                    Agregar nuevo lote
                </Link>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="prop in propiedades"
                    :key="prop.id"
                    type="button"
                    class="inline-flex min-w-[7.5rem] flex-col items-start rounded-xl border px-3 py-2 text-left transition"
                    :class="
                        prop.activa
                            ? 'border-cyan-500 bg-cyan-500 text-white shadow-sm'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-cyan-400 hover:bg-cyan-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-cyan-500/50 dark:hover:bg-slate-800'
                    "
                    @click="cambiarLote(prop.id)"
                >
                    <span class="text-sm font-semibold">Lote {{ prop.numero_lote || '—' }}</span>
                    <span
                        class="mt-0.5 max-w-[11rem] truncate text-xs"
                        :class="prop.activa ? 'text-cyan-50' : 'text-slate-500 dark:text-slate-400'"
                    >
                        {{ prop.otb ? `OTB ${prop.otb}` : 'Sin OTB' }}
                    </span>
                </button>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Deuda total</p>
                <p class="mt-1 text-xl font-bold text-red-600 dark:text-red-400">{{ formatBs(resumen.total_deuda) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Cuotas pendientes</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-50">{{ formatBs(resumen.total_cuotas_pendientes) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Multas pendientes</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-50">{{ formatBs(resumen.total_multas_pendientes) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Meses pagados</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-50">
                    {{ resumen.meses_pagados }} / {{ resumen.meses_esperados }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-50">Datos de afiliación</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Forma de pago</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formaPagoLabel }}</dd>
                    </div>
                    <template v-if="esPlazo">
                        <div v-if="muestraAfiliacionAgua" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Afiliación agua</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(desgloseAfiliacion.agua.abono) }}</dd>
                        </div>
                        <div v-if="muestraAfiliacionAgua" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Saldo pendiente agua</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(desgloseAfiliacion.agua.pendiente) }}</dd>
                        </div>
                        <div v-if="muestraAfiliacionAlcantarillado" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Afiliación alcantarillado</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(desgloseAfiliacion.alc.abono) }}</dd>
                        </div>
                        <div v-if="muestraAfiliacionAlcantarillado" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Saldo pendiente alcantarillado</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(desgloseAfiliacion.alc.pendiente) }}</dd>
                        </div>
                    </template>
                    <template v-else>
                        <div v-if="muestraAfiliacionAgua" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Afiliación agua</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(afiliado.monto_afiliacion_agua) }}</dd>
                        </div>
                        <div v-if="muestraAfiliacionAlcantarillado" class="flex justify-between gap-3">
                            <dt class="text-slate-500">Afiliación alcantarillado</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(afiliado.monto_afiliacion_alcantarillado) }}</dd>
                        </div>
                    </template>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Total afiliación</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(afiliado.monto_afiliacion) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Categoría</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-50">{{ afiliado.categoria_nombre }}</dd>
                    </div>
                    <div v-if="afiliado.tarifa_agua != null" class="flex justify-between gap-3">
                        <dt class="text-slate-500">Tarifa agua</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(afiliado.tarifa_agua) }}</dd>
                    </div>
                    <div v-if="afiliado.tarifa_alcantarillado != null" class="flex justify-between gap-3">
                        <dt class="text-slate-500">Tarifa alcantarillado</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-50">{{ formatBs(afiliado.tarifa_alcantarillado) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Total mensual</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-50">
                            {{ afiliado.monto_categorizado != null ? formatBs(afiliado.monto_categorizado) : '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-50">Servicios de este lote</h3>
                <ul v-if="afiliado.servicios.length" class="space-y-2">
                    <li
                        v-for="servicio in afiliado.servicios"
                        :key="servicio.id"
                        class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-800"
                    >
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-50">{{ servicio.nombre }}</p>                        </div>
                        <p class="text-sm font-semibold text-cyan-600">{{ formatBs(servicio.tarifa) }}</p>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-500">Este lote no tiene servicios asignados.</p>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-50">
                <History class="size-4 text-cyan-600" />
                Historial de titulares del lote {{ afiliado.numero_lote }}
            </h3>
            <p v-if="!historial_titulares || historial_titulares.length === 0" class="text-sm text-slate-500">
                Este lote no tiene transferencias registradas: {{ afiliado.nombre_completo }} es su titular desde la afiliación.
            </p>
            <ol v-else class="space-y-3">
                <li
                    v-for="cambio in historial_titulares"
                    :key="cambio.id"
                    class="rounded-xl border border-slate-100 p-3 text-sm dark:border-slate-800"
                >
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="rounded-full bg-cyan-500/10 px-2 py-0.5 text-xs font-semibold text-cyan-700 dark:text-cyan-300">
                            {{ cambio.fecha }}
                        </span>
                        <span class="text-slate-600 dark:text-slate-300">
                            {{ cambio.anterior.nombre }}<span v-if="cambio.anterior.ci" class="text-slate-400"> (CI {{ cambio.anterior.ci }})</span>
                        </span>
                        <ArrowRightLeft class="size-3.5 shrink-0 text-slate-400" />
                        <span class="font-semibold text-slate-900 dark:text-slate-50">
                            {{ cambio.nuevo.nombre }}<span v-if="cambio.nuevo.ci" class="font-normal text-slate-400"> (CI {{ cambio.nuevo.ci }})</span>
                        </span>
                    </div>
                    <p class="mt-1.5 text-slate-600 dark:text-slate-400">
                        <span class="font-medium text-slate-700 dark:text-slate-300">Motivo:</span> {{ cambio.motivo }}
                        <template v-if="cambio.documento"> · <span class="font-medium text-slate-700 dark:text-slate-300">Documento:</span> {{ cambio.documento }}</template>
                    </p>
                    <p v-if="cambio.registrado_por" class="mt-0.5 text-xs text-slate-400">Registrado por {{ cambio.registrado_por }}</p>
                </li>
            </ol>
        </section>
    </div>
</template>
