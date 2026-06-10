<?php

namespace App\Http\Middleware;

use App\Support\PostAuthenticationRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalChoiceAccess
{
    /**
     * Portal choice is shown only to authenticated staff (admin / staff roles).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->isAdmin()) {
            return redirect()->to(PostAuthenticationRedirect::pathFor($user));
        }

        return $next($request);
    }
}
