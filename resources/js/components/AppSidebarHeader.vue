<script setup lang="ts">
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-black/40 bg-[#06061b] px-3 shadow-[0_4px_18px_rgba(0,0,0,0.35)] transition-[width,height] ease-linear sm:gap-3 sm:px-6"
    >
        <SidebarTrigger
            class="-ml-0.5 shrink-0 rounded-lg border border-white/20 bg-white/10 text-white shadow-sm backdrop-blur-sm transition hover:border-white/40 hover:bg-white/20"
        />
    </header>
</template>
