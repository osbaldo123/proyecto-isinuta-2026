import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();
    const auth = computed(() => page.props.auth ?? {});

    const roles = computed<string[]>(() => auth.value.roles ?? []);
    const permissions = computed<string[]>(() => auth.value.permissions ?? []);
    const isAdmin = computed<boolean>(() => auth.value.isAdmin || roles.value.includes('admin'));
    const isReadOnly = computed<boolean>(
        () => auth.value.readOnly ?? (roles.value.includes('presidente') && !isAdmin.value),
    );

    const can = (permission: string): boolean => {
        if (isAdmin.value) {
            return true;
        }

        return permissions.value.includes(permission);
    };

    const canManage = (permission: string): boolean => {
        if (isAdmin.value) {
            return true;
        }

        return can(permission);
    };

    const hasRole = (...requiredRoles: string[]): boolean =>
        requiredRoles.some((role) => roles.value.includes(role));

    return { can, canManage, hasRole, roles, permissions, isAdmin, isReadOnly };
}
