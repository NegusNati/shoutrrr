<?php

declare(strict_types=1);

namespace App\Support;

/**
 * URL builder for the standalone SPA mounted at /app/*. OAuth callbacks and
 * other server-side flows that hand control back to the browser redirect here
 * once a surface has been ported — legacy `route()` targets would dump the user
 * out of the SPA into the Inertia app. Flash-style messages travel as query
 * params because the SPA has no session-flash channel.
 */
final class Spa
{
    /**
     * @param  string  $path  Path inside the SPA, e.g. '/accounts' or '/settings/workspace'.
     */
    public static function url(string $path, ?string $success = null, ?string $error = null): string
    {
        $query = array_filter(['success' => $success, 'error' => $error]);

        return '/app'.$path.($query === [] ? '' : '?'.http_build_query($query));
    }
}
