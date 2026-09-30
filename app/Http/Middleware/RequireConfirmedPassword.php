<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Override;

/**
 * RequirePassword for the API surface: the stock middleware calls
 * `$request->session()` unconditionally, which 500s on stateless requests
 * (Sanctum only attaches StartSession to stateful-domain traffic). A
 * sessionless caller can't have confirmed anything, so it fails the gate.
 */
class RequireConfirmedPassword extends RequirePassword
{
    /**
     * @param  Request  $request
     * @param  int|null  $passwordTimeoutSeconds
     */
    #[Override]
    protected function shouldConfirmPassword($request, $passwordTimeoutSeconds = null): bool
    {
        if (! $request->hasSession()) {
            return true;
        }

        return parent::shouldConfirmPassword($request, $passwordTimeoutSeconds);
    }
}
