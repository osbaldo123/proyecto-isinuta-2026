import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MultasController::index
 * @see app/Http/Controllers/MultasController.php:26
 * @route '/multas'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/multas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MultasController::index
 * @see app/Http/Controllers/MultasController.php:26
 * @route '/multas'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MultasController::index
 * @see app/Http/Controllers/MultasController.php:26
 * @route '/multas'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MultasController::index
 * @see app/Http/Controllers/MultasController.php:26
 * @route '/multas'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MultasController::show
 * @see app/Http/Controllers/MultasController.php:35
 * @route '/multas/{id}'
 */
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/multas/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MultasController::show
 * @see app/Http/Controllers/MultasController.php:35
 * @route '/multas/{id}'
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
* @see \App\Http\Controllers\MultasController::show
 * @see app/Http/Controllers/MultasController.php:35
 * @route '/multas/{id}'
 */
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MultasController::show
 * @see app/Http/Controllers/MultasController.php:35
 * @route '/multas/{id}'
 */
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MultasController::create
 * @see app/Http/Controllers/MultasController.php:111
 * @route '/multas/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/multas/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MultasController::create
 * @see app/Http/Controllers/MultasController.php:111
 * @route '/multas/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MultasController::create
 * @see app/Http/Controllers/MultasController.php:111
 * @route '/multas/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MultasController::create
 * @see app/Http/Controllers/MultasController.php:111
 * @route '/multas/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MultasController::store
 * @see app/Http/Controllers/MultasController.php:126
 * @route '/multas'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/multas',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MultasController::store
 * @see app/Http/Controllers/MultasController.php:126
 * @route '/multas'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MultasController::store
 * @see app/Http/Controllers/MultasController.php:126
 * @route '/multas'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MultasController::generarAutomaticas
 * @see app/Http/Controllers/MultasController.php:160
 * @route '/afiliados/{afiliadoId}/multas/auto'
 */
export const generarAutomaticas = (args: { afiliadoId: string | number } | [afiliadoId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generarAutomaticas.url(args, options),
    method: 'post',
})

generarAutomaticas.definition = {
    methods: ["post"],
    url: '/afiliados/{afiliadoId}/multas/auto',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MultasController::generarAutomaticas
 * @see app/Http/Controllers/MultasController.php:160
 * @route '/afiliados/{afiliadoId}/multas/auto'
 */
generarAutomaticas.url = (args: { afiliadoId: string | number } | [afiliadoId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { afiliadoId: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    afiliadoId: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        afiliadoId: args.afiliadoId,
                }

    return generarAutomaticas.definition.url
            .replace('{afiliadoId}', parsedArgs.afiliadoId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MultasController::generarAutomaticas
 * @see app/Http/Controllers/MultasController.php:160
 * @route '/afiliados/{afiliadoId}/multas/auto'
 */
generarAutomaticas.post = (args: { afiliadoId: string | number } | [afiliadoId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generarAutomaticas.url(args, options),
    method: 'post',
})
const multas = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
create: Object.assign(create, create),
store: Object.assign(store, store),
generarAutomaticas: Object.assign(generarAutomaticas, generarAutomaticas),
}

export default multas