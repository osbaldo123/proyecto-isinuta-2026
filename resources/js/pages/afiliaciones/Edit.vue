<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Save } from 'lucide-vue-next';
import FormPageShell from '@/components/FormPageShell.vue';
import {
    formActionsClass,
    formCardClass,
    formInputClass,
    formLabelClass,
    formPrimaryButtonClass,
    formSecondaryButtonClass,
    formSelectClass,
} from '@/lib/formStyles';

type LineaModalidad = {
    modalidad: string;
    monto: number;
    estado: 'Activa' | 'Inactiva';
};

const props = defineProps<{
    tipo: {
        id: number;
        codigo: string;
        nombre: string;
        descripcion: string;
        modalidad: string;
        monto: number;
        monto_plazo: number;
        orden: number;
        lotes: number;
        estado: string;
        es_sistema: boolean;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Afiliaciones', href: '/afiliaciones' },
            { title: 'Editar', href: '' },
        ],
    }),
});

const esPlazo = (texto: string) => {
    const t = texto.toLowerCase();
    return t.includes('plazo') || t.includes('cuota');
};

const lineasIniciales = (): LineaModalidad[] => {
    const contado = Number(props.tipo.monto) || 0;
    const plazo = Number(props.tipo.monto_plazo) || 0;
    const etiquetas = (props.tipo.modalidad || '')
        .split('/')
        .map((parte) => parte.trim())
        .filter(Boolean);

    if (etiquetas.length >= 2) {
        return etiquetas.map((etiqueta) => ({
            modalidad: etiqueta,
            monto: esPlazo(etiqueta) ? plazo : contado,
            estado: 'Activa' as const,
        }));
    }

    if (etiquetas.length === 1) {
        const etiqueta = etiquetas[0];
        if (esPlazo(etiqueta) && plazo > 0) {
            return [{ modalidad: etiqueta, monto: plazo, estado: 'Activa' }];
        }
        if (contado > 0 || plazo === 0) {
            return [{ modalidad: etiqueta, monto: contado || plazo, estado: 'Activa' }];
        }
        return [{ modalidad: etiqueta, monto: plazo, estado: 'Activa' }];
    }

    if (props.tipo.es_sistema || (contado > 0 && plazo > 0)) {
        return [
            { modalidad: 'Al contado', monto: contado, estado: 'Activa' },
            { modalidad: 'A plazo', monto: plazo, estado: 'Activa' },
        ];
    }

    if (plazo > 0 && contado === 0) {
        return [{ modalidad: 'A plazo', monto: plazo, estado: 'Activa' }];
    }

    return [{ modalidad: 'Al contado', monto: contado, estado: 'Activa' }];
};

const form = useForm({
    nombre: props.tipo.nombre,
    descripcion: props.tipo.descripcion,
    lineas: lineasIniciales(),
    estado: props.tipo.estado,
});

const agregarLinea = () => {
    form.lineas.push({ modalidad: '', monto: 0, estado: 'Activa' });
};

const submit = () => {
    form.put(`/afiliaciones/${props.tipo.id}`);
};
</script>

<template>
    <Head :title="`Editar — ${tipo.nombre}`" />

    <FormPageShell title="Editar afiliación" :subtitle="tipo.codigo" back-href="/afiliaciones" max-width="3xl">
        <form :class="formCardClass" autocomplete="off" @submit.prevent="submit">
            <div class="space-y-4 px-5 py-5 sm:px-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Datos de la afiliación</h2>
                <p v-if="tipo.es_sistema" class="rounded-lg bg-cyan-50 px-3 py-2 text-xs text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200">
                    Tipo base (agua o alcantarillado). Divida modalidad y monto en líneas.
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label :class="formLabelClass">Nombre</label>
                        <input v-model="form.nombre" type="text" :class="formInputClass" required />
                        <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="formLabelClass">Descripción</label>
                        <textarea
                            v-model="form.descripcion"
                            rows="2"
                            :class="`${formInputClass} !h-auto min-h-[2.75rem] resize-y`"
                        />
                        <p v-if="form.errors.descripcion" class="mt-1 text-xs text-red-600">{{ form.errors.descripcion }}</p>
                    </div>

                    <div class="sm:col-span-2 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-50">Modalidades y montos</p>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                                @click="agregarLinea"
                            >
                                <Plus class="size-3.5" />
                                Agregar línea
                            </button>
                        </div>

                        <div
                            v-for="(linea, index) in form.lineas"
                            :key="index"
                            class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40"
                            :class="linea.estado === 'Inactiva' ? 'opacity-60' : ''"
                        >
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label :class="formLabelClass">Modalidad</label>
                                    <input
                                        v-model="linea.modalidad"
                                        type="text"
                                        required
                                        maxlength="50"
                                        :class="formInputClass"
                                        placeholder="Ej. Al contado o A plazo"
                                    />
                                    <p
                                        v-if="form.errors[`lineas.${index}.modalidad`]"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ form.errors[`lineas.${index}.modalidad`] }}
                                    </p>
                                </div>
                                <div>
                                    <label :class="formLabelClass">Monto (Bs)</label>
                                    <input
                                        v-model.number="linea.monto"
                                        v-numeric.decimal
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        :class="formInputClass"
                                    />
                                    <p
                                        v-if="form.errors[`lineas.${index}.monto`]"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ form.errors[`lineas.${index}.monto`] }}
                                    </p>
                                </div>
                                <div>
                                    <label :class="formLabelClass">Estado</label>
                                    <select v-model="linea.estado" :class="formSelectClass">
                                        <option value="Activa">Activa</option>
                                        <option value="Inactiva">Inactiva</option>
                                    </select>
                                    <p
                                        v-if="form.errors[`lineas.${index}.estado`]"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ form.errors[`lineas.${index}.estado`] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p v-if="form.errors.lineas" class="text-xs text-red-600">{{ form.errors.lineas }}</p>
                    </div>

                    <div>
                        <label :class="formLabelClass">Estado de la afiliación</label>
                        <select v-model="form.estado" :class="formSelectClass">
                            <option value="Activa">Activa</option>
                            <option value="Inactiva">Inactiva</option>
                        </select>
                        <p v-if="form.errors.estado" class="mt-1 text-xs text-red-600">{{ form.errors.estado }}</p>
                    </div>
                    <div class="flex items-end">
                        <p class="text-sm text-slate-500">Usado en {{ tipo.lotes }} lote(s)</p>
                    </div>
                </div>
            </div>

            <div :class="formActionsClass">
                <Link href="/afiliaciones" :class="formSecondaryButtonClass">Cancelar</Link>
                <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                    <Save class="size-4" />
                    Guardar cambios
                </button>
            </div>
        </form>
    </FormPageShell>
</template>
