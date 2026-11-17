<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formInputClass, formLabelClass } from '@/lib/formStyles';

const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const deleteUser = (e: Event) => {
    e.preventDefault();

    form.delete('/settings/profile', {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-red-200/80 bg-white shadow-sm dark:border-red-900/40 dark:bg-slate-900">
        <div class="border-b border-red-100 px-5 py-4 dark:border-red-900/40 sm:px-6">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-50">Eliminar cuenta</h2>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                Elimine su cuenta y todos sus datos asociados
            </p>
        </div>

        <div class="space-y-4 px-5 py-5 sm:px-6">
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-950/30">
                <p class="font-medium text-red-700 dark:text-red-200">Advertencia</p>
                <p class="mt-1 text-sm text-red-600/90 dark:text-red-200/80">
                    Esta acción no se puede deshacer. Proceda con precaución.
                </p>
            </div>

            <Dialog>
                <DialogTrigger as-child>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700"
                    >
                        Eliminar cuenta
                    </button>
                </DialogTrigger>
                <DialogContent>
                    <form class="space-y-6" @submit="deleteUser">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>¿Eliminar su cuenta?</DialogTitle>
                            <DialogDescription>
                                Una vez eliminada, todos los recursos y datos de la cuenta se borrarán de forma permanente.
                                Ingrese su contraseña para confirmar.
                            </DialogDescription>
                        </DialogHeader>

                        <div>
                            <label for="password" :class="formLabelClass">Contraseña</label>
                            <input
                                id="password"
                                ref="passwordInput"
                                v-model="form.password"
                                type="password"
                                name="password"
                                placeholder="Contraseña"
                                :class="formInputClass"
                            />
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                                    @click="closeModal"
                                >
                                    Cancelar
                                </button>
                            </DialogClose>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                Eliminar cuenta
                            </button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
