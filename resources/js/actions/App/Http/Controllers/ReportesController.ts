import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ReportesController::index
 * @see app/Http/Controllers/ReportesController.php:46
 * @route '/reportes'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/reportes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportesController::index
 * @see app/Http/Controllers/ReportesController.php:46
 * @route '/reportes'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportesController::index
 * @see app/Http/Controllers/ReportesController.php:46
 * @route '/reportes'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportesController::index
 * @see app/Http/Controllers/ReportesController.php:46
 * @route '/reportes'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ReportesController::deudas
 * @see app/Http/Controllers/ReportesController.php:68
 * @route '/reportes/deudas'
 */
export const deudas = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: deudas.url(options),
    method: 'get',
})

deudas.definition = {
    methods: ["get","head"],
    url: '/reportes/deudas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportesController::deudas
 * @see app/Http/Controllers/ReportesController.php:68
 * @route '/reportes/deudas'
 */
deudas.url = (options?: RouteQueryOptions) => {
    return deudas.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportesController::deudas
 * @see app/Http/Controllers/ReportesController.php:68
 * @route '/reportes/deudas'
 */
deudas.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: deudas.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportesController::deudas
 * @see app/Http/Controllers/ReportesController.php:68
 * @route '/reportes/deudas'
 */
deudas.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: deudas.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ReportesController::ingresos
 * @see app/Http/Controllers/ReportesController.php:113
 * @route '/reportes/ingresos'
 */
export const ingresos = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ingresos.url(options),
    method: 'get',
})

ingresos.definition = {
    methods: ["get","head"],
    url: '/reportes/ingresos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportesController::ingresos
 * @see app/Http/Controllers/ReportesController.php:113
 * @route '/reportes/ingresos'
 */
ingresos.url = (options?: RouteQueryOptions) => {
    return ingresos.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportesController::ingresos
 * @see app/Http/Controllers/ReportesController.php:113
 * @route '/reportes/ingresos'
 */
ingresos.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ingresos.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportesController::ingresos
 * @see app/Http/Controllers/ReportesController.php:113
 * @route '/reportes/ingresos'
 */
ingresos.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ingresos.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ReportesController::exportIngresos
 * @see app/Http/Controllers/ReportesController.php:216
 * @route '/reportes/ingresos/export/{formato}'
 */
export const exportIngresos = (args: { formato: string | number } | [formato: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportIngresos.url(args, options),
    method: 'get',
})

exportIngresos.definition = {
    methods: ["get","head"],
    url: '/reportes/ingresos/export/{formato}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportesController::exportIngresos
 * @see app/Http/Controllers/ReportesController.php:216
 * @route '/reportes/ingresos/export/{formato}'
 */
exportIngresos.url = (args: { formato: string | number } | [formato: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { formato: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    formato: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        formato: args.formato,
                }

    return exportIngresos.definition.url
            .replace('{formato}', parsedArgs.formato.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportesController::exportIngresos
 * @see app/Http/Controllers/ReportesController.php:216
 * @route '/reportes/ingresos/export/{formato}'
 */
exportIngresos.get = (args: { formato: string | number } | [formato: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportIngresos.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportesController::exportIngresos
 * @see app/Http/Controllers/ReportesController.php:216
 * @route '/reportes/ingresos/export/{formato}'
 */
exportIngresos.head = (args: { formato: string | number } | [formato: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportIngresos.url(args, options),
    method: 'head',
})
const ReportesController = { index, deudas, ingresos, exportIngresos }

export default ReportesController