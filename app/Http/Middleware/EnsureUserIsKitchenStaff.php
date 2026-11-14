<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsKitchenStaff
{
    /**
     * Allow Craft Master General, Admin, and Staff into legacy Livewire kitchen tools.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->role->isStaff()) {
            if ($user !== null) {
                return redirect($user->homePath())
                    ->with('error', __('That area is for kitchen staff only.'));
            }

            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
