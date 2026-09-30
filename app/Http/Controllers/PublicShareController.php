<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Public share links live in the SPA now — keep the minted /share/{token}
 * URLs working by redirecting to the SPA page, which reads the post through
 * GET /api/v1/shares/{token}.
 */
class PublicShareController extends Controller
{
    public function show(string $token): RedirectResponse
    {
        return redirect("/app/share/{$token}");
    }
}
