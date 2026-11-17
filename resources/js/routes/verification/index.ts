import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import code414516 from './code'
/**
* @see \Laravel\Fortify\Http\Controllers\EmailVerificationPromptController::notice
 * @see vendor/laravel/fortify/src/Http/Controllers/EmailVerificationPromptController.php:18
 * @route '/email/verify'
 */
export const notice = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: notice.url(options),
    method: 'get',
})

notice.definition = {
    methods: ["get","head"],
    url: '/email/verify',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Laravel\Fortify\Http\Controllers\EmailVerificationPromptController::notice
 * @see vendor/laravel/fortify/src/Http/Controllers/EmailVerificationPromptController.php:18
 * @route '/email/verify'
 */
notice.url = (options?: RouteQueryOptions) => {
    return notice.definition.url + queryParams(options)
}

/**
* @see \Laravel\Fortify\Http\Controllers\EmailVerificationPromptController::notice
 * @see vendor/laravel/fortify/src/Http/Controllers/EmailVerificationPromptController.php:18
 * @route '/email/verify'
 */
notice.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: notice.url(options),
    method: 'get',
})
/**
* @see \Laravel\Fortify\Http\Controllers\EmailVerificationPromptController::notice
 * @see vendor/laravel/fortify/src/Http/Controllers/EmailVerificationPromptController.php:18
 * @route '/email/verify'
 */
notice.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: notice.url(options),
    method: 'head',
})

/**
* @see \Laravel\Fortify\Http\Controllers\VerifyEmailController::verify
 * @see vendor/laravel/fortify/src/Http/Controllers/VerifyEmailController.php:18
 * @route '/email/verify/{id}/{hash}'
 */
export const verify = (args: { id: string | number, hash: string | number } | [id: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/email/verify/{id}/{hash}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Laravel\Fortify\Http\Controllers\VerifyEmailController::verify
 * @see vendor/laravel/fortify/src/Http/Controllers/VerifyEmailController.php:18
 * @route '/email/verify/{id}/{hash}'
 */
verify.url = (args: { id: string | number, hash: string | number } | [id: string | number, hash: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                    hash: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                                hash: args.hash,
                }

    return verify.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace('{hash}', parsedArgs.hash.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Laravel\Fortify\Http\Controllers\VerifyEmailController::verify
 * @see vendor/laravel/fortify/src/Http/Controllers/VerifyEmailController.php:18
 * @route '/email/verify/{id}/{hash}'
 */
verify.get = (args: { id: string | number, hash: string | number } | [id: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(args, options),
    method: 'get',
})
/**
* @see \Laravel\Fortify\Http\Controllers\VerifyEmailController::verify
 * @see vendor/laravel/fortify/src/Http/Controllers/VerifyEmailController.php:18
 * @route '/email/verify/{id}/{hash}'
 */
verify.head = (args: { id: string | number, hash: string | number } | [id: string | number, hash: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(args, options),
    method: 'head',
})

/**
* @see \Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController::send
 * @see vendor/laravel/fortify/src/Http/Controllers/EmailVerificationNotificationController.php:19
 * @route '/email/verification-notification'
 */
export const send = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(options),
    method: 'post',
})

send.definition = {
    methods: ["post"],
    url: '/email/verification-notification',
} satisfies RouteDefinition<["post"]>

/**
* @see \Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController::send
 * @see vendor/laravel/fortify/src/Http/Controllers/EmailVerificationNotificationController.php:19
 * @route '/email/verification-notification'
 */
send.url = (options?: RouteQueryOptions) => {
    return send.definition.url + queryParams(options)
}

/**
* @see \Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController::send
 * @see vendor/laravel/fortify/src/Http/Controllers/EmailVerificationNotificationController.php:19
 * @route '/email/verification-notification'
 */
send.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::code
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:16
 * @route '/email/verify-code'
 */
export const code = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: code.url(options),
    method: 'post',
})

code.definition = {
    methods: ["post"],
    url: '/email/verify-code',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::code
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:16
 * @route '/email/verify-code'
 */
code.url = (options?: RouteQueryOptions) => {
    return code.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::code
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:16
 * @route '/email/verify-code'
 */
code.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: code.url(options),
    method: 'post',
})
const verification = {
    notice: Object.assign(notice, notice),
verify: Object.assign(verify, verify),
send: Object.assign(send, send),
code: Object.assign(code, code414516),
}

export default verification