<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class UserPasswordResetCodeMail extends Mailable
{
    public function __construct(public string $code)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Код сброса пароля')
            ->text('emails.user-password-reset-code');
    }
}
