<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Answers a reset request for an unknown email exactly like one for a real account,
 * so the form can't be used to find out who has an account. Other failures, such as
 * asking too often, are still shown.
 */
final readonly class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(private string $status) {}

    /** @param Request $request */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($this->status === Password::INVALID_USER) {
            $message = __(Password::RESET_LINK_SENT);

            return $request->wantsJson()
                ? new JsonResponse(['message' => $message])
                : back()->with('status', $message);
        }

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => [__($this->status)]]);
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($this->status)]);
    }
}
