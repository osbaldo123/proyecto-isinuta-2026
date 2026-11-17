import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\EstadoCuentaController::show
 * @see app/Http/Controllers/EstadoCuentaController.php:28
 * @route '/pagos/estado-cuenta/{id}'
 */
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/pagos/estado-cuenta/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EstadoCuentaController::show
 * @see app/Http/Controllers/EstadoCuentaController.php:28
 * @route '/pagos/estado-cuenta/{id}'
 */
show.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return show.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EstadoCuentaController::show
 * @see app/Http/Controllers/EstadoCuentaController.php:28
 * @route '/pagos/estado-cuenta/{id}'
 */
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\EstadoCuentaController::show
 * @see app/Http/Controllers/EstadoCuentaController.php:28
 * @route '/pagos/estado-cuenta/{id}'
 */
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\EstadoCuentaController::exportMethod
 * @see app/Http/Controllers/EstadoCuentaController.php:41
 * @route '/pagos/estado-cuenta/{id}/export/{formato}'
 */
export const exportMethod = (args: { id: string | number, formato: string | number } | [id: string | number, formato: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/pagos/estado-cuenta/{id}/export/{formato}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EstadoCuentaController::exportMethod
 * @see app/Http/Controllers/EstadoCuentaController.php:41
 * @route '/pagos/estado-cuenta/{id}/export/{formato}'
 */
exportMethod.url = (args: { id: string | number, formato: string | number } | [id: string | number, formato: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                    formato: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                                formato: args.formato,
                }

    return exportMethod.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace('{formato}', parsedArgs.formato.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EstadoCuentaController::exportMethod
 * @see app/Http/Controllers/EstadoCuentaController.php:41
 * @route '/pagos/estado-cuenta/{id}/export/{formato}'
 */
exportMethod.get = (args: { id: string | number, formato: string | number } | [id: string | number, formato: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\EstadoCuentaController::exportMethod
 * @see app/Http/Controllers/EstadoCuentaController.php:41
 * @route '/pagos/estado-cuenta/{id}/export/{formato}'
 */
exportMethod.head = (args: { id: string | number, formato: string | number } | [id: string | number, formato: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(args, options),
    method: 'head',
})
const EstadoCuentaController = { show, exportMethod, export: exportMethod }

export default EstadoCuentaController