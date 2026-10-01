<?php

namespace App\Support;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SpaRedirect
{
    /**
     * Redirect a legacy web GET page to its React SPA equivalent under /app,
     * preserving the query string so shared/bookmarked links keep working.
     *
     * @return Closure(Request): RedirectResponse
     */
    public static function to(string $path): Closure
    {
        return function (Request $request) use ($path): RedirectResponse {
            $query = $request->getQueryString();
            $suffix = $query === null || $query === '' ? '' : '?'.$query;

            return redirect('/app'.$path.$suffix);
        };
    }
}
