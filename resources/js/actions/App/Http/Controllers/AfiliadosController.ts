import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AfiliadosController::index
 * @see app/Http/Controllers/AfiliadosController.php:36
 * @route '/afiliados'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/afiliados',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AfiliadosController::index
 * @see app/Http/Controllers/AfiliadosController.php:36
 * @route '/afiliados'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AfiliadosController::index
 * @see app/Http/Controllers/AfiliadosController.php:36
 * @route '/afiliados'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AfiliadosController::index
 * @see app/Http/Controllers/AfiliadosController.php:36
 * @route '/afiliados'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AfiliadosController::buscar
 * @see app/Http/Controllers/AfiliadosController.php:145
 * @route '/afiliados/buscar'
 */
export const buscar = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: buscar.url(options),
    method: 'get',
})

buscar.definition = {
    methods: ["get","head"],
    url: '/afiliados/buscar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AfiliadosController::buscar
 * @see app/Http/Controllers/AfiliadosController.php:145
 * @route '/afiliados/buscar'
 */
buscar.url = (options?: RouteQueryOptions) => {
    return buscar.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AfiliadosController::buscar
 * @see app/Http/Controllers/AfiliadosController.php:145
 * @route '/afiliados/buscar'
 */
buscar.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: buscar.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AfiliadosController::buscar
 * @see app/Http/Controllers/AfiliadosController.php:145
 * @route '/afiliados/buscar'
 */
buscar.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: buscar.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AfiliadosController::siguienteLote
 * @see app/Http/Controllers/AfiliadosController.php:193
 * @route '/afiliados/siguiente-lote'
 */
export const siguienteLote = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: siguienteLote.url(options),
    method: 'get',
})

siguienteLote.definition = {
    methods: ["get","head"],
    url: '/afiliados/siguiente-lote',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AfiliadosController::siguienteLote
 * @see app/Http/Controllers/AfiliadosController.php:193
 * @route '/afiliados/siguiente-lote'
 */
siguienteLote.url = (options?: RouteQueryOptions) => {
    return siguienteLote.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AfiliadosController::siguienteLote
 * @see app/Http/Controllers/AfiliadosController.php:193
 * @route '/afiliados/siguiente-lote'
 */
siguienteLote.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: siguienteLote.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AfiliadosController::siguienteLote
 * @see app/Http/Controllers/AfiliadosController.php:193
 * @route '/afiliados/siguiente-lote'
 */
siguienteLote.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: siguienteLote.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AfiliadosController::create
 * @see app/Http/Controllers/AfiliadosController.php:228
 * @route '/afiliados/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/afiliados/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AfiliadosController::create
 * @see app/Http/Controllers/AfiliadosController.php:228
 * @route '/afiliados/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AfiliadosController::create
 * @see app/Http/Controllers/AfiliadosController.php:228
 * @route '/afiliados/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AfiliadosController::create
 * @see app/Http/Controllers/AfiliadosController.php:228
 * @route '/afiliados/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AfiliadosController::store
 * @see app/Http/Controllers/AfiliadosController.php:261
 * @route '/afiliados'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/afiliados',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AfiliadosController::store
 * @see app/Http/Controllers/AfiliadosController.php:261
 * @route '/afiliados'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AfiliadosController::store
 * @see app/Http/Controllers/AfiliadosController.php:261
 * @route '/afiliados'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AfiliadosController::edit
 * @see app/Http/Controllers/AfiliadosController.php:457
 * @route '/afiliados/{id}/edit'
 */
export const edit = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/afiliados/{id}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AfiliadosController::edit
 * @see app/Http/Controllers/AfiliadosController.php:457
 * @route '/afiliados/{id}/edit'
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
* @see \App\Http\Controllers\AfiliadosController::edit
 * @see app/Http/Controllers/AfiliadosController.php:457
 * @route '/afiliados/{id}/edit'
 */
edit.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AfiliadosController::edit
 * @see app/Http/Controllers/AfiliadosController.php:457
 * @route '/afiliados/{id}/edit'
 */
edit.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AfiliadosController::update
 * @see app/Http/Controllers/AfiliadosController.php:492
 * @route '/afiliados/{id}'
 */
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/afiliados/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\AfiliadosController::update
 * @see app/Http/Controllers/AfiliadosController.php:492
 * @route '/afiliados/{id}'
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
* @see \App\Http\Controllers\AfiliadosController::update
 * @see app/Http/Controllers/AfiliadosController.php:492
 * @route '/afiliados/{id}'
 */
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\AfiliadosController::changeStatus
 * @see app/Http/Controllers/AfiliadosController.php:611
 * @route '/afiliados/{id}/estado'
 */
export const changeStatus = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: changeStatus.url(args, options),
    method: 'patch',
})

changeStatus.definition = {
    methods: ["patch"],
    url: '/afiliados/{id}/estado',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\AfiliadosController::changeStatus
 * @see app/Http/Controllers/AfiliadosController.php:611
 * @route '/afiliados/{id}/estado'
 */
changeStatus.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return changeStatus.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AfiliadosController::changeStatus
 * @see app/Http/Controllers/AfiliadosController.php:611
 * @route '/afiliados/{id}/estado'
 */
changeStatus.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: changeStatus.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\AfiliadosController::destroy
 * @see app/Http/Controllers/AfiliadosController.php:567
 * @route '/afiliados/{id}'
 */
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/afiliados/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AfiliadosController::destroy
 * @see app/Http/Controllers/AfiliadosController.php:567
 * @route '/afiliados/{id}'
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
* @see \App\Http\Controllers\AfiliadosController::destroy
 * @see app/Http/Controllers/AfiliadosController.php:567
 * @route '/afiliados/{id}'
 */
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\AfiliadosController::show
 * @see app/Http/Controllers/AfiliadosController.php:379
 * @route '/afiliados/{id}'
 */
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/afiliados/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AfiliadosController::show
 * @see app/Http/Controllers/AfiliadosController.php:379
 * @route '/afiliados/{id}'
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
* @see \App\Http\Controllers\AfiliadosController::show
 * @see app/Http/Controllers/AfiliadosController.php:379
 * @route '/afiliados/{id}'
 */
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AfiliadosController::show
 * @see app/Http/Controllers/AfiliadosController.php:379
 * @route '/afiliados/{id}'
 */
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})
const AfiliadosController = { index, buscar, siguienteLote, create, store, edit, update, changeStatus, destroy, show }

export default AfiliadosController