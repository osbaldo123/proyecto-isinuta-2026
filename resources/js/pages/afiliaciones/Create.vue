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

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Afiliaciones', href: '/afiliaciones' },
            { title: 'Nueva afiliación', href: '/afiliaciones/create' },
        ],
    }),
});

const form = useForm({
    nombre: '',
    descripcion: '',
    lineas: [{ modalidad: 'Al contado', monto: 0, estado: 'Activa' as 'Activa' | 'Inactiva' }],
    estado: 'Activa',
});

const agregarLinea = () => {
    form.lineas.push({ modalidad: '', monto: 0, estado: 'Activa' });
};

const submit = () => {
    form.post('/afiliaciones');
};
</script>

<template>
    <Head title="Nueva afiliación" />

    <FormPageShell
        title="Nueva afiliación"
        subtitle="Agregue modalidades y montos en líneas"
        back-href="/afiliaciones"
        max-width="3xl"
    >
        <form :class="formCardClass" autocomplete="off" @submit.prevent="submit">
            <div class="space-y-4 px-5 py-5 sm:px-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-50">Datos de la afiliación</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label :class="formLabelClass">Nombre</label>
                        <input v-model="form.nombre" type="text" :class="formInputClass" placeholder="Nombre" required />
                        <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="formLabelClass">Descripción</label>
                        <textarea
                            v-model="form.descripcion"
                            rows="2"
                            :class="`${formInputClass} !h-auto min-h-[2.75rem] resize-y`"
                            placeholder="Instalación / implementación..."
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
                </div>
            </div>

            <div :class="formActionsClass">
                <Link href="/afiliaciones" :class="formSecondaryButtonClass">Cancelar</Link>
                <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                    <Save class="size-4" />
                    Guardar
                </button>
            </div>
        </form>
    </FormPageShell>
</template>
