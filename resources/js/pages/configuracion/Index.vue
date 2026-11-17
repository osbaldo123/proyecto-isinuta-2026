<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save, Settings } from 'lucide-vue-next';
import { computed } from 'vue';
import FormPageShell from '@/components/FormPageShell.vue';
import { usePermissions } from '@/composables/usePermissions';
import {
    formActionsClass,
    formCardClass,
    formInputClass,
    formLabelClass,
    formPrimaryButtonClass,
    formSecondaryButtonClass,
} from '@/lib/formStyles';

/**
 * Configuración de multas por mora: se abre desde Multas › «Configurar multas por mora».
 * - Multa trimestral: al completar 3 meses sin pagar, el día 1 del mes siguiente se aplica la multa.
 *   Ese mes se cobra normal y no entra en la cuenta, así que un año sin pagar acumula
 *   Bs 50 (1 de abril), Bs 100 (1 de agosto) y Bs 150 (1 de diciembre).
 * - Corte del servicio: meses vencidos sin pagar con los que el lote pasa a «Cortado».
 */

type ConfigItem = {
    clave: string;
    label: string;
    valor: number;
    tipo: string;
    descripcion: string | null;
};

type Campo = {
    clave: string;
    etiqueta: string;
    tipo: 'meses' | 'monto';
};

type Regla = {
    id: 'multa' | 'corte';
    titulo: string;
    campos: Campo[];
};

const props = defineProps<{
    configuraciones: ConfigItem[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Multas', href: '/multas' },
            { title: 'Configuración de multas por mora', href: '/configuracion' },
        ],
    }),
});

const { canManage, isReadOnly } = usePermissions();

const puedeEditar = computed(() => canManage('configuracion.gestionar') && !isReadOnly.value);

const reglas: Regla[] = [
    {
        id: 'multa',
        titulo: 'Multa trimestral',
        campos: [
            { clave: 'meses_multa_trimestral', etiqueta: 'Meses sin pagar', tipo: 'meses' },
            { clave: 'multa_trimestral', etiqueta: 'Monto de la multa (Bs)', tipo: 'monto' },
        ],
    },
    {
        id: 'corte',
        titulo: 'Corte del servicio',
        campos: [{ clave: 'meses_corte_servicio', etiqueta: 'Meses vencidos sin pagar', tipo: 'meses' }],
    },
];

const valorGuardado = (clave: string) => props.configuraciones.find((item) => item.clave === clave)?.valor;

const form = useForm(
    Object.fromEntries(
        reglas.flatMap((regla) => regla.campos).map((campo) => [campo.clave, valorGuardado(campo.clave) ?? '']),
    ) as Record<string, number>,
);

const submit = () => {
    form.put('/configuracion', { preserveScroll: true });
};

const formatBs = (n: number) =>
    new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n);

const valorDe = (clave: string): number | null => {
    const valor: unknown = form[clave];

    if (valor === null || valor === undefined || String(valor).trim() === '') {
        return null;
    }

    const numero = Number(valor);

    return Number.isFinite(numero) ? numero : null;
};

const nombresMes = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
];

const textoMeses = (meses: number) => (meses === 1 ? '1 mes' : `${meses} meses`);

/** Explica con los valores escritos cuándo se aplica la multa y cuánto acumula en un año sin pagar. */
const multa = computed(() => {
    const meses = valorDe('meses_multa_trimestral');
    const monto = valorDe('multa_trimestral');

    if (meses === null || monto === null || meses < 1 || monto < 0) {
        return null;
    }

    // Cada multa cuenta sus meses sin pagar y se aplica el día 1 del mes siguiente, que no se cuenta.
    const ciclo = Math.floor(meses) + 1;
    const ejemplo: { fecha: string; total: string }[] = [];

    for (let mes = ciclo, vez = 1; mes <= 12; mes += ciclo, vez++) {
        ejemplo.push({ fecha: `1 de ${nombresMes[mes - 1]}`, total: formatBs(monto * vez) });
    }

    return {
        regla: `Al completar ${textoMeses(meses)} sin pagar, el día 1 del mes siguiente se aplica una multa de ${formatBs(monto)}. Ese mes se cobra normal y no entra en la cuenta: los ${textoMeses(meses)} siguientes se cuentan desde el mes posterior. Las mensualidades se siguen cobrando cada mes.`,
        ejemplo,
        totalAnio: formatBs(monto * ejemplo.length),
    };
});

const corte = computed(() => {
    const meses = valorDe('meses_corte_servicio');

    if (meses === null || meses < 1) {
        return 'Complete los meses para ver cuándo se corta el servicio.';
    }

    return `Con ${textoMeses(meses)} vencidos sin pagar, el lote pasa a «Cortado». Vuelve a «Activo» cuando se pone al día con las mensualidades y las multas. El corte no genera otra multa. Si cambia este número, los lotes cortados se vuelven a revisar con el nuevo.`;
});

const campoClass = computed(() => [formInputClass, puedeEditar.value ? '' : 'cursor-not-allowed opacity-60']);
</script>

<template>
    <Head title="Configuración de multas por mora" />

    <FormPageShell
        title="Configuración de multas por mora"
        subtitle="Montos y plazos que el sistema usa para generar automáticamente las multas por mora."
        back-href="/multas"
        :icon="Settings"
    >
        <p
            v-if="!puedeEditar"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
            Modo consulta: puede revisar la configuración de las multas por mora, pero no modificarla.
        </p>

        <form :class="formCardClass" autocomplete="off" @submit.prevent="submit">
            <div class="grid gap-4 px-4 py-4 sm:px-6 sm:py-5 lg:grid-cols-2">
                <section
                    v-for="regla in reglas"
                    :key="regla.id"
                    class="flex min-w-0 flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40"
                >
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">{{ regla.titulo }}</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="campo in regla.campos" :key="campo.clave">
                            <label :for="campo.clave" :class="formLabelClass">{{ campo.etiqueta }}</label>
                            <input
                                v-if="campo.tipo === 'meses'"
                                :id="campo.clave"
                                v-model.number="form[campo.clave]"
                                v-numeric
                                type="number"
                                step="1"
                                min="1"
                                :max="regla.id === 'multa' ? 12 : 120"
                                required
                                :disabled="!puedeEditar"
                                :class="campoClass"
                            />
                            <input
                                v-else
                                :id="campo.clave"
                                v-model.number="form[campo.clave]"
                                v-numeric.decimal
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                :disabled="!puedeEditar"
                                :class="campoClass"
                            />
                            <p v-if="form.errors[campo.clave]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[campo.clave] }}
                            </p>
                        </div>
                    </div>

                    <template v-if="regla.id === 'multa'">
                        <template v-if="multa">
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ multa.regla }}</p>

                            <div
                                v-if="multa.ejemplo.length > 0"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900/40"
                            >
                                <p class="font-medium text-slate-800 dark:text-slate-100">Ejemplo: sin pagar desde enero</p>
                                <ul class="mt-1.5 flex flex-col gap-1 text-slate-600 dark:text-slate-400">
                                    <li v-for="paso in multa.ejemplo" :key="paso.fecha" class="flex justify-between gap-3">
                                        <span>{{ paso.fecha }}</span>
                                        <span class="tabular-nums text-slate-800 dark:text-slate-100">{{ paso.total }} acumulado</span>
                                    </li>
                                </ul>
                                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    En 12 meses sin pagar la multa suma {{ multa.totalAnio }}.
                                </p>
                            </div>
                        </template>
                        <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                            Complete los meses y el monto para ver cómo se aplicará la multa.
                        </p>
                    </template>

                    <p v-else class="text-sm text-slate-600 dark:text-slate-400">{{ corte }}</p>
                </section>
            </div>

            <p class="px-4 pb-4 text-xs text-slate-500 sm:px-6 dark:text-slate-400">
                Los cambios se aplican a las multas que el sistema genere desde ahora; las multas ya registradas conservan su monto.
            </p>

            <div v-if="puedeEditar" :class="formActionsClass">
                <Link href="/multas" :class="formSecondaryButtonClass">Cancelar</Link>
                <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                    <Save class="size-4 shrink-0" />
                    <span>Guardar configuración</span>
                </button>
            </div>
        </form>
    </FormPageShell>
</template>
