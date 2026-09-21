<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caps the two account forms that send an email to whatever address is typed.
 *
 * Fortify throttles logins but not these, and each one mails a stranger from
 * our domain: a script looping on either turns the site into a spam cannon and
 * our sending reputation into the casualty — the same reputation the contact
 * form's replies and every reset link depend on. A teacher needs one or two;
 * this only stops floods.
 */
class ThrottleAccountMail
{
    /** @var list<string> */
    private const ROUTES = ['register.store', 'password.email'];

    private const MAX_PER_HOUR = 5;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('post') || ! $request->routeIs(...self::ROUTES)) {
            return $next($request);
        }

        $key = 'account-mail|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            return back()
                ->withInput($request->only('name', 'email'))
                ->withErrors(['email' => 'Too many attempts from this connection. Please try again in an hour.']);
        }

        RateLimiter::hit($key, 3600);

        return $next($request);
    }
}
