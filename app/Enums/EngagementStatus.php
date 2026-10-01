<?php

declare(strict_types=1);

namespace App\Enums;

enum EngagementStatus: string
{
    case Ok = 'ok';
    case Unsupported = 'unsupported';
    case RateLimited = 'rate_limited';
    case AuthExpired = 'auth_expired';
    case Failed = 'failed';

    public function isOk(): bool
    {
        return $this === self::Ok;
    }

    /**
     * The HTTP status a connector outcome is reported as by the engagement
     * action endpoints.
     *
     * 422 is deliberately absent and must never be used here. The SPA treats
     * 422 as the validation-errors path, parsing the body as `data.errors`.
     * A connector failure returned as 422 would therefore surface an empty
     * error bag — silently swallowing the message and skipping the rollback /
     * error toast. That is precisely the silent-lie bug this map exists to
     * fix, so 422 stays reserved for real validation errors.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::Ok => 200,
            self::AuthExpired => 403,
            self::Unsupported => 409,
            self::RateLimited => 429,
            self::Failed => 502,
        };
    }
}
