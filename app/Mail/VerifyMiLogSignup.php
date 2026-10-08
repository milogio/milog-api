<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class VerifyMiLogSignup extends Mailable
{
    public $verificationUrl;

    public function __construct($verificationUrl)
    {
        $this->verificationUrl = $verificationUrl;
    }

    public function build()
    {
        return $this->subject('Verify your MiLog account')
            ->text('emails.verify-signup');
    }
}
