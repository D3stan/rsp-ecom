<?php

namespace App\Enums;

/**
 * The framework account notifications that SendUserNotification may deliver (a closed list,
 * so the queued payload can never name an arbitrary notification class).
 */
enum UserNotificationKind: string
{
    case PasswordReset = 'password_reset';
    case VerifyEmail = 'verify_email';
}
