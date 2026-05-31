<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminFromLegacySettings
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin() && $request->is('settings', 'settings/*')) {
            $path = $request->path();

            if (str_starts_with($path, 'settings/security')) {
                return redirect()->route('admin.settings.security');
            }

            if (str_starts_with($path, 'settings/appearance')) {
                return redirect()->route('admin.settings.appearance');
            }

            return redirect()->route('admin.settings.profile');
        }

        return $next($request);
    }
}
