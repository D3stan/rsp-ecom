<?php

namespace App\Shipping\Tracking\Contracts;

use App\Shipping\Contracts\Authenticator as BaseAuthenticator;

/**
 * How an HTTP adapter proves its identity to the carrier API (bearer token, API-key header, OAuth
 * client credentials, ...). Extends App\Shipping\Contracts\Authenticator so shared authenticators
 * work across both tracking and label subsystems.
 *
 * Credentials must never be logged and never appear in exception messages.
 */
interface Authenticator extends BaseAuthenticator {}
