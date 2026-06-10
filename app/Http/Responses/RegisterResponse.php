<?php

namespace App\Http\Responses;

use App\Enums\OnboardingStep;
use App\Support\PostAuthenticationRedirect;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        $target = $user !== null
            ? PostAuthenticationRedirect::pathFor($user)
            : route('onboarding.show', ['step' => OnboardingStep::Gender->value], absolute: false);

        $redirect = redirect()->intended($target);

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => $redirect->getTargetUrl(),
            ], 201);
        }

        return $redirect;
    }
}
