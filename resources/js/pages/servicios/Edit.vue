s<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FormPageShell from '@/components/FormPageShell.vue';
import ServicioForm from '@/components/ServicioForm.vue';
import type { TarifaCategoria } from '@/components/ServicioForm.vue';

defineProps<{
    servicio: {
        id: number;
        nombre: string;
        tipo: string;
        tarifas_por_categoria: TarifaCategoria[];
        activo: boolean;
        lotes: number;
        es_base: boolean;
    };
    categoria_id: number | null;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Servicios', href: '/servicios' },
            { title: 'Editar servicio', href: '' },
        ],
    }),
});
</script>

<template>
    <Head title="Editar servicio" />

    <FormPageShell title="Editar servicio" back-href="/servicios" max-width="5xl">
        <ServicioForm :servicio="servicio" :tarifas="servicio.tarifas_por_categoria" :categoria-id="categoria_id" />
    </FormPageShell>
</template>
