<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifySignupOtpRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\PostAuthenticationRedirect;
use App\Support\SignupOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class JoinVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isCustomer()) {
            abort(403);
        }

        if (! $user->needsSignupVerification()) {
            return redirect(PostAuthenticationRedirect::pathFor($user));
        }

        $user->loadMissing('customerProfile');

        $previewCode = $this->previewCodeFor($user);

        return view('pages::auth.join-verify', [
            'email' => $user->email,
            'maskedPhone' => PhoneNumber::mask($user->customerProfile?->phone),
            'previewCode' => $previewCode,
            'deliveryWarning' => $previewCode !== null
                ? __('Email and WhatsApp are not connected on this machine, so the code is shown here.')
                : null,
        ]);
    }

    public function store(VerifySignupOtpRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('customerProfile');

        $result = SignupOtp::check($user, (string) $request->validated('code'));

        if ($result !== 'ok') {
            throw ValidationException::withMessages([
                'code' => match ($result) {
                    'locked' => __('Too many attempts. Send a new code.'),
                    'expired' => __('That code has expired. Send a new one.'),
                    default => __('That code is incorrect.'),
                },
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $user->customerProfile?->forceFill([
            'phone_verified_at' => now(),
        ])->save();

        $user->unsetRelation('customerProfile');
        SignupOtp::forgetPreview();

        $redirect = redirect(PostAuthenticationRedirect::pathFor($user));

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => $redirect->getTargetUrl(),
            ]);
        }

        return $redirect;
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isCustomer() || ! $user->needsSignupVerification()) {
            abort(403);
        }

        $user->loadMissing('customerProfile');

        try {
            $result = SignupOtp::issue($user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('warning', __('We could not send a new code. Please try again.'));
        }

        if (SignupOtp::shouldPreviewOnScreen()) {
            return back()->with('status', __('A new code is shown on this screen.'));
        }

        if ($result['whatsapp'] === 'failed') {
            return back()
                ->with('status', __('A new code was emailed to you.'))
                ->with('warning', __('WhatsApp could not be delivered. You can still use the email code, or resend.'));
        }

        return back()->with('status', __('A new code was sent to your email and WhatsApp.'));
    }

    private function previewCodeFor(User $user): ?string
    {
        if (! SignupOtp::shouldPreviewOnScreen()) {
            return null;
        }

        $preview = SignupOtp::previewCode();

        if ($preview !== null) {
            return $preview;
        }

        SignupOtp::issue($user);

        return SignupOtp::previewCode();
    }
}
