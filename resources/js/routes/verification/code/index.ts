import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::send
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:35
 * @route '/email/verification-code'
 */
export const send = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(options),
    method: 'post',
})

send.definition = {
    methods: ["post"],
    url: '/email/verification-code',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::send
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:35
 * @route '/email/verification-code'
 */
send.url = (options?: RouteQueryOptions) => {
    return send.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\EmailVerificationCodeController::send
 * @see app/Http/Controllers/Auth/EmailVerificationCodeController.php:35
 * @route '/email/verification-code'
 */
send.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(options),
    method: 'post',
})
const code = {
    send: Object.assign(send, send),
}

export default code