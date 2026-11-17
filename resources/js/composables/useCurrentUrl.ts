import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toUrl } from '@/lib/utils';

export function useCurrentUrl() {
    const page = usePage();
    const currentUrl = computed(() => page.url.split('?')[0]);

    const isCurrentUrl = (url: string) => currentUrl.value === toUrl(url);

    const isCurrentOrParentUrl = (url: string) => {
        const target = toUrl(url);

        return currentUrl.value === target || currentUrl.value.startsWith(`${target}/`);
    };

    return { currentUrl, isCurrentUrl, isCurrentOrParentUrl };
}
