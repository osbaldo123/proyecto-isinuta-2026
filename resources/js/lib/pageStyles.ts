/**
 * Escala visual ISINUTA (referencia: Panel / dashboard).
 * Misma tipografía y espaciado para Presidente y Secretaria en todo el sistema.
 * La escala global del área de trabajo vive en `.isinuta-content-compact` (app.css).
 */

/** Contenedor de listados y pantallas internas (alineado al Panel). */
export const pageShellClass =
    'flex h-full min-w-0 flex-1 flex-col gap-3 overflow-x-hidden px-3 pb-4 pt-3 sm:gap-4 sm:px-6';

export const pageHeaderClass =
    'flex min-w-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between';

export const pageHeaderMainClass = 'flex min-w-0 items-center gap-2 sm:gap-3';

export const pageIconClass =
    'flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 text-white shadow-sm';

export const pageTitleClass =
    'truncate text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-50';

export const pageSubtitleClass = 'mt-0.5 text-sm text-slate-600 dark:text-slate-400';

export const pageCardClass =
    'rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900';

export const pageStatClass =
    'flex min-w-0 flex-1 flex-col items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/5 px-3 py-3 sm:min-w-[110px] sm:flex-none sm:px-5 dark:border-cyan-500/40 dark:bg-cyan-500/10';

export const pageStatLabelClass =
    'text-[10px] font-semibold uppercase tracking-wider text-slate-400';

export const pageStatValueClass =
    'text-2xl font-bold leading-none tabular-nums text-cyan-500 sm:text-3xl';
