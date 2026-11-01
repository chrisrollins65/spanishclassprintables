<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's own redirects, minus one destination.
 *
 * Whoever is sent to log in is sent back afterwards to the page they asked for,
 * and the session keeps that page across a sign-up too. So an /admin that was
 * tried while logged out carried on into a brand new teacher's account, and
 * their confirmation link ended on a 403 that looked like the link had failed.
 *
 * Only that destination goes. The rest has to survive, because it is what
 * brings a buyer from "Change the words or questions" back to /my-games with
 * their code still filled in once they have signed up.
 */
class NoAdminDetour implements LoginResponse, RegisterResponse, VerifyEmailResponse
{
    public function __construct(private Responsable $response) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $intended = (string) $request->session()->get('url.intended');
        $path = trim((string) parse_url($intended, PHP_URL_PATH), '/');

        if (($path === 'admin' || str_starts_with($path, 'admin/')) && ! $request->user()?->isAdmin()) {
            $request->session()->forget('url.intended');
        }

        return $this->response->toResponse($request);
    }
}
