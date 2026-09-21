<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Where an already logged-in teacher lands if they open a page meant
         * for guests — the log-in link they still have bookmarked, or the
         * browser restoring /login. The framework's default is the site root,
         * which looks like being logged out.
         */
        RedirectIfAuthenticated::redirectUsing(fn (): string => route('my-games'));

        /*
         * Length over composition rules: a long password a teacher can
         * remember beats a short one with a symbol stuck on the end. In
         * production it is also checked against known breaches (a k-anonymity
         * lookup — the password itself never leaves the server); tests and
         * local development skip that network call.
         */
        Password::defaults(function (): Password {
            $rule = Password::min(10)->max(128);

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
