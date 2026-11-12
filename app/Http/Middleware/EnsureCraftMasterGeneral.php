<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCraftMasterGeneral
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isCraftMasterGeneral()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', __('Only the Craft Master General can manage that.'));
        }

        return $next($request);
    }
}
