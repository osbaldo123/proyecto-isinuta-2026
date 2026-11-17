import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ReportesController::exportMethod
 * @see app/Http/Controllers/ReportesController.php:216
 * @route '/reportes/ingresos/export/{formato}'
 */
export const exportMethod = (args: { formato: string | number } | [formato: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/reportes/ingresos/export/{formato}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportesController::exportMethod
 * @see app/Http/Controllers/ReportesController.php:216
 * @route '/reportes/ingresos/export/{formato}'
 */
exportMethod.url = (args: { formato: string | number } | [formato: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return exportMethod.definition.url
            .replace('{formato}', parsedArgs.formato.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportesController::exportMethod
 * @see app/Http/Controllers/ReportesController.php:216
 * @route '/reportes/ingresos/export/{formato}'
 */
exportMethod.get = (args: { formato: string | number } | [formato: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportesController::exportMethod
 * @see app/Http/Controllers/ReportesController.php:216
 * @route '/reportes/ingresos/export/{formato}'
 */
exportMethod.head = (args: { formato: string | number } | [formato: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(args, options),
    method: 'head',
})
const ingresos = {
    export: Object.assign(exportMethod, exportMethod),
}

export default ingresos