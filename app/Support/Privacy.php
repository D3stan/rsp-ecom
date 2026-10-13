<?php

namespace App\Support;

class Privacy
{
    /**
     * Mask an email address for log output: "mario.rossi@example.com" => "m***@example.com".
     * Keeps enough to correlate a customer complaint with a log line, not enough to harvest addresses.
     */
    public static function maskEmail(?string $email): string
    {
        if ($email === null || $email === '') {
            return '';
        }

        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }
}
