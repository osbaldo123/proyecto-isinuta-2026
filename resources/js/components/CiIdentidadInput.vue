<script setup lang="ts">
import { computed } from 'vue';
import { formInputClass, formLabelClass } from '@/lib/formStyles';

const props = withDefaults(
    defineProps<{
        ci?: string;
        ciError?: string;
        readonly?: boolean;
    }>(),
    {
        readonly: false,
    },
);

const emit = defineEmits<{
    'update:ci': [value: string];
}>();

const ciValue = computed({
    get: () => props.ci ?? '',
    set: (value: string) => {
        emit('update:ci', value.replace(/\D/g, '').slice(0, 8));
    },
});
</script>

<template>
    <div class="w-full">
        <label :class="formLabelClass">Número de CI</label>
        <input
            v-model="ciValue"
            v-numeric
            type="text"
            name="usuarios_ci"
            inputmode="numeric"
            maxlength="8"
            placeholder="Ej: 12345678"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            data-lpignore="true"
            data-1p-ignore="true"
            :readonly="readonly"
            :class="[
                formInputClass,
                readonly ? 'bg-slate-100 dark:bg-slate-800' : '',
                ciError ? 'border-red-400 focus:border-red-500 focus:ring-red-400/30 dark:border-red-500' : '',
            ]"
        />
        <p v-if="ciError" class="mt-1 text-sm font-medium text-red-600">{{ ciError }}</p>
    </div>
</template>
