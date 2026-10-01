<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\EmailVerificationNotificationSentResponse;
use App\Support\SpaRedirect;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse as EmailVerificationNotificationSentResponseContract;
use Laravel\Fortify\Fortify;
use Override;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(
            EmailVerificationNotificationSentResponseContract::class,
            EmailVerificationNotificationSentResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views — all auth pages are served by the SPA under
     * /app, so every GET view redirects there (the SPA fetches the feature
     * flags the old pages embedded from /api/v1/auth/options instead).
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => SpaRedirect::to('/login')($request));

        Fortify::resetPasswordView(fn (Request $request) => redirect()->to(
            '/app/reset-password?'.http_build_query([
                'token' => $request->route('token'),
                'email' => $request->email,
            ])
        ));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => SpaRedirect::to('/forgot-password')($request));

        Fortify::verifyEmailView(fn () => redirect('/app/verify-email'));

        Fortify::registerView(fn (Request $request) => SpaRedirect::to('/register')($request));

        Fortify::twoFactorChallengeView(fn () => redirect('/app/two-factor-challenge'));

        Fortify::confirmPasswordView(fn (Request $request) => SpaRedirect::to('/confirm-password')($request));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', fn (Request $request) => Limit::perMinute(10)->by(
            ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
        ));
    }
}
