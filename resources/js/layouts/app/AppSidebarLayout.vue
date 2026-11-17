<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ConfirmDialogHost from '@/components/ConfirmDialogHost.vue';
import FlashToastHost from '@/components/FlashToastHost.vue';
import PresidentConsultaBanner from '@/components/PresidentConsultaBanner.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="isinuta-content-compact flex min-w-0 flex-1 flex-col bg-[#eef1f6] dark:bg-slate-950">
                <PresidentConsultaBanner />
                <slot />
            </div>
        </AppContent>
        <ConfirmDialogHost />
        <FlashToastHost />
    </AppShell>
</template>
