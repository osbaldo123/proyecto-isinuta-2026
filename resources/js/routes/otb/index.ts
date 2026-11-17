import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\OtbsController::index
 * @see app/Http/Controllers/OtbsController.php:15
 * @route '/otb'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/otb',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OtbsController::index
 * @see app/Http/Controllers/OtbsController.php:15
 * @route '/otb'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OtbsController::index
 * @see app/Http/Controllers/OtbsController.php:15
 * @route '/otb'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OtbsController::index
 * @see app/Http/Controllers/OtbsController.php:15
 * @route '/otb'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OtbsController::create
 * @see app/Http/Controllers/OtbsController.php:56
 * @route '/otb/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/otb/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OtbsController::create
 * @see app/Http/Controllers/OtbsController.php:56
 * @route '/otb/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OtbsController::create
 * @see app/Http/Controllers/OtbsController.php:56
 * @route '/otb/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OtbsController::create
 * @see app/Http/Controllers/OtbsController.php:56
 * @route '/otb/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OtbsController::store
 * @see app/Http/Controllers/OtbsController.php:61
 * @route '/otb'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/otb',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OtbsController::store
 * @see app/Http/Controllers/OtbsController.php:61
 * @route '/otb'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OtbsController::store
 * @see app/Http/Controllers/OtbsController.php:61
 * @route '/otb'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OtbsController::edit
 * @see app/Http/Controllers/OtbsController.php:75
 * @route '/otb/{id}/edit'
 */
export const edit = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/otb/{id}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OtbsController::edit
 * @see app/Http/Controllers/OtbsController.php:75
 * @route '/otb/{id}/edit'
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
* @see \App\Http\Controllers\OtbsController::edit
 * @see app/Http/Controllers/OtbsController.php:75
 * @route '/otb/{id}/edit'
 */
edit.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OtbsController::edit
 * @see app/Http/Controllers/OtbsController.php:75
 * @route '/otb/{id}/edit'
 */
edit.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OtbsController::update
 * @see app/Http/Controllers/OtbsController.php:95
 * @route '/otb/{id}'
 */
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/otb/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OtbsController::update
 * @see app/Http/Controllers/OtbsController.php:95
 * @route '/otb/{id}'
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
* @see \App\Http\Controllers\OtbsController::update
 * @see app/Http/Controllers/OtbsController.php:95
 * @route '/otb/{id}'
 */
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OtbsController::toggleEstado
 * @see app/Http/Controllers/OtbsController.php:109
 * @route '/otb/{id}/estado'
 */
export const toggleEstado = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleEstado.url(args, options),
    method: 'patch',
})

toggleEstado.definition = {
    methods: ["patch"],
    url: '/otb/{id}/estado',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\OtbsController::toggleEstado
 * @see app/Http/Controllers/OtbsController.php:109
 * @route '/otb/{id}/estado'
 */
toggleEstado.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return toggleEstado.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OtbsController::toggleEstado
 * @see app/Http/Controllers/OtbsController.php:109
 * @route '/otb/{id}/estado'
 */
toggleEstado.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleEstado.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\OtbsController::destroy
 * @see app/Http/Controllers/OtbsController.php:121
 * @route '/otb/{id}'
 */
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/otb/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OtbsController::destroy
 * @see app/Http/Controllers/OtbsController.php:121
 * @route '/otb/{id}'
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
* @see \App\Http\Controllers\OtbsController::destroy
 * @see app/Http/Controllers/OtbsController.php:121
 * @route '/otb/{id}'
 */
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const otb = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
toggleEstado: Object.assign(toggleEstado, toggleEstado),
destroy: Object.assign(destroy, destroy),
}

export default otb