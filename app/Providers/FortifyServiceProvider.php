<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/**
 * Teacher accounts (docs/teacher-games.md).
 *
 * Fortify is the back end — sessions, throttling, reset and verification
 * tokens — and every page is ours, in the site's own style. Nothing here
 * decides who owns what; that belongs to the policy on each model.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('account.login'));
        Fortify::registerView(fn () => view('account.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('account.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('account.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('account.verify-email'));
        Fortify::confirmPasswordView(fn () => view('account.confirm-password'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
