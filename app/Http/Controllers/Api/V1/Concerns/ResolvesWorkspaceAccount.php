<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\ConnectedAccount;
use Illuminate\Support\Facades\Context;

/**
 * Shared connected-account lookup for the API controllers, scoped to the
 * caller's bound workspace so a foreign id is a 404 rather than a
 * cross-tenant leak.
 */
trait ResolvesWorkspaceAccount
{
    protected function findAccountOrFail(ConnectedAccount|string $account): ConnectedAccount
    {
        if ($account instanceof ConnectedAccount) {
            return $account;
        }

        return ConnectedAccount::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', Context::get('workspace_id'))
            ->whereKey($account)
            ->firstOr(fn () => abort(404, 'No account with that id exists in this workspace.'));
    }
}
