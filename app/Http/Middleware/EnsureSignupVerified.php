<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSignupVerified
{
    /**
     * Keep new customers on the signup OTP screen until email and WhatsApp are confirmed.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && $user->needsSignupVerification()
            && ! $request->routeIs('join.verify', 'join.verify.store', 'join.verify.resend', 'logout')
        ) {
            return redirect()->route('join.verify');
        }

        return $next($request);
    }
}
