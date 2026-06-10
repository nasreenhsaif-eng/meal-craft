<?php

namespace App\Http\Responses;

use App\Support\PostAuthenticationRedirect;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $target = PostAuthenticationRedirect::pathFor($user);

        $redirect = $user->isAdmin()
            ? redirect()->to($target)
            : redirect()->intended($target);

        if ($request->wantsJson()) {
            return new JsonResponse([
                'two_factor' => false,
                'redirect' => $redirect->getTargetUrl(),
            ], 200);
        }

        return $redirect;
    }
}
