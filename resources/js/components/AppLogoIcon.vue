<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path
            fill="currentColor"
            d="M12 2.4c.35.6 1.2 2 2.4 3.85C16.8 9.35 19 12.6 19 15.4c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.8 2.2-6.05 4.6-9.15C10.8 4.4 11.65 3 12 2.4Z"
        />
    </svg>
</template>
