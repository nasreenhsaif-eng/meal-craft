<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->canAccessAdminPortal()) {
            if ($user !== null && ! $user->canAccessAdminPortal()) {
                return redirect($user->homePath())
                    ->with('error', __('That area is for admin staff only.'));
            }

            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
