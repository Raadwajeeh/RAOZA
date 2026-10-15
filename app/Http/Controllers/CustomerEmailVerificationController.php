<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Services\GuestOrderClaimService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerEmailVerificationController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        return Inertia::render('Account/Auth/VerifyEmail', ['email' => $request->user()->email]);
    }

    public function verify(EmailVerificationRequest $request, GuestOrderClaimService $claims): RedirectResponse
    {
        $request->fulfill();
        $claims->claimForVerifiedCustomer($request->user());

        return redirect()->route('account.index')->with('success', 'Your email address has been verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'A new verification link has been sent.');
    }
}
