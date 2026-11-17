<script setup lang="ts">
import { AlertTriangle, Trash2 } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const { state, accept, cancel } = useConfirmDialog();

const onOpenChange = (open: boolean) => {
    if (!open) {
        cancel();
    }
};
</script>

<template>
    <Dialog :open="state.open" @update:open="onOpenChange">
        <DialogContent
            class="max-w-md border-slate-700 bg-slate-900 text-slate-50 shadow-2xl shadow-black/50 sm:rounded-2xl"
            @pointer-down-outside.prevent
            @escape-key-down="cancel"
        >
            <DialogHeader class="space-y-3 text-left">
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                        :class="
                            state.variant === 'danger'
                                ? 'bg-red-500/15 text-red-400'
                                : state.variant === 'warning'
                                  ? 'bg-amber-500/15 text-amber-300'
                                  : 'bg-cyan-500/15 text-cyan-300'
                        "
                    >
                        <Trash2 v-if="state.variant === 'danger'" class="size-5" />
                        <AlertTriangle v-else class="size-5" />
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <DialogTitle class="text-lg font-semibold text-slate-50">
                            {{ state.title }}
                        </DialogTitle>
                        <DialogDescription class="text-sm leading-relaxed text-slate-400">
                            {{ state.message }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogFooter class="mt-2 flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-200 transition hover:bg-slate-700 sm:w-auto"
                    @click="cancel"
                >
                    {{ state.cancelLabel }}
                </button>
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition sm:w-auto"
                    :class="
                        state.variant === 'danger'
                            ? 'bg-red-500 hover:bg-red-600'
                            : state.variant === 'warning'
                              ? 'bg-amber-500 hover:bg-amber-600'
                              : 'bg-cyan-500 hover:bg-cyan-600'
                    "
                    @click="accept"
                >
                    {{ state.confirmLabel }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
