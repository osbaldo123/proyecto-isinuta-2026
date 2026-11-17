import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::store
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:16
 * @route '/email/verify-code'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/email/verify-code',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::store
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:16
 * @route '/email/verify-code'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::store
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:16
 * @route '/email/verify-code'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::resend
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:35
 * @route '/email/verification-code'
 */
export const resend = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '/email/verification-code',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::resend
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:35
 * @route '/email/verification-code'
 */
resend.url = (options?: RouteQueryOptions) => {
    return resend.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::resend
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:35
 * @route '/email/verification-code'
 */
resend.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(options),
    method: 'post',
})
const EmailVerificationCodeController = { store, resend }

export default EmailVerificationCodeController