import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CategoriasController::index
 * @see app/Http/Controllers/CategoriasController.php:15
 * @route '/categorias'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/categorias',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CategoriasController::index
 * @see app/Http/Controllers/CategoriasController.php:15
 * @route '/categorias'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CategoriasController::index
 * @see app/Http/Controllers/CategoriasController.php:15
 * @route '/categorias'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CategoriasController::index
 * @see app/Http/Controllers/CategoriasController.php:15
 * @route '/categorias'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CategoriasController::create
 * @see app/Http/Controllers/CategoriasController.php:61
 * @route '/categorias/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/categorias/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CategoriasController::create
 * @see app/Http/Controllers/CategoriasController.php:61
 * @route '/categorias/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CategoriasController::create
 * @see app/Http/Controllers/CategoriasController.php:61
 * @route '/categorias/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CategoriasController::create
 * @see app/Http/Controllers/CategoriasController.php:61
 * @route '/categorias/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CategoriasController::store
 * @see app/Http/Controllers/CategoriasController.php:66
 * @route '/categorias'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/categorias',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CategoriasController::store
 * @see app/Http/Controllers/CategoriasController.php:66
 * @route '/categorias'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CategoriasController::store
 * @see app/Http/Controllers/CategoriasController.php:66
 * @route '/categorias'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CategoriasController::edit
 * @see app/Http/Controllers/CategoriasController.php:83
 * @route '/categorias/{id}/edit'
 */
export const edit = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/categorias/{id}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CategoriasController::edit
 * @see app/Http/Controllers/CategoriasController.php:83
 * @route '/categorias/{id}/edit'
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
* @see \App\Http\Controllers\CategoriasController::edit
 * @see app/Http/Controllers/CategoriasController.php:83
 * @route '/categorias/{id}/edit'
 */
edit.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CategoriasController::edit
 * @see app/Http/Controllers/CategoriasController.php:83
 * @route '/categorias/{id}/edit'
 */
edit.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\CategoriasController::update
 * @see app/Http/Controllers/CategoriasController.php:100
 * @route '/categorias/{id}'
 */
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/categorias/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\CategoriasController::update
 * @see app/Http/Controllers/CategoriasController.php:100
 * @route '/categorias/{id}'
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
* @see \App\Http\Controllers\CategoriasController::update
 * @see app/Http/Controllers/CategoriasController.php:100
 * @route '/categorias/{id}'
 */
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\CategoriasController::toggleEstado
 * @see app/Http/Controllers/CategoriasController.php:114
 * @route '/categorias/{id}/estado'
 */
export const toggleEstado = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleEstado.url(args, options),
    method: 'patch',
})

toggleEstado.definition = {
    methods: ["patch"],
    url: '/categorias/{id}/estado',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\CategoriasController::toggleEstado
 * @see app/Http/Controllers/CategoriasController.php:114
 * @route '/categorias/{id}/estado'
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
* @see \App\Http\Controllers\CategoriasController::toggleEstado
 * @see app/Http/Controllers/CategoriasController.php:114
 * @route '/categorias/{id}/estado'
 */
toggleEstado.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleEstado.url(args, options),
    method: 'patch',
})
const CategoriasController = { index, create, store, edit, update, toggleEstado }

export default CategoriasController