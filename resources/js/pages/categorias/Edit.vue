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
    formSplitClass,
    formSplitMainClass,
    formSplitSideClass,
} from '@/lib/formStyles';

const props = defineProps<{
    categoria: {
        id: number;
        nombre: string;
        descripcion: string;
        lotes: number;
        estado: string;
    };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Servicios', href: '/servicios' },
            { title: 'Categorías', href: '/categorias' },
            { title: 'Editar categoría', href: '#' },
        ],
    }),
});

const form = useForm({
    nombre: props.categoria.nombre,
    descripcion: props.categoria.descripcion,
    estado: props.categoria.estado,
});

const submit = () => {
    form.put(`/categorias/${props.categoria.id}`);
};
</script>

<template>
    <Head :title="`Editar ${categoria.nombre}`" />

    <FormPageShell :title="`Editar ${categoria.nombre}`" back-href="/categorias" max-width="5xl">
        <form :class="formSplitClass" autocomplete="off" @submit.prevent="submit">
            <div :class="formSplitMainClass">
                <div :class="formCardClass">
                    <div :class="formCardBodyClass">
                        <h2 :class="formCardTitleClass">Datos básicos</h2>
                        <p
                            v-if="categoria.lotes > 0"
                            class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
                        >
                            {{ categoria.lotes }} lote(s) usan esta categoría.
                        </p>
                        <div class="space-y-4">
                            <div>
                                <label :class="formLabelClass">Nombre</label>
                                <input v-model="form.nombre" type="text" :class="formInputClass" required />
                                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-600">{{ form.errors.nombre }}</p>
                            </div>
                            <div>
                                <label :class="formLabelClass">Descripción</label>
                                <textarea
                                    v-model="form.descripcion"
                                    rows="4"
                                    :class="`${formInputClass} !h-auto min-h-[6rem] resize-y`"
                                />
                                <p v-if="form.errors.descripcion" class="mt-1 text-xs text-red-600">{{ form.errors.descripcion }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div :class="formSplitSideClass">
                <div :class="formCardClass">
                    <div :class="formCardBodyClass">
                        <div class="space-y-4">
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
                </div>

                <div :class="formCardClass">
                    <div :class="formCardBodyClass">
                        <h2 :class="formCardTitleClass">Acciones</h2>
                        <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                            <Link href="/categorias" :class="formSecondaryButtonClass">Cancelar</Link>
                            <button type="submit" :disabled="form.processing" :class="formPrimaryButtonClass">
                                <Save class="size-4 shrink-0" />
                                Guardar cambios
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </FormPageShell>
</template>
