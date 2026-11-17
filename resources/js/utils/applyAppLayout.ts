import AppLayout from '@/layouts/AppLayout.vue';
import type { DefineComponent, VNode } from 'vue';
import { h } from 'vue';

type BreadcrumbDefinition = {
    title: string;
    href: string | { url?: string };
};

type LayoutProps = {
    breadcrumbs?: BreadcrumbDefinition[];
};

function normalizeHref(href: string | { url?: string }): string {
    if (typeof href === 'string') {
        return href;
    }

    return href.url ?? '/';
}

function isLayoutProps(value: unknown): value is LayoutProps {
    return value !== null && typeof value === 'object' && 'breadcrumbs' in value;
}

export function applyAppLayout(component: DefineComponent): DefineComponent {
    const layout = (component as DefineComponent & { layout?: unknown }).layout;

    if (typeof layout !== 'function' || layout.length !== 0) {
        return component;
    }

    const layoutProps = layout();

    if (!isLayoutProps(layoutProps)) {
        return component;
    }

    const breadcrumbs = (layoutProps.breadcrumbs ?? []).map((item) => ({
        title: item.title,
        href: normalizeHref(item.href),
    }));

    (component as DefineComponent & { layout?: unknown }).layout = (layoutH: typeof h, page: VNode) =>
        layoutH(AppLayout, { breadcrumbs }, { default: () => page });

    return component;
}
