<?php

namespace App\Mail\Traits;

use Illuminate\Mail\Mailables\Attachment;

trait HasLogo
{
    /**
     * Get the logo attachment for the email.
     *
     * @return array<int, Attachment>
     */
    protected function getLogoAttachment(): array
    {
        return [];
    }

    protected function getLogoUrl(): string
    {
        if (file_exists(public_path('images/rlogo.png'))) {
            return asset('images/rlogo.png');
        }

        if (file_exists(public_path('favicon.ico'))) {
            return asset('favicon.ico');
        }

        return asset('logo.svg');
    }
}
