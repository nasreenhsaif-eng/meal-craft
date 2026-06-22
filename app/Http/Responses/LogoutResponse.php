<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->route('login');
    }
}
