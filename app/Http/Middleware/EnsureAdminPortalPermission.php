<?php

namespace App\Http\Middleware;

use App\Support\AdminPortalPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPortalPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->canAccessAdminPortal()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $key = $permission ?: AdminPortalPermission::keyForRoute($request->route()?->getName());

        if ($key === null) {
            return $next($request);
        }

        if (! $user->hasAdminPermission($key)) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('You do not have access to that section.'));
        }

        return $next($request);
    }
}
