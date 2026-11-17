import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\TiposAfiliacionController::index
 * @see app/Http/Controllers/TiposAfiliacionController.php:14
 * @route '/afiliaciones'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/afiliaciones',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TiposAfiliacionController::index
 * @see app/Http/Controllers/TiposAfiliacionController.php:14
 * @route '/afiliaciones'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TiposAfiliacionController::index
 * @see app/Http/Controllers/TiposAfiliacionController.php:14
 * @route '/afiliaciones'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\TiposAfiliacionController::index
 * @see app/Http/Controllers/TiposAfiliacionController.php:14
 * @route '/afiliaciones'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TiposAfiliacionController::create
 * @see app/Http/Controllers/TiposAfiliacionController.php:58
 * @route '/afiliaciones/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/afiliaciones/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TiposAfiliacionController::create
 * @see app/Http/Controllers/TiposAfiliacionController.php:58
 * @route '/afiliaciones/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TiposAfiliacionController::create
 * @see app/Http/Controllers/TiposAfiliacionController.php:58
 * @route '/afiliaciones/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\TiposAfiliacionController::create
 * @see app/Http/Controllers/TiposAfiliacionController.php:58
 * @route '/afiliaciones/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TiposAfiliacionController::store
 * @see app/Http/Controllers/TiposAfiliacionController.php:63
 * @route '/afiliaciones'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/afiliaciones',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\TiposAfiliacionController::store
 * @see app/Http/Controllers/TiposAfiliacionController.php:63
 * @route '/afiliaciones'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TiposAfiliacionController::store
 * @see app/Http/Controllers/TiposAfiliacionController.php:63
 * @route '/afiliaciones'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TiposAfiliacionController::edit
 * @see app/Http/Controllers/TiposAfiliacionController.php:82
 * @route '/afiliaciones/{id}/edit'
 */
export const edit = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/afiliaciones/{id}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TiposAfiliacionController::edit
 * @see app/Http/Controllers/TiposAfiliacionController.php:82
 * @route '/afiliaciones/{id}/edit'
 */
edit.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TiposAfiliacionController::edit
 * @see app/Http/Controllers/TiposAfiliacionController.php:82
 * @route '/afiliaciones/{id}/edit'
 */
edit.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\TiposAfiliacionController::edit
 * @see app/Http/Controllers/TiposAfiliacionController.php:82
 * @route '/afiliaciones/{id}/edit'
 */
edit.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\TiposAfiliacionController::update
 * @see app/Http/Controllers/TiposAfiliacionController.php:108
 * @route '/afiliaciones/{id}'
 */
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/afiliaciones/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\TiposAfiliacionController::update
 * @see app/Http/Controllers/TiposAfiliacionController.php:108
 * @route '/afiliaciones/{id}'
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
* @see \App\Http\Controllers\TiposAfiliacionController::update
 * @see app/Http/Controllers/TiposAfiliacionController.php:108
 * @route '/afiliaciones/{id}'
 */
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\TiposAfiliacionController::destroy
 * @see app/Http/Controllers/TiposAfiliacionController.php:127
 * @route '/afiliaciones/{id}'
 */
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/afiliaciones/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\TiposAfiliacionController::destroy
 * @see app/Http/Controllers/TiposAfiliacionController.php:127
 * @route '/afiliaciones/{id}'
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
* @see \App\Http\Controllers\TiposAfiliacionController::destroy
 * @see app/Http/Controllers/TiposAfiliacionController.php:127
 * @route '/afiliaciones/{id}'
 */
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const afiliaciones = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default afiliaciones