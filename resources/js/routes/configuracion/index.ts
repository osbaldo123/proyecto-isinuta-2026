import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\ConfiguracionController::index
 * @see app/Http/Controllers/ConfiguracionController.php:49
 * @route '/configuracion'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/configuracion',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ConfiguracionController::index
 * @see app/Http/Controllers/ConfiguracionController.php:49
 * @route '/configuracion'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConfiguracionController::index
 * @see app/Http/Controllers/ConfiguracionController.php:49
 * @route '/configuracion'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ConfiguracionController::index
 * @see app/Http/Controllers/ConfiguracionController.php:49
 * @route '/configuracion'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ConfiguracionController::update
 * @see app/Http/Controllers/ConfiguracionController.php:56
 * @route '/configuracion'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/configuracion',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ConfiguracionController::update
 * @see app/Http/Controllers/ConfiguracionController.php:56
 * @route '/configuracion'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConfiguracionController::update
 * @see app/Http/Controllers/ConfiguracionController.php:56
 * @route '/configuracion'
 */
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})
const configuracion = {
    index: Object.assign(index, index),
update: Object.assign(update, update),
}

export default configuracion