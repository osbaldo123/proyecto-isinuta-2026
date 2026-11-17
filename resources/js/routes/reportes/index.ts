import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import ingresos0ee14c from './ingresos'
/**
* @see \App\Http\Controllers\ReportesController::index
 * @see app/Http/Controllers/ReportesController.php:46
 * @route '/reportes'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/reportes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportesController::index
 * @see app/Http/Controllers/ReportesController.php:46
 * @route '/reportes'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportesController::index
 * @see app/Http/Controllers/ReportesController.php:46
 * @route '/reportes'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportesController::index
 * @see app/Http/Controllers/ReportesController.php:46
 * @route '/reportes'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ReportesController::deudas
 * @see app/Http/Controllers/ReportesController.php:68
 * @route '/reportes/deudas'
 */
export const deudas = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: deudas.url(options),
    method: 'get',
})

deudas.definition = {
    methods: ["get","head"],
    url: '/reportes/deudas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportesController::deudas
 * @see app/Http/Controllers/ReportesController.php:68
 * @route '/reportes/deudas'
 */
deudas.url = (options?: RouteQueryOptions) => {
    return deudas.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportesController::deudas
 * @see app/Http/Controllers/ReportesController.php:68
 * @route '/reportes/deudas'
 */
deudas.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: deudas.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportesController::deudas
 * @see app/Http/Controllers/ReportesController.php:68
 * @route '/reportes/deudas'
 */
deudas.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: deudas.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ReportesController::ingresos
 * @see app/Http/Controllers/ReportesController.php:113
 * @route '/reportes/ingresos'
 */
export const ingresos = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ingresos.url(options),
    method: 'get',
})

ingresos.definition = {
    methods: ["get","head"],
    url: '/reportes/ingresos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportesController::ingresos
 * @see app/Http/Controllers/ReportesController.php:113
 * @route '/reportes/ingresos'
 */
ingresos.url = (options?: RouteQueryOptions) => {
    return ingresos.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportesController::ingresos
 * @see app/Http/Controllers/ReportesController.php:113
 * @route '/reportes/ingresos'
 */
ingresos.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ingresos.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportesController::ingresos
 * @see app/Http/Controllers/ReportesController.php:113
 * @route '/reportes/ingresos'
 */
ingresos.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ingresos.url(options),
    method: 'head',
})
const reportes = {
    index: Object.assign(index, index),
deudas: Object.assign(deudas, deudas),
ingresos: Object.assign(ingresos, ingresos0ee14c),
}

export default reportes