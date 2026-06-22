<?php

namespace App\Http\Responses;

use App\Support\PostAuthenticationRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
                'redirect' => $this->relativeRedirectPath($redirect),
            ], 200);
        }

        return $redirect;
    }

    private function relativeRedirectPath(RedirectResponse $redirect): string
    {
        $target = $redirect->getTargetUrl();
        $path = parse_url($target, PHP_URL_PATH) ?: '/';
        $query = parse_url($target, PHP_URL_QUERY);
        $fragment = parse_url($target, PHP_URL_FRAGMENT);

        $relative = $path
            .($query ? "?{$query}" : '')
            .($fragment ? "#{$fragment}" : '');

        return $relative === '' ? '/' : $relative;
    }
}
