<?php

namespace App\Settings\Events;

class SettingsChanged
{
    /**
     * @param  array<int, string>  $keys
     */
    public function __construct(public readonly array $keys) {}
}
