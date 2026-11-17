<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const sidebarNavItems = [
    { title: 'Perfil', href: '/settings/profile' },
    { title: 'Seguridad', href: '/settings/password' },
    { title: 'Apariencia', href: '/settings/appearance' },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="min-h-[calc(100vh-4rem)] w-full bg-[#eef1f6] dark:bg-slate-950">
        <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-50">
                    Configuración de cuenta
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Administre su perfil y preferencias
                </p>
            </div>

            <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
                <aside class="w-full shrink-0 lg:w-52">
                    <nav
                        class="flex flex-row gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:flex-col"
                        aria-label="Configuración de cuenta"
                    >
                        <Link
                            v-for="item in sidebarNavItems"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'rounded-xl px-3.5 py-2.5 text-sm font-medium whitespace-nowrap transition',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-cyan-100 text-cyan-900 ring-1 ring-cyan-200 dark:bg-cyan-600 dark:text-white dark:ring-cyan-500'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-50',
                            ]"
                        >
                            {{ item.title }}
                        </Link>
                    </nav>
                </aside>

                <div class="min-w-0 flex-1">
                    <div class="max-w-xl space-y-8">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
