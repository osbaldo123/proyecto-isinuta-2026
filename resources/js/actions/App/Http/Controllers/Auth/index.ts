import EmailVerificationPromptController from './EmailVerificationPromptController'
import EmailVerificationCodeController from './EmailVerificationCodeController'
import ConfirmablePasswordController from './ConfirmablePasswordController'
const Auth = {
    EmailVerificationPromptController: Object.assign(EmailVerificationPromptController, EmailVerificationPromptController),
EmailVerificationCodeController: Object.assign(EmailVerificationCodeController, EmailVerificationCodeController),
ConfirmablePasswordController: Object.assign(ConfirmablePasswordController, ConfirmablePasswordController),
}

export default Auth