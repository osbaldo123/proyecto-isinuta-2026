import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PagosController::index
 * @see app/Http/Controllers/PagosController.php:50
 * @route '/pagos'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/pagos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PagosController::index
 * @see app/Http/Controllers/PagosController.php:50
 * @route '/pagos'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PagosController::index
 * @see app/Http/Controllers/PagosController.php:50
 * @route '/pagos'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PagosController::index
 * @see app/Http/Controllers/PagosController.php:50
 * @route '/pagos'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PagosController::show
 * @see app/Http/Controllers/PagosController.php:248
 * @route '/pagos/{id}'
 */
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/pagos/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PagosController::show
 * @see app/Http/Controllers/PagosController.php:248
 * @route '/pagos/{id}'
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
* @see \App\Http\Controllers\PagosController::show
 * @see app/Http/Controllers/PagosController.php:248
 * @route '/pagos/{id}'
 */
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PagosController::show
 * @see app/Http/Controllers/PagosController.php:248
 * @route '/pagos/{id}'
 */
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PagosController::exportMethod
 * @see app/Http/Controllers/PagosController.php:370
 * @route '/pagos/{id}/export/{formato}'
 */
export const exportMethod = (args: { id: string | number, formato: string | number } | [id: string | number, formato: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/pagos/{id}/export/{formato}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PagosController::exportMethod
 * @see app/Http/Controllers/PagosController.php:370
 * @route '/pagos/{id}/export/{formato}'
 */
exportMethod.url = (args: { id: string | number, formato: string | number } | [id: string | number, formato: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                    formato: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                                formato: args.formato,
                }

    return exportMethod.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace('{formato}', parsedArgs.formato.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PagosController::exportMethod
 * @see app/Http/Controllers/PagosController.php:370
 * @route '/pagos/{id}/export/{formato}'
 */
exportMethod.get = (args: { id: string | number, formato: string | number } | [id: string | number, formato: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PagosController::exportMethod
 * @see app/Http/Controllers/PagosController.php:370
 * @route '/pagos/{id}/export/{formato}'
 */
exportMethod.head = (args: { id: string | number, formato: string | number } | [id: string | number, formato: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PagosController::imprimirCobro
 * @see app/Http/Controllers/PagosController.php:276
 * @route '/pagos/cobro/{cobro}/imprimir'
 */
export const imprimirCobro = (args: { cobro: string | number } | [cobro: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: imprimirCobro.url(args, options),
    method: 'get',
})

imprimirCobro.definition = {
    methods: ["get","head"],
    url: '/pagos/cobro/{cobro}/imprimir',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PagosController::imprimirCobro
 * @see app/Http/Controllers/PagosController.php:276
 * @route '/pagos/cobro/{cobro}/imprimir'
 */
imprimirCobro.url = (args: { cobro: string | number } | [cobro: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return imprimirCobro.definition.url
            .replace('{cobro}', parsedArgs.cobro.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PagosController::imprimirCobro
 * @see app/Http/Controllers/PagosController.php:276
 * @route '/pagos/cobro/{cobro}/imprimir'
 */
imprimirCobro.get = (args: { cobro: string | number } | [cobro: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: imprimirCobro.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PagosController::imprimirCobro
 * @see app/Http/Controllers/PagosController.php:276
 * @route '/pagos/cobro/{cobro}/imprimir'
 */
imprimirCobro.head = (args: { cobro: string | number } | [cobro: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: imprimirCobro.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PagosController::create
 * @see app/Http/Controllers/PagosController.php:59
 * @route '/pagos/registrar/nuevo'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/pagos/registrar/nuevo',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PagosController::create
 * @see app/Http/Controllers/PagosController.php:59
 * @route '/pagos/registrar/nuevo'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PagosController::create
 * @see app/Http/Controllers/PagosController.php:59
 * @route '/pagos/registrar/nuevo'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PagosController::create
 * @see app/Http/Controllers/PagosController.php:59
 * @route '/pagos/registrar/nuevo'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PagosController::store
 * @see app/Http/Controllers/PagosController.php:216
 * @route '/pagos'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/pagos',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PagosController::store
 * @see app/Http/Controllers/PagosController.php:216
 * @route '/pagos'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PagosController::store
 * @see app/Http/Controllers/PagosController.php:216
 * @route '/pagos'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PagosController::anular
 * @see app/Http/Controllers/PagosController.php:386
 * @route '/pagos/{id}/anular'
 */
export const anular = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: anular.url(args, options),
    method: 'post',
})

anular.definition = {
    methods: ["post"],
    url: '/pagos/{id}/anular',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PagosController::anular
 * @see app/Http/Controllers/PagosController.php:386
 * @route '/pagos/{id}/anular'
 */
anular.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return anular.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PagosController::anular
 * @see app/Http/Controllers/PagosController.php:386
 * @route '/pagos/{id}/anular'
 */
anular.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: anular.url(args, options),
    method: 'post',
})
const PagosController = { index, show, exportMethod, imprimirCobro, create, store, anular, export: exportMethod }

export default PagosController