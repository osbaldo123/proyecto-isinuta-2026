<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save, ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import CiIdentidadInput from '@/components/CiIdentidadInput.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SouthAmericaPhoneInput from '@/components/SouthAmericaPhoneInput.vue';
import { formActionsClass, formCardClass } from '@/lib/formStyles';
import { pageShellClass, pageTitleClass } from '@/lib/pageStyles';
import { index, update } from '@/routes/usuarios';

const props = withDefaults(
    defineProps<{
        usuario: {
            id: number;
            nombres: string;
            apellidos: string;
            email: string;
            ci: string;
            telefono_codigo: string;
            telefono: string;
            rol: string;
            rol_label?: string;
            estado: string;
            fecha_ingreso?: string;
            fecha_final?: string;
            es_institucional?: boolean;
            rol_bloqueado?: boolean;
        };
        paisesTelefono: Array<{ value: string; label: string; dial: string }>;
        rolesDisponibles?: Array<{ value: string; label: string }>;
    }>(),
    {
        rolesDisponibles: () => [],
    },
);

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Usuarios de consulta', href: '/usuarios' },
            { title: 'Editar usuario', href: '#' },
        ],
    }),
});

const rolesFallback = [
    { value: 'consulta_pagos_multas', label: 'Consulta (pagos y multas)' },
    { value: 'cajera', label: 'Cajera' },
    { value: 'operador', label: 'Operador' },
];

const rolesOpciones = computed(() =>
    props.rolesDisponibles.length > 0 ? props.rolesDisponibles : rolesFallback,
);

const form = useForm({
    nombres: props.usuario.nombres,
    apellidos: props.usuario.apellidos,
    email: props.usuario.email,
    ci: props.usuario.ci,
    telefono_codigo: props.usuario.telefono_codigo,
    telefono: props.usuario.telefono,
    rol: props.usuario.rol || rolesOpciones.value[0]?.value || 'consulta_pagos_multas',
    rol_nuevo: '',
    estado: props.usuario.estado,
    fecha_ingreso: props.usuario.fecha_ingreso ?? '',
    fecha_final: props.usuario.fecha_final ?? '',
    password: '',
    password_confirmation: '',
});

const escribiendoRol = computed(() => form.rol === '__otro__');

const submit = () => {
    form.put(update.url(props.usuario.id));
};
</script>

<template>
    <Head title="Editar usuario de consulta" />

    <div :class="pageShellClass">
        <div class="flex min-w-0 items-center gap-2 sm:gap-3">
            <Link :href="index.url()" class="shrink-0 rounded-lg p-2 transition hover:bg-slate-100 dark:hover:bg-slate-800">
                <ArrowLeft class="size-5 text-slate-600 dark:text-slate-400" />
            </Link>
            <h1 :class="pageTitleClass">
                {{ usuario.es_institucional ? 'Editar usuario del sistema' : 'Editar usuario' }}
            </h1>
        </div>

        <form
            class="space-y-4 sm:space-y-5"
            autocomplete="off"
            data-lpignore="true"
            data-1p-ignore="true"
            @submit.prevent="submit"
        >
            <div :class="formCardClass" class="!p-4 sm:!p-6">
                <div class="mx-auto grid max-w-3xl grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                        <input
                            v-model="form.nombres"
                            type="text"
                            name="usuarios_nombres"
                            autocomplete="off"
                            autocapitalize="words"
                            spellcheck="false"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50"
                            required
                        />
                        <p v-if="form.errors.nombres" class="mt-1 text-sm text-red-600">{{ form.errors.nombres }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Apellido</label>
                        <input
                            v-model="form.apellidos"
                            type="text"
                            name="usuarios_apellidos"
                            autocomplete="off"
                            autocapitalize="words"
                            spellcheck="false"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50"
                            required
                        />
                        <p v-if="form.errors.apellidos" class="mt-1 text-sm text-red-600">{{ form.errors.apellidos }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Correo electrónico</label>
                        <input
                            v-model="form.email"
                            type="email"
                            name="usuarios_email"
                            autocomplete="off"
                            spellcheck="false"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50"
                            required
                        />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <CiIdentidadInput
                        v-model:ci="form.ci"
                        :ci-error="form.errors.ci"
                    />
                    <SouthAmericaPhoneInput
                        v-model:telefono-codigo="form.telefono_codigo"
                        v-model:telefono="form.telefono"
                        :paises="paisesTelefono"
                        :codigo-error="form.errors.telefono_codigo"
                        :telefono-error="form.errors.telefono"
                    />

                    <h2 class="pt-2 text-base font-semibold text-slate-900 sm:col-span-3 dark:text-slate-50">Acceso al sistema</h2>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Rol</label>
                        <template v-if="usuario.rol_bloqueado">
                            <input type="hidden" v-model="form.rol" />
                            <input
                                type="text"
                                :value="usuario.rol_label || usuario.rol"
                                readonly
                                class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm font-medium text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                            />
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                El rol institucional no se cambia aquí; sí puede editar datos, estado y dar de baja.
                            </p>
                        </template>
                        <template v-else>
                            <select
                                v-model="form.rol"
                                title="Elija un rol del módulo Roles"
                                class="w-full rounded-xl border border-cyan-400 bg-white px-3 py-2.5 text-sm font-medium text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-cyan-500 dark:bg-slate-800 dark:text-white"
                                required
                            >
                                <option
                                    v-for="rol in rolesOpciones"
                                    :key="rol.value"
                                    :value="rol.value"
                                >
                                    {{ rol.label }}
                                </option>
                                <option value="__otro__">Escribir otro rol…</option>
                            </select>
                            <input
                                v-if="escribiendoRol"
                                v-model="form.rol_nuevo"
                                type="text"
                                placeholder="Ej: Supervisor de OTB"
                                class="mt-2 w-full rounded-xl border border-cyan-400 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-cyan-500 dark:bg-slate-800 dark:text-white"
                                required
                            />
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Aquí aparecen Cajera, Operador, Consulta y los roles del módulo Roles. Presidente y Secretaria no se reasignan aquí.
                            </p>
                        </template>
                        <p v-if="form.errors.rol" class="mt-1 text-sm text-red-600">{{ form.errors.rol }}</p>
                        <p v-if="form.errors.rol_nuevo" class="mt-1 text-sm text-red-600">{{ form.errors.rol_nuevo }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Estado</label>
                        <select
                            v-model="form.estado"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50"
                            required
                        >
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Fecha de ingreso</label>
                        <input
                            v-model="form.fecha_ingreso"
                            type="date"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50"
                        />
                        <p v-if="form.errors.fecha_ingreso" class="mt-1 text-sm text-red-600">{{ form.errors.fecha_ingreso }}</p>
                    </div>
                    <div v-if="form.estado === 'Inactivo'">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Fecha final</label>
                        <input
                            v-model="form.fecha_final"
                            type="date"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-50"
                            required
                        />
                        <p v-if="form.errors.fecha_final" class="mt-1 text-sm text-red-600">{{ form.errors.fecha_final }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Nueva contraseña</label>
                        <PasswordInput
                            v-model="form.password"
                            name="usuarios_password"
                            autocomplete="new-password"
                            placeholder="Dejar en blanco para no cambiar"
                            default-visible
                        />
                        <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Confirmar contraseña</label>
                        <PasswordInput
                            v-model="form.password_confirmation"
                            name="usuarios_password_confirmation"
                            autocomplete="new-password"
                            default-visible
                        />
                    </div>
                </div>
            </div>

            <div :class="formActionsClass">
                <Link
                    :href="index.url()"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex items-center gap-2 rounded-xl bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-600 disabled:opacity-50"
                >
                    <Save class="size-4" />
                    Actualizar usuario
                </button>
            </div>
        </form>
    </div>
</template>
