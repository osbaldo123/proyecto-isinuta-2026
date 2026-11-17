import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\TrabajosController::index
 * @see app/Http/Controllers/TrabajosController.php:25
 * @route '/trabajos'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/trabajos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TrabajosController::index
 * @see app/Http/Controllers/TrabajosController.php:25
 * @route '/trabajos'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TrabajosController::index
 * @see app/Http/Controllers/TrabajosController.php:25
 * @route '/trabajos'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\TrabajosController::index
 * @see app/Http/Controllers/TrabajosController.php:25
 * @route '/trabajos'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TrabajosController::show
 * @see app/Http/Controllers/TrabajosController.php:71
 * @route '/trabajos/{id}'
 */
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/trabajos/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TrabajosController::show
 * @see app/Http/Controllers/TrabajosController.php:71
 * @route '/trabajos/{id}'
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
* @see \App\Http\Controllers\TrabajosController::show
 * @see app/Http/Controllers/TrabajosController.php:71
 * @route '/trabajos/{id}'
 */
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\TrabajosController::show
 * @see app/Http/Controllers/TrabajosController.php:71
 * @route '/trabajos/{id}'
 */
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TrabajosController::store
 * @see app/Http/Controllers/TrabajosController.php:56
 * @route '/trabajos'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/trabajos',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\TrabajosController::store
 * @see app/Http/Controllers/TrabajosController.php:56
 * @route '/trabajos'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TrabajosController::store
 * @see app/Http/Controllers/TrabajosController.php:56
 * @route '/trabajos'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TrabajosController::lista
 * @see app/Http/Controllers/TrabajosController.php:107
 * @route '/trabajos/{id}/lista'
 */
export const lista = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: lista.url(args, options),
    method: 'put',
})

lista.definition = {
    methods: ["put"],
    url: '/trabajos/{id}/lista',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\TrabajosController::lista
 * @see app/Http/Controllers/TrabajosController.php:107
 * @route '/trabajos/{id}/lista'
 */
lista.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return lista.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TrabajosController::lista
 * @see app/Http/Controllers/TrabajosController.php:107
 * @route '/trabajos/{id}/lista'
 */
lista.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: lista.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\TrabajosController::update
 * @see app/Http/Controllers/TrabajosController.php:86
 * @route '/trabajos/{id}'
 */
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/trabajos/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\TrabajosController::update
 * @see app/Http/Controllers/TrabajosController.php:86
 * @route '/trabajos/{id}'
 */
update.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TrabajosController::update
 * @see app/Http/Controllers/TrabajosController.php:86
 * @route '/trabajos/{id}'
 */
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\TrabajosController::destroy
 * @see app/Http/Controllers/TrabajosController.php:134
 * @route '/trabajos/{id}'
 */
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/trabajos/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\TrabajosController::destroy
 * @see app/Http/Controllers/TrabajosController.php:134
 * @route '/trabajos/{id}'
 */
destroy.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TrabajosController::destroy
 * @see app/Http/Controllers/TrabajosController.php:134
 * @route '/trabajos/{id}'
 */
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const trabajos = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
store: Object.assign(store, store),
lista: Object.assign(lista, lista),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default trabajos