import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PagosController::imprimir
 * @see app/Http/Controllers/PagosController.php:276
 * @route '/pagos/cobro/{cobro}/imprimir'
 */
export const imprimir = (args: { cobro: string | number } | [cobro: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: imprimir.url(args, options),
    method: 'get',
})

imprimir.definition = {
    methods: ["get","head"],
    url: '/pagos/cobro/{cobro}/imprimir',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PagosController::imprimir
 * @see app/Http/Controllers/PagosController.php:276
 * @route '/pagos/cobro/{cobro}/imprimir'
 */
imprimir.url = (args: { cobro: string | number } | [cobro: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { cobro: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    cobro: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        cobro: args.cobro,
                }

    return imprimir.definition.url
            .replace('{cobro}', parsedArgs.cobro.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PagosController::imprimir
 * @see app/Http/Controllers/PagosController.php:276
 * @route '/pagos/cobro/{cobro}/imprimir'
 */
imprimir.get = (args: { cobro: string | number } | [cobro: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: imprimir.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PagosController::imprimir
 * @see app/Http/Controllers/PagosController.php:276
 * @route '/pagos/cobro/{cobro}/imprimir'
 */
imprimir.head = (args: { cobro: string | number } | [cobro: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: imprimir.url(args, options),
    method: 'head',
})
const cobro = {
    imprimir: Object.assign(imprimir, imprimir),
}

export default cobro