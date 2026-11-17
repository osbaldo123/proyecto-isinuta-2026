<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Save } from 'lucide-vue-next';
import {
    formCardBodyClass,
    formCardClass,
    formCardTitleClass,
    formInputClass,
    formLabelClass,
    formPrimaryButtonClass,
    formSecondaryButtonClass,
    formSelectClass,
} from '@/lib/formStyles';

export type TarifaCategoria = {
    categoria_id: number;
    categoria: string;
    monto: number;
    lotes: number;
};

type LineaTarifa = {
    categoria_id: number | null;
    categoria: string;
    monto: number;
    estado: 'Activa' | 'Inactiva';
    lotes: number;
};

const props = defineProps<{
    servicio?: {
        id: number;
        nombre: string;
        tipo: string;
        activo: boolean;
        lotes: number;
        es_base: boolean;
    };
    tarifas: TarifaCategoria[];
    categoriaId?: number | null;
}>();

const lineasIniciales = (): LineaTarifa[] => {
    const tarifas = props.tarifas ?? [];

    if (tarifas.length === 0) {
        return [{ categoria_id: null, categoria: '', monto: 0, estado: 'Activa', lotes: 0 }];
    }

    // Editar: todas las tarifas del servicio en líneas.
    if (props.servicio) {
        return tarifas.map((t) => ({
            categoria_id: t.categoria_id > 0 ? t.categoria_id : null,
            categoria: t.categoria,
            monto: Number(t.monto) || 0,
            estado: 'Activa' as const,
            lotes: t.lotes ?? 0,
        }));
    }

    // Crear: una línea vacía (o la categoría elegida si viene en la URL).
    const elegida = tarifas.find((t) => t.categoria_id === props.categoriaId) ?? tarifas[0];

    return [
        {
            categoria_id: elegida?.categoria_id > 0 ? elegida.categoria_id : null,
            categoria: elegida?.categoria ?? '',
            monto: 0,
            estado: 'Activa',
            lotes: elegida?.lotes ?? 0,
        },
    ];
};

const form = useForm({
    nombre: props.servicio?.nombre ?? '',
    tipo: props.servicio?.tipo || 'otro',
    lineas: lineasIniciales(),
    activo: (props.servicio?.activo ?? true) as boolean,
});

const agregarLinea = () => {
    form.lineas.push({
        categoria_id: null,
        categoria: '',
        monto: 0,
        estado: 'Activa',
        lotes: 0,
    });
};

const errorLinea = (index: number, campo: string) => {
    const errores = form.errors as Record<string, string | undefined>;

    return errores[`lineas.${index}.${campo}`] ?? errores[`lineas.${index}`];
};

const submit = () => {
    const enviar = form.transform(({ lineas, ...datos }) => ({
        ...datos,
        lineas: lineas.map((linea) => ({
            categoria_id: linea.categoria_id && linea.categoria_id > 0 ? linea.categoria_id : null,
            categoria_nombre: linea.categoria,
            monto: linea.monto,
            estado: linea.estado,
        })),
    }));

    if (props.servicio) {
        enviar.put(`/servicios/${props.servicio.id}`);
    } else {
        enviar.post('/servicios');
    }
};
</script>

<template>
    <form autocomplete="off" @submit.prevent="submit">
        <div :class="formCardClass">
            <div :class="formCardBodyClass">
                <h2 :class="formCardTitleClass">Datos del servicio</h2>
                <p
                    v-if="servicio?.es_base"
                    class="mb-4 rounded-lg bg-cyan-50 px-3 py-2 text-xs text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200"
                >
                    Servicio base (agua o alcantarillado). Divida categoría y tarifa en líneas dentro del formulario.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="servicio-nombre" :class="formLabelClass">Servicio</label>
                        <input
                            id="servicio-nombre"
                            v-model="form.nombre"
                            type="text"
                            required
                            :class="formInputClass"
                            placeholder="Ej. Agua potable"
                        />
                        <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
                    </div>
                    <div>
                        <label for="servicio-estado" :class="formLabelClass">Estado del servicio</label>
                        <select
                            id="servicio-estado"
                            :class="formSelectClass"
                            :value="form.activo ? '1' : '0'"
                            @change="form.activo = ($event.target as HTMLSelectElement).value === '1'"
                        >
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <p v-if="form.errors.activo" class="mt-1 text-xs text-red-600">{{ form.errors.activo }}</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-50">Categorías y tarifas</p>
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
                                <label :class="formLabelClass">Categoría</label>
                                <input
                                    v-model="linea.categoria"
                                    type="text"
                                    required
                                    maxlength="100"
                                    :class="formInputClass"
                                    placeholder="Ej. Domiciliar"
                                />
                                <p v-if="errorLinea(index, 'categoria_nombre')" class="mt-1 text-xs text-red-600">
                                    {{ errorLinea(index, 'categoria_nombre') }}
                                </p>
                            </div>
                            <div>
                                <label :class="formLabelClass">Tarifa/mes (Bs)</label>
                                <input
                                    v-model.number="linea.monto"
                                    v-numeric.decimal
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    :class="formInputClass"
                                    placeholder="0.00"
                                />
                                <p v-if="errorLinea(index, 'monto')" class="mt-1 text-xs text-red-600">
                                    {{ errorLinea(index, 'monto') }}
                                </p>
                            </div>
                            <div>
                                <label :class="formLabelClass">Estado</label>
                                <select v-model="linea.estado" :class="formSelectClass">
                                    <option value="Activa">Activa</option>
                                    <option value="Inactiva">Inactiva</option>
                                </select>
                                <p v-if="errorLinea(index, 'estado')" class="mt-1 text-xs text-red-600">
                                    {{ errorLinea(index, 'estado') }}
                                </p>
                                <p v-if="linea.lotes > 0" class="mt-1 text-xs text-slate-500">
                                    Usado en {{ linea.lotes }} lote(s)
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.lineas" class="text-xs text-red-600">{{ form.errors.lineas }}</p>
                    <p v-if="form.errors.tarifas" class="text-xs text-red-600">{{ form.errors.tarifas }}</p>
                </div>

                <div class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end dark:border-slate-800">
                    <Link href="/servicios" :class="[formSecondaryButtonClass, 'sm:w-40']">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" :class="[formPrimaryButtonClass, 'sm:w-40']">
                        <Save class="size-4 shrink-0" />
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
