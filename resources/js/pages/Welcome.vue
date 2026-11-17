<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    Droplets,
    Receipt,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const institucion = computed(() => String(page.props.name ?? 'ISINUTA'));
const anio = new Date().getFullYear();

/** Tamaño bloqueado del fondo: evita que el cover se reescale al mostrar/ocultar la barra del navegador. */
const bgFrameStyle = ref<Record<string, string>>({
    width: '100%',
    height: '100%',
});

function lockBackgroundFrame() {
    const width = window.innerWidth;
    const height = Math.max(window.innerHeight, document.documentElement.clientHeight);

    bgFrameStyle.value = {
        width: `${width}px`,
        height: `${height}px`,
    };
}

function onOrientationChange() {
    // Esperar a que el viewport se estabilice tras rotar
    window.setTimeout(lockBackgroundFrame, 150);
}

onMounted(() => {
    lockBackgroundFrame();
    window.addEventListener('orientationchange', onOrientationChange);
});

onUnmounted(() => {
    window.removeEventListener('orientationchange', onOrientationChange);
});

const modulos = [
    {
        icon: Users,
        titulo: 'Afiliados',
        descripcion: 'Registro, otbs y estado de servicio de cada conexión.',
    },
    {
        icon: Receipt,
        titulo: 'Pagos y recibos',
        descripcion: 'Cobros, emisión de recibos y seguimiento de cuentas.',
    },
    {
        icon: BarChart3,
        titulo: 'Reportes',
        descripcion: 'Deudas, multas y resúmenes operativos al instante.',
    },
    {
        icon: ShieldCheck,
        titulo: 'Roles y permisos',
        descripcion: 'Acceso controlado para secretaría, consulta y administración.',
    },
];
</script>

<template>
    <Head title="Bienvenida" />

    <!-- Fondo fijo: tamaño bloqueado al cargar (no se reescala al deslizar en móvil) -->
    <div class="welcome-bg-root pointer-events-none fixed inset-0 z-0 overflow-hidden bg-sky-950" aria-hidden="true">
        <div class="welcome-bg-frame absolute left-1/2 top-1/2" :style="bgFrameStyle">
            <div
                class="welcome-bg-image absolute inset-0 bg-cover bg-center bg-no-repeat"
                style="background-image: url('/images/welcome-grifo.png')"
            />
            <div class="absolute inset-0 bg-sky-950/15 dark:bg-slate-950/35" />
        </div>
    </div>

    <div
        class="relative z-10 flex min-h-svh flex-col overflow-x-hidden text-slate-900 lg:h-svh lg:overflow-hidden dark:text-white"
    >
        <header class="relative w-full shrink-0 pt-[env(safe-area-inset-top)]">
            <div
                class="flex w-full items-center justify-between border-b border-white/20 bg-white/90 px-3 py-2.5 shadow-sm backdrop-blur-sm sm:px-6 sm:py-3 lg:px-8 dark:border-slate-700 dark:bg-slate-900/90"
            >
                <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-2 sm:gap-3">
                    <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-sky-500 text-white shadow-md sm:size-11">
                            <AppLogoIcon class="size-4 fill-current sm:size-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-cyan-700 sm:text-[11px] dark:text-cyan-300">
                                Administración
                            </p>
                            <p class="truncate text-sm font-bold leading-tight sm:text-lg">ISINUTA</p>
                        </div>
                    </div>

                    <nav class="shrink-0">
                        <Link
                            v-if="page.props.auth.user"
                            :href="route('dashboard')"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-cyan-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition active:scale-[0.97] hover:bg-cyan-500 sm:gap-2 sm:px-5 sm:py-2.5 sm:text-sm"
                        >
                            Ir al panel
                            <ArrowRight class="size-3.5 sm:size-4" />
                        </Link>
                        <Link
                            v-else
                            :href="route('login')"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-cyan-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition active:scale-[0.97] hover:bg-cyan-500 sm:gap-2 sm:px-5 sm:py-2.5 sm:text-sm"
                        >
                            Iniciar sesión
                            <ArrowRight class="size-3.5 sm:size-4" />
                        </Link>
                    </nav>
                </div>
            </div>
        </header>

        <div
            class="relative mx-auto flex w-full max-w-6xl flex-1 flex-col px-3 sm:px-6 lg:min-h-0 lg:px-8"
        >
            <main
                class="welcome-scroll flex flex-1 flex-col justify-start py-4 sm:py-5 lg:min-h-0 lg:items-center lg:justify-center lg:overflow-y-auto lg:py-6"
            >
                <div class="grid w-full items-stretch gap-4 sm:gap-5 lg:grid-cols-[1.1fr_0.9fr] lg:gap-8">
                    <!-- Panel izquierdo -->
                    <div
                        class="flex flex-col justify-center space-y-4 rounded-2xl border border-white/40 bg-white/30 p-4 shadow-xl backdrop-blur-md sm:space-y-5 sm:rounded-3xl sm:p-7 dark:border-slate-700/40 dark:bg-slate-900/30"
                    >
                        <span
                            class="welcome-chip inline-flex w-fit max-w-full flex-wrap items-center gap-1.5 rounded-full border border-cyan-400/80 bg-cyan-500/15 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-cyan-950 shadow-[0_0_20px_rgba(6,182,212,0.45)] backdrop-blur-sm sm:gap-2 sm:px-4 sm:py-2 sm:text-xs dark:border-cyan-300/80 dark:bg-cyan-400/20 dark:text-cyan-50"
                        >
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-cyan-500 text-white shadow-[0_0_12px_rgba(6,182,212,0.8)] sm:size-6">
                                <Droplets class="size-3 sm:size-3.5" />
                            </span>
                            <span class="bg-gradient-to-r from-cyan-800 via-teal-700 to-cyan-900 bg-clip-text text-transparent dark:from-cyan-100 dark:via-teal-200 dark:to-cyan-50">
                                <span class="sm:hidden">Agua · Alcantarillado · Isinuta</span>
                                <span class="hidden sm:inline">Agua potable · Alcantarillado · Isinuta</span>
                            </span>
                        </span>

                        <div class="space-y-2.5 sm:space-y-3">
                            <h1
                                class="text-[clamp(1.25rem,4.6vw,2.25rem)] font-bold leading-snug tracking-tight text-slate-900 dark:text-white"
                            >
                                Sistema web para la gestión administrativa de la
                                Asociación de Agua Potable y Alcantarillado
                                <span class="text-cyan-700 dark:text-cyan-300">Isinuta</span>
                            </h1>
                            <p
                                class="max-w-xl text-[0.8125rem] font-semibold leading-relaxed text-slate-900 sm:text-base dark:text-white"
                            >
                                Administra afiliados, cobros, multas y reportes desde un solo lugar.
                            </p>
                        </div>

                        <p class="text-xs font-bold text-slate-900 sm:text-sm dark:text-white">
                            {{ institucion }}
                        </p>
                    </div>

                    <!-- Panel derecho: lista vertical -->
                    <aside class="flex w-full">
                        <div
                            class="flex w-full flex-col justify-center rounded-2xl border border-white/40 bg-white/30 p-3.5 shadow-xl backdrop-blur-md sm:rounded-3xl sm:p-5 dark:border-slate-700/40 dark:bg-slate-900/30"
                        >
                            <div class="mb-3 flex items-center gap-2.5 sm:mb-4 sm:gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-cyan-500 text-white shadow-md sm:size-11">
                                    <Droplets class="size-4 sm:size-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-900 sm:text-sm dark:text-white">
                                        Servicio comunitario
                                    </p>
                                    <p class="text-sm font-bold text-slate-900 sm:text-lg dark:text-white">
                                        Agua potable y alcantarillado
                                    </p>
                                </div>
                            </div>

                            <ul class="space-y-2 sm:space-y-2.5">
                                <li
                                    v-for="modulo in modulos"
                                    :key="modulo.titulo"
                                    class="group flex items-start gap-2.5 rounded-2xl border border-sky-600 bg-sky-500 px-3 py-2.5 transition active:scale-[0.99] hover:-translate-y-0.5 hover:bg-sky-600 hover:shadow-md sm:gap-3 sm:px-3.5 sm:py-3"
                                >
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-sky-600 text-white transition group-hover:scale-110 sm:size-9">
                                        <component :is="modulo.icon" class="size-3.5 sm:size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold leading-tight text-white sm:text-base">
                                            {{ modulo.titulo }}
                                        </p>
                                        <p class="mt-0.5 text-[0.8125rem] leading-snug text-sky-50 sm:text-sm">
                                            {{ modulo.descripcion }}
                                        </p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </main>

            <footer class="shrink-0 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-2 sm:pb-4">
                <div
                    class="flex flex-col items-center gap-1 rounded-2xl border border-white/40 bg-white/30 px-3 py-2.5 text-center text-[11px] font-semibold text-slate-800 shadow-sm backdrop-blur-md sm:flex-row sm:justify-between sm:gap-1.5 sm:px-5 sm:py-3 sm:text-left sm:text-sm dark:border-slate-600/40 dark:bg-slate-900/30 dark:text-slate-100"
                >
                    <span class="inline-flex items-center gap-2">
                        <Droplets class="size-3.5 shrink-0 text-cyan-600 dark:text-cyan-400" />
                        © {{ anio }} ISINUTA - Gestión Administrativa.
                    </span>
                    <span class="text-slate-700 dark:text-slate-300">Acceso restringido a personal autorizado.</span>
                </div>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.welcome-bg-frame {
    /* Anclado al centro; un poco más grande para absorber cambios de barra sin bloquear scroll */
    transform: translate(-50%, -50%) scale(1.12);
    transform-origin: center center;
}

.welcome-bg-image {
    background-attachment: scroll;
}

.welcome-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
    -webkit-overflow-scrolling: touch;
}

.welcome-scroll::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}

.welcome-chip {
    animation: chip-glow 2.8s ease-in-out infinite;
}

@keyframes chip-glow {
    0%,
    100% {
        box-shadow:
            0 0 16px rgba(6, 182, 212, 0.35),
            inset 0 1px 0 rgba(255, 255, 255, 0.35);
    }

    50% {
        box-shadow:
            0 0 28px rgba(6, 182, 212, 0.65),
            0 0 48px rgba(20, 184, 166, 0.25),
            inset 0 1px 0 rgba(255, 255, 255, 0.5);
    }
}

@media (prefers-reduced-motion: reduce) {
    .welcome-chip {
        animation: none;
    }
}
</style>
