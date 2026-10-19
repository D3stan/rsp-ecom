<?php

namespace App\Shipping\Tracking\Contracts;

use App\Shipping\Contracts\RefreshesCredentials as BaseRefreshesCredentials;

/**
 * Optional capability of an Authenticator whose credentials expire (OAuth access tokens, session
 * tokens): AbstractHttpProvider asks it after a failed call and, when it agrees, discards the
 * credentials and repeats the call exactly once.
 */
interface RefreshesCredentials extends Authenticator, BaseRefreshesCredentials {}
