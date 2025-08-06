<?php

namespace App\Mail\Traits;

use Illuminate\Mail\Mailables\Attachment;

trait HasLogo
{
    /**
     * Get the logo attachment for the email.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    protected function getLogoAttachment(): array
    {
        // For now, we'll embed the logo via URL in the template
        // rather than as an attachment due to Laravel 12 compatibility
        return [];
    }

    /**
     * Get the logo URL for email templates.
     *
     * @return string
     */
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
