<?php

declare(strict_types=1);

namespace App\Http\Requests\ConnectedAccount;

use App\Models\ConnectedAccount;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAutoRepostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        if (! $account instanceof ConnectedAccount) {
            // API binds `accountId` as a string — resolve it the same way the
            // action will (workspace-scoped) before checking update ability.
            $account = ConnectedAccount::query()
                ->withoutGlobalScopes()
                ->where('workspace_id', $this->user()?->current_workspace_id)
                ->whereKey($this->route('accountId'))
                ->firstOr(fn () => abort(404, 'No account with that id exists in this workspace.'));
        }

        return $this->user()?->can('update', $account) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'min_percentile' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
