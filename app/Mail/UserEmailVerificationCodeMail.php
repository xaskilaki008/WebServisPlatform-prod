<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class UserEmailVerificationCodeMail extends Mailable
{
    public function __construct(public string $code)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Код подтверждения регистрации')
            ->text('emails.user-email-verification-code');
    }
}
