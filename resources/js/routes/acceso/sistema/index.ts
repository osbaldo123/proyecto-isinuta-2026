import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AccesoSistemaController::store
 * @see app/Http/Controllers/AccesoSistemaController.php:24
 * @route '/acceso'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/acceso',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AccesoSistemaController::store
 * @see app/Http/Controllers/AccesoSistemaController.php:24
 * @route '/acceso'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AccesoSistemaController::store
 * @see app/Http/Controllers/AccesoSistemaController.php:24
 * @route '/acceso'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AccesoSistemaController::cambiar
 * @see app/Http/Controllers/AccesoSistemaController.php:69
 * @route '/acceso/cambiar'
 */
export const cambiar = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: cambiar.url(options),
    method: 'get',
})

cambiar.definition = {
    methods: ["get","head"],
    url: '/acceso/cambiar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AccesoSistemaController::cambiar
 * @see app/Http/Controllers/AccesoSistemaController.php:69
 * @route '/acceso/cambiar'
 */
cambiar.url = (options?: RouteQueryOptions) => {
    return cambiar.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AccesoSistemaController::cambiar
 * @see app/Http/Controllers/AccesoSistemaController.php:69
 * @route '/acceso/cambiar'
 */
cambiar.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: cambiar.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AccesoSistemaController::cambiar
 * @see app/Http/Controllers/AccesoSistemaController.php:69
 * @route '/acceso/cambiar'
 */
cambiar.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: cambiar.url(options),
    method: 'head',
})
const sistema = {
    store: Object.assign(store, store),
cambiar: Object.assign(cambiar, cambiar),
}

export default sistema