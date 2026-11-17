<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        autocomplete?: string;
        required?: boolean;
        placeholder?: string;
        inputClass?: string;
        /** En formularios de administración conviene ver la clave al escribir. */
        defaultVisible?: boolean;
    }>(),
    {
        autocomplete: 'new-password',
        required: false,
        defaultVisible: false,
        inputClass:
            'w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-3 pr-11 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50',
    },
);

const model = defineModel<string>({ default: '' });
const visible = ref(props.defaultVisible);
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            v-model="model"
            :type="visible ? 'text' : 'password'"
            :name="name"
            :autocomplete="autocomplete"
            :required="required"
            :placeholder="placeholder"
            :class="inputClass"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-cyan-600 transition hover:text-cyan-700 dark:text-cyan-400 dark:hover:text-cyan-300"
            :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            :title="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="size-4" />
            <Eye v-else class="size-4" />
        </button>
    </div>
</template>
