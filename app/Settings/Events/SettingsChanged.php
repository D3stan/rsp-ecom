<?php

namespace App\Settings\Events;

/**
 * Dispatched after settings were written (or reset) and the cache version was bumped.
 */
class SettingsChanged
{
    /**
     * @param  array<int, string>  $keys
     */
    public function __construct(public readonly array $keys) {}
}
