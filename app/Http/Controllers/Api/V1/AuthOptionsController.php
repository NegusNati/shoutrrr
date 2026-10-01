<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvitation;
use App\Support\InstanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

/**
 * Public options for the SPA's auth pages — the JSON equivalent of the props
 * the Fortify view callbacks previously used by the server-rendered auth pages
 * (provider buttons, registration/reset feature flags, password rules).
 */
class AuthOptionsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = app(InstanceSettings::class);
        $invitation = $request->query('invitation');
        $invitation = is_string($invitation) ? $invitation : null;

        $canRegister = $settings->registrationsAllowed($invitation);

        $model = $invitation !== null
            ? WorkspaceInvitation::findByToken($invitation)
            : null;

        return response()->json([
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => $canRegister,
            'registrationDisabledMessage' => $canRegister ? null : 'Registration is disabled for this instance.',
            'providers' => SocialProvider::enabledProvidersWithLabels(),
            'invitation' => $invitation,
            'invitationEmail' => $model?->isValid() ? $model->email : null,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ...$this->defaultLoginCredentials(),
        ]);
    }

    /**
     * Default credentials for local development, matching the old login view.
     *
     * @return array<string, array{email: string, password: string}>
     */
    private function defaultLoginCredentials(): array
    {
        if (! app()->isLocal()) {
            return [];
        }

        return [
            'defaultLogin' => [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
        ];
    }
}
