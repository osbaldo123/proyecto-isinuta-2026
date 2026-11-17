import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DashboardController::__invoke
 * @see app/Http/Controllers/DashboardController.php:28
 * @route '/dashboard'
 */
const DashboardController42a740574ecbfbac32f8cc353fc32db9 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardController42a740574ecbfbac32f8cc353fc32db9.url(options),
    method: 'get',
})

DashboardController42a740574ecbfbac32f8cc353fc32db9.definition = {
    methods: ["get","head"],
    url: '/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::__invoke
 * @see app/Http/Controllers/DashboardController.php:28
 * @route '/dashboard'
 */
DashboardController42a740574ecbfbac32f8cc353fc32db9.url = (options?: RouteQueryOptions) => {
    return DashboardController42a740574ecbfbac32f8cc353fc32db9.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::__invoke
 * @see app/Http/Controllers/DashboardController.php:28
 * @route '/dashboard'
 */
DashboardController42a740574ecbfbac32f8cc353fc32db9.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardController42a740574ecbfbac32f8cc353fc32db9.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\DashboardController::__invoke
 * @see app/Http/Controllers/DashboardController.php:28
 * @route '/dashboard'
 */
DashboardController42a740574ecbfbac32f8cc353fc32db9.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: DashboardController42a740574ecbfbac32f8cc353fc32db9.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\DashboardController::__invoke
 * @see app/Http/Controllers/DashboardController.php:28
 * @route '/panel'
 */
const DashboardControllerfc281efe9b3109603fafd82c703ad3f9 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardControllerfc281efe9b3109603fafd82c703ad3f9.url(options),
    method: 'get',
})

DashboardControllerfc281efe9b3109603fafd82c703ad3f9.definition = {
    methods: ["get","head"],
    url: '/panel',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::__invoke
 * @see app/Http/Controllers/DashboardController.php:28
 * @route '/panel'
 */
DashboardControllerfc281efe9b3109603fafd82c703ad3f9.url = (options?: RouteQueryOptions) => {
    return DashboardControllerfc281efe9b3109603fafd82c703ad3f9.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::__invoke
 * @see app/Http/Controllers/DashboardController.php:28
 * @route '/panel'
 */
DashboardControllerfc281efe9b3109603fafd82c703ad3f9.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardControllerfc281efe9b3109603fafd82c703ad3f9.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\DashboardController::__invoke
 * @see app/Http/Controllers/DashboardController.php:28
 * @route '/panel'
 */
DashboardControllerfc281efe9b3109603fafd82c703ad3f9.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: DashboardControllerfc281efe9b3109603fafd82c703ad3f9.url(options),
    method: 'head',
})

/**
* Multiple routes resolve to \App\Http\Controllers\DashboardController::DashboardController, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `DashboardController['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
const DashboardController = {
    '/dashboard': DashboardController42a740574ecbfbac32f8cc353fc32db9,
    '/panel': DashboardControllerfc281efe9b3109603fafd82c703ad3f9,
}

export default DashboardController