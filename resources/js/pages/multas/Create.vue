<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
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

type AfiliadoOption = {
    id: number;
    codigo: string;
    nombre_completo: string;
    ci: string;
};

defineProps<{
    afiliados: AfiliadoOption[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Multas', href: '/multas' },
            { title: 'Registrar multa', href: '/multas/create' },
        ],
    }),
});

const form = useForm({
    afiliado_id: null as number | null,
    fecha_generacion: new Date().toISOString().slice(0, 10),
    descripcion: '',
    monto: 0,
});

const submit = () => form.post('/multas');
</script>

<template>
    <Head title="Registrar multa" />

    <FormPageShell title="Registrar multa" back-href="/multas">
        <form :class="formCardClass" autocomplete="off" @submit.prevent="submit">
            <div class="space-y-4 px-5 py-5 sm:px-6">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label :class="formLabelClass">Afiliado</label>
                        <select v-model="form.afiliado_id" :class="formSelectClass" required>
                            <option :value="null">Seleccionar afiliado...</option>
                            <option v-for="a in afiliados" :key="a.id" :value="a.id">
                                CI {{ a.ci }} — {{ a.nombre_completo }}
                            </option>
                        </select>
                        <p v-if="form.errors.afiliado_id" class="mt-1 text-xs text-red-600">{{ form.errors.afiliado_id }}</p>
                    </div>

                    <div>
                        <label :class="formLabelClass">Fecha</label>
                        <input v-model="form.fecha_generacion" type="date" required :class="formInputClass" />
                        <p v-if="form.errors.fecha_generacion" class="mt-1 text-xs text-red-600">{{ form.errors.fecha_generacion }}</p>
                    </div>

                    <div>
                        <label :class="formLabelClass">Monto (Bs)</label>
                        <input v-model.number="form.monto" v-numeric.decimal type="number" step="0.01" min="0.01" required :class="formInputClass" />
                        <p v-if="form.errors.monto" class="mt-1 text-xs text-red-600">{{ form.errors.monto }}</p>
                    </div>
                </div>

                <div class="max-w-md">
                    <label :class="formLabelClass">Descripción</label>
                    <textarea
                        v-model="form.descripcion"
                        rows="2"
                        required
                        :class="`${formInputClass} !h-auto min-h-[2.75rem] resize-y`"
                        placeholder="Motivo de esta multa"
                    />
                    <p v-if="form.errors.descripcion" class="mt-1 text-xs text-red-600">{{ form.errors.descripcion }}</p>
                </div>
            </div>

            <div :class="formActionsClass">
                <Link href="/multas" :class="formSecondaryButtonClass">Cancelar</Link>
                <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                    <Save class="size-4 shrink-0" />
                    <span>Registrar multa</span>
                </button>
            </div>
        </form>
    </FormPageShell>
</template>
