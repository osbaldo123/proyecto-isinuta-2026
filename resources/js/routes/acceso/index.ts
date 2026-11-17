import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import sistemaB998ab from './sistema'
/**
* @see \App\Http\Controllers\AccesoSistemaController::sistema
 * @see app/Http/Controllers/AccesoSistemaController.php:15
 * @route '/acceso'
 */
export const sistema = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sistema.url(options),
    method: 'get',
})

sistema.definition = {
    methods: ["get","head"],
    url: '/acceso',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AccesoSistemaController::sistema
 * @see app/Http/Controllers/AccesoSistemaController.php:15
 * @route '/acceso'
 */
sistema.url = (options?: RouteQueryOptions) => {
    return sistema.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AccesoSistemaController::sistema
 * @see app/Http/Controllers/AccesoSistemaController.php:15
 * @route '/acceso'
 */
sistema.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sistema.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AccesoSistemaController::sistema
 * @see app/Http/Controllers/AccesoSistemaController.php:15
 * @route '/acceso'
 */
sistema.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sistema.url(options),
    method: 'head',
})
const acceso = {
    sistema: Object.assign(sistema, sistemaB998ab),
}

export default acceso