<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import FormPageShell from '@/components/FormPageShell.vue';
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

const props = defineProps<{
    otb: {
        id: number;
        nombre: string;
        descripcion: string;
        afiliados: number;
        lotes: number;
        estado: string;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'OTB', href: '/otb' },
            { title: 'Editar OTB', href: '#' },
        ],
    }),
});

const form = useForm({
    nombre: props.otb.nombre,
    descripcion: props.otb.descripcion,
    estado: props.otb.estado,
});

const submit = () => {
    form.put(`/otb/${props.otb.id}`);
};
</script>

<template>
    <Head title="Editar OTB" />

    <FormPageShell title="Editar OTB" back-href="/otb" max-width="5xl">
        <form class="grid items-stretch gap-4 lg:grid-cols-12 lg:gap-5" autocomplete="off" @submit.prevent="submit">
            <div class="flex min-w-0 lg:col-span-8">
                <div :class="[formCardClass, 'flex w-full flex-col']">
                    <div :class="[formCardBodyClass, 'flex flex-1 flex-col']">
                        <h2 :class="formCardTitleClass">Datos básicos</h2>
                        <div class="flex flex-1 flex-col gap-4">
                            <div>
                                <label :class="formLabelClass">Nombre</label>
                                <input v-model="form.nombre" type="text" :class="formInputClass" required />
                                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
                            </div>
                            <div class="flex flex-1 flex-col">
                                <label :class="formLabelClass">Descripción</label>
                                <textarea
                                    v-model="form.descripcion"
                                    rows="4"
                                    :class="`${formInputClass} min-h-[6rem] flex-1 !h-auto resize-y`"
                                />
                                <p v-if="form.errors.descripcion" class="mt-1 text-xs text-red-600">{{ form.errors.descripcion }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-4 lg:col-span-4">
                <div :class="formCardClass">
                    <div :class="formCardBodyClass">
                        <h2 :class="formCardTitleClass">Estado</h2>
                        <div>
                            <label :class="formLabelClass">Estado</label>
                            <select v-model="form.estado" :class="formSelectClass" required>
                                <option value="Activa">Activa</option>
                                <option value="Inactiva">Inactiva</option>
                            </select>
                            <p v-if="form.errors.estado" class="mt-1 text-xs text-red-600">{{ form.errors.estado }}</p>
                        </div>
                    </div>
                </div>

                <div :class="[formCardClass, 'flex flex-1 flex-col']">
                    <div :class="[formCardBodyClass, 'flex flex-1 flex-col']">
                        <h2 :class="formCardTitleClass">Acciones</h2>
                        <div class="mt-auto flex flex-col gap-2">
                            <Link href="/otb" :class="[formSecondaryButtonClass, '!w-full']">Cancelar</Link>
                            <button type="submit" :disabled="form.processing" :class="[formPrimaryButtonClass, '!w-full']">
                                <Save class="size-4 shrink-0" />
                                Actualizar OTB
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </FormPageShell>
</template>
