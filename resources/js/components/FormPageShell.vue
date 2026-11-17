<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        backHref?: string;
        maxWidth?: '3xl' | '4xl' | '5xl' | '6xl' | 'full';
        icon?: Component;
    }>(),
    {
        maxWidth: 'full',
    },
);

const innerClass = computed(() => {
    const widths = {
        '3xl': 'max-w-3xl',
        '4xl': 'max-w-4xl',
        '5xl': 'max-w-5xl',
        '6xl': 'max-w-6xl',
        full: 'max-w-none',
    };

    return `mx-auto flex w-full min-w-0 flex-col gap-3 px-3 py-3 sm:gap-4 sm:px-6 sm:py-4 lg:px-8 ${widths[props.maxWidth]}`;
});
</script>

<template>
    <div class="min-h-[calc(100vh-4rem)] w-full overflow-x-hidden">
        <div :class="innerClass">
            <div v-if="title || $slots.header" class="flex min-w-0 items-start gap-2 sm:gap-3">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="mt-0.5 shrink-0 rounded-xl border border-slate-200 bg-white p-2 text-slate-500 shadow-sm transition hover:border-cyan-200 hover:text-cyan-600 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-cyan-800 dark:hover:text-cyan-400"
                >
                    <ArrowLeft class="size-4" />
                </Link>
                <slot name="header">
                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                                <div
                                    v-if="icon"
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 text-white shadow-sm"
                                >
                                    <component :is="icon" class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <h1 v-if="title" class="truncate text-xl font-bold tracking-tight text-slate-900 dark:text-slate-50 sm:text-2xl">
                                        {{ title }}
                                    </h1>
                                    <p v-if="subtitle" class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                                        {{ subtitle }}
                                    </p>
                                </div>
                            </div>
                            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
                                <slot name="actions" />
                            </div>
                        </div>
                    </div>
                </slot>
            </div>
            <slot />
        </div>
    </div>
</template>
