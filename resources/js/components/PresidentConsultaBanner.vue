<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const { hasRole, isReadOnly } = usePermissions();

const visible = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    if (!isReadOnly.value || !hasRole('presidente')) {
        return;
    }

    visible.value = true;
    timer = setTimeout(() => {
        visible.value = false;
    }, 3000);
});

onUnmounted(() => {
    if (timer) {
        clearTimeout(timer);
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-300 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
    >
        <div
            v-if="visible"
            class="border-b border-red-300 bg-red-100 px-4 py-2.5 text-center text-sm font-medium text-red-800 md:px-6 dark:border-red-500/50 dark:bg-red-950 dark:text-red-400"
            role="status"
        >
            <strong class="font-semibold">Administrador:</strong>
            revisión y supervisión · usuarios y roles.
        </div>
    </Transition>
</template>
