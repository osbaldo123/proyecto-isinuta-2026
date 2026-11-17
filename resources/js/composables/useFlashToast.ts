import { ref } from 'vue';

export type ClientToastType = 'success' | 'error' | 'info';

export type ClientToast = {
    id: number;
    message: string;
    type: ClientToastType;
    duration: number;
};

const clientToast = ref<ClientToast | null>(null);
let nextId = 0;

export function showToast(
    message: string,
    type: ClientToastType = 'info',
    duration = 5000,
): void {
    clientToast.value = { id: ++nextId, message, type, duration };
}

export function useClientToastQueue() {
    return clientToast;
}
