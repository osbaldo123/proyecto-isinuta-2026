<script setup lang="ts">
import { Calendar } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { formInputClass } from '@/lib/formStyles';
import { shouldBlockNumericKey } from '@/lib/numericInput';

withDefaults(
    defineProps<{
        id?: string;
        required?: boolean;
        placeholder?: string;
    }>(),
    {
        required: false,
        placeholder: 'DD/MM/AAAA',
    },
);

const model = defineModel<string>({ default: '' });

const textInput = ref<HTMLInputElement | null>(null);
const pickerInput = ref<HTMLInputElement | null>(null);
const display = ref('');
const focused = ref(false);

const isoToDisplay = (iso: string): string => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return '';
    }

    const [year, month, day] = iso.split('-');

    return `${day}/${month}/${year}`;
};

const digitsOnly = (value: string): string => value.replace(/\D/g, '').slice(0, 8);

const formatWithSlashes = (digits: string): string => {
    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 4) {
        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
};

const isValidIsoDate = (iso: string): boolean => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return false;
    }

    const [year, month, day] = iso.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));

    return (
        date.getUTCFullYear() === year &&
        date.getUTCMonth() === month - 1 &&
        date.getUTCDate() === day
    );
};

const displayToIso = (value: string): string | null => {
    const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) {
        return null;
    }

    const iso = `${match[3]}-${match[2]}-${match[1]}`;

    return isValidIsoDate(iso) ? iso : null;
};

watch(
    model,
    (value) => {
        if (!focused.value) {
            display.value = isoToDisplay(value ?? '');
        }
    },
    { immediate: true },
);

const onType = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const formatted = formatWithSlashes(digitsOnly(target.value));
    display.value = formatted;
    target.value = formatted;

    if (formatted.length === 0) {
        model.value = '';
        return;
    }

    const iso = displayToIso(formatted);
    model.value = iso ?? '';
};

const onKeydown = (event: KeyboardEvent) => {
    if (shouldBlockNumericKey(event, 'integer')) {
        event.preventDefault();
    }
};

const openPicker = () => {
    const el = pickerInput.value;
    if (!el) {
        return;
    }

    if (typeof el.showPicker === 'function') {
        el.showPicker();
        return;
    }

    el.click();
};

const onPickerChange = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    model.value = value;
    display.value = isoToDisplay(value);
};

const pickerValue = computed(() => (isValidIsoDate(model.value) ? model.value : ''));
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            ref="textInput"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            maxlength="10"
            :required="required"
            :placeholder="placeholder"
            :value="display"
            :class="[formInputClass, '!pr-11']"
            @focus="focused = true"
            @blur="focused = false"
            @keydown="onKeydown"
            @input="onType"
        />

        <input
            ref="pickerInput"
            type="date"
            tabindex="-1"
            aria-hidden="true"
            class="pointer-events-none absolute h-0 w-0 opacity-0"
            :value="pickerValue"
            @change="onPickerChange"
        />

        <button
            type="button"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition hover:text-cyan-500 dark:text-slate-500 dark:hover:text-cyan-400"
            title="Abrir calendario"
            aria-label="Abrir calendario"
            tabindex="-1"
            @click="openPicker"
        >
            <Calendar class="size-4" />
        </button>
    </div>
</template>
