<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AfiliadoVerificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public ?string $plainPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verificación de cuenta — Asociación Isinuta',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.afiliado-verificacion',
            with: [
                'nombre' => $this->user->name,
                'email' => $this->user->email,
                'code' => $this->code,
                'password' => $this->plainPassword,
                'ttlMinutes' => EmailVerificationCodeService::CODE_TTL_MINUTES,
                'loginUrl' => url('/login'),
            ],
        );
    }
}
