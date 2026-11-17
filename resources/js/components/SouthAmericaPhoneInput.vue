<script setup lang="ts">
import { onMounted } from 'vue';

const props = withDefaults(
    defineProps<{
        telefonoCodigo?: string;
        telefono?: string;
        paises?: Array<{ value: string; label: string; dial: string }>;
        codigoError?: string;
        telefonoError?: string;
        compact?: boolean;
    }>(),
    {
        compact: false,
        telefonoCodigo: '+591',
    },
);

const emit = defineEmits<{
    'update:telefonoCodigo': [value: string];
    'update:telefono': [value: string];
}>();

onMounted(() => {
    if (!props.telefonoCodigo) {
        emit('update:telefonoCodigo', '+591');
    }
});

const onTelefonoInput = (event: Event) => {
    const value = (event.target as HTMLInputElement).value.replace(/\D/g, '');
    emit('update:telefono', value);
};
</script>

<template>
    <div class="w-full max-w-[14rem]">
        <label :class="compact ? 'mb-1' : 'mb-1.5'" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Teléfono</label>
        <input type="hidden" name="usuarios_telefono_codigo" :value="telefonoCodigo || '+591'" />
        <input
            :value="telefono"
            v-numeric
            type="tel"
            name="usuarios_telefono_numero"
            inputmode="numeric"
            maxlength="12"
            placeholder="Número de teléfono"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            data-lpignore="true"
            data-1p-ignore="true"
            :class="compact
                ? 'h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50'
                : 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50'"
            @input="onTelefonoInput"
        />
        <p v-if="codigoError" class="mt-1 text-sm text-red-600">{{ codigoError }}</p>
        <p v-else-if="telefonoError" class="mt-1 text-sm text-red-600">{{ telefonoError }}</p>
    </div>
</template>
