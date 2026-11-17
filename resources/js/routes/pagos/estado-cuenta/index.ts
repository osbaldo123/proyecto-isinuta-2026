import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
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
const estadoCuenta = {
    export: Object.assign(exportMethod, exportMethod),
}

export default estadoCuenta