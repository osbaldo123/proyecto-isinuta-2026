<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FormPageShell from '@/components/FormPageShell.vue';
import ServicioForm from '@/components/ServicioForm.vue';
import type { TarifaCategoria } from '@/components/ServicioForm.vue';

defineProps<{
    tarifas_por_categoria: TarifaCategoria[];
    categoria_id: number | null;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Servicios', href: '/servicios' },
            { title: 'Nuevo servicio', href: '/servicios/create' },
        ],
    }),
});
</script>

<template>
    <Head title="Nuevo servicio" />

    <FormPageShell title="Nuevo servicio" back-href="/servicios" max-width="5xl">
        <ServicioForm :tarifas="tarifas_por_categoria" :categoria-id="categoria_id" />
    </FormPageShell>
</template>
