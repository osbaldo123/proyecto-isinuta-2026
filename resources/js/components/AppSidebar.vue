<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    BarChart3,
    ClipboardList,
    Droplets,
    FileText,
    LayoutGrid,
    MapPin,
    Receipt,
    Shield,
    UserRound,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, type Component } from 'vue';

type NavEntry = {
    title: string;
    href: string;
    icon: Component;
    permission?: string;
    anyPermission?: string[];
};

const { can } = usePermissions();
const page = usePage();
const { isMobile, setOpenMobile } = useSidebar();

const closeMobileDrawer = () => {
    if (isMobile.value) {
        setOpenMobile(false);
    }
};

let removeNavigateListener: (() => void) | null = null;

onMounted(() => {
    // En teléfono: al elegir cualquier módulo, cerrar el drawer automáticamente.
    removeNavigateListener = router.on('navigate', () => {
        closeMobileDrawer();
    });
});

onUnmounted(() => {
    removeNavigateListener?.();
});

const allNavItems: NavEntry[] = [
    { title: 'Panel', href: '/dashboard', icon: LayoutGrid, permission: 'panel.ver' },
    { title: 'Afiliados', href: '/afiliados', icon: Users, permission: 'afiliados.ver' },
    { title: 'Servicios', href: '/servicios', icon: Droplets, permission: 'servicios.ver' },
    {
        title: 'Afiliaciones',
        href: '/afiliaciones',
        icon: BadgeDollarSign,
        anyPermission: ['afiliaciones.ver', 'afiliaciones.gestionar', 'afiliados.gestionar'],
    },
    { title: 'Trabajos', href: '/trabajos', icon: ClipboardList, permission: 'trabajos.ver' },
    { title: 'Gestionar pagos', href: '/pagos/registrar/nuevo', icon: Wallet, permission: 'pagos.gestionar' },
    { title: 'Recibos', href: '/pagos', icon: FileText, permission: 'pagos.ver' },
    { title: 'Multas', href: '/multas', icon: Receipt, permission: 'multas.ver' },
    { title: 'OTB', href: '/otb', icon: MapPin, permission: 'otb.ver' },
    { title: 'Reportes', href: '/reportes', icon: BarChart3, permission: 'reportes.ver' },
    {
        title: 'Usuarios',
        href: '/usuarios',
        icon: UserRound,
        anyPermission: ['usuarios.gestionar', 'usuarios.gestionar_consulta'],
    },
    { title: 'Roles', href: '/roles', icon: Shield, permission: 'roles.ver' },
    // La configuración de multas por mora se abre desde el botón de la pantalla Multas.
];

const mainNavItems = computed(() =>
    allNavItems.filter((item) => {
        if (item.anyPermission) {
            return item.anyPermission.some((p) => can(p));
        }

        if (item.permission) {
            return can(item.permission);
        }

        return true;
    }),
);

const isActive = (href: string) => {
    const url = page.url.split('?')[0];

    if (href === '/dashboard') {
        return url === '/dashboard' || url === '/panel';
    }

    // Gestionar pagos solo en registrar; Recibos en listado y ficha de recibo.
    if (href === '/pagos/registrar/nuevo') {
        return url.startsWith('/pagos/registrar');
    }

    if (href === '/servicios') {
        return url.startsWith('/servicios') || url.startsWith('/categorias');
    }

    if (href === '/pagos') {
        return url === '/pagos' || /^\/pagos\/(\d+|estado-cuenta|cobro)(\/|$)/.test(url);
    }

    // La configuración de multas por mora forma parte del módulo Multas.
    if (href === '/multas') {
        return url === '/multas' || url.startsWith('/multas/') || url === '/configuracion';
    }

    return url === href || url.startsWith(`${href}/`);
};
</script>

<template>
    <!-- Menú siempre azul (contrasta con el panel blanco en tema claro) -->
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="border-r border-cyan-900/40 bg-slate-900 text-slate-100"
    >
        <SidebarHeader class="!h-14 !flex-row !items-center !gap-0 !p-0 border-b border-cyan-900/40 bg-slate-950 px-2">
            <SidebarMenu class="w-full">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-12 text-slate-100 hover:bg-cyan-700 hover:text-white data-[active=true]:bg-cyan-600 data-[active=true]:text-white"
                    >
                        <Link :href="route('dashboard')" @click="closeMobileDrawer">
                            <div class="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-md bg-white shadow-sm shadow-cyan-900/40">
                                <img
                                    src="/images/logo-isinuta.png"
                                    alt="ISINUTA"
                                    class="size-full object-contain p-0.5"
                                />
                            </div>
                            <div class="ml-1 grid flex-1 text-left text-sm">
                                <span class="truncate text-[10px] font-semibold uppercase tracking-wider text-cyan-300">
                                    Administración
                                </span>
                                <span class="truncate font-semibold leading-none text-cyan-50">
                                    isinuta
                                </span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="bg-slate-900">
            <SidebarGroup class="px-2.5 py-3">
                <SidebarGroupLabel class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-cyan-400">
                    Menú principal
                </SidebarGroupLabel>
                <SidebarMenu class="gap-1.5">
                    <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(item.href)"
                            class="isinuta-nav-item group/nav relative h-10 !overflow-visible rounded-xl px-3.5 text-slate-300"
                        >
                            <Link :href="item.href" class="relative gap-3" @click="closeMobileDrawer">
                                <span class="isinuta-nav-shine" aria-hidden="true" />
                                <span
                                    class="absolute inset-y-1.5 left-0 w-1 rounded-r-full bg-cyan-300 opacity-0 shadow-[0_0_10px_rgba(34,211,238,0.95)] transition-opacity duration-200 group-data-[active=true]/nav:opacity-100"
                                />
                                <component
                                    :is="item.icon"
                                    class="relative z-10 size-4 shrink-0 text-cyan-400 transition duration-200 group-hover/nav:text-cyan-100 group-data-[active=true]/nav:text-white group-data-[active=true]/nav:drop-shadow-[0_0_10px_rgba(34,211,238,1)]"
                                />
                                <span class="relative z-10 truncate tracking-wide">
                                    {{ item.title }}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="border-t border-cyan-900/40 bg-slate-950 text-slate-100">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
