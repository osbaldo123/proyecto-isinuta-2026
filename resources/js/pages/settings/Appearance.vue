<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/settings/profile' },
    { title: 'Apariencia', href: '/settings/appearance' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Apariencia" />

        <SettingsLayout>
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">Apariencia</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Elija el tema claro, oscuro o del sistema
                    </p>
                </div>
                <div class="px-5 py-5 sm:px-6">
                    <AppearanceTabs />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
