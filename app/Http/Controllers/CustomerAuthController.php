<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Commerce\Services\GuestOrderClaimService;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAuthController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('Account/Auth/Login');
    }

    public function authenticate(Request $request, GuestOrderClaimService $claims): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'password' => ['required', 'string']]);
        $data['email'] = mb_strtolower(trim($data['email']));

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The provided credentials do not match our records.']);
        }
        if ($request->user()->isStaff()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'Staff accounts must use the operations login.']);
        }

        $request->session()->regenerate();
        $claims->claimForVerifiedCustomer($request->user());

        return redirect()->intended($request->user()->hasVerifiedEmail() ? route('account.index') : route('verification.notice'));
    }

    public function register(): Response
    {
        return Inertia::render('Account/Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->mixedCase()->numbers()],
        ]);

        if (User::query()->whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
        }

        $user = User::query()->create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => false,
            'role' => AdminRole::Customer,
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function forgotPassword(): Response
    {
        return Inertia::render('Account/Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && ! $user->isStaff()) {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        return back()->with('success', 'If a customer account exists for that address, a reset link has been sent.');
    }

    public function resetPassword(Request $request, string $token): Response
    {
        return Inertia::render('Account/Auth/ResetPassword', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function updatePassword(Request $request, GuestOrderClaimService $claims): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $repository = Password::broker()->getRepository();

        if (! $user || $user->isStaff() || ! $repository->exists($user, $data['token'])) {
            throw ValidationException::withMessages(['email' => 'This password reset link is invalid or has expired.']);
        }

        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $repository->delete($user);
        event(new PasswordReset($user));
        Auth::login($user);
        $request->session()->regenerate();
        $claims->claimForVerifiedCustomer($user);

        return redirect()->route($user->hasVerifiedEmail() ? 'account.index' : 'verification.notice')->with('success', 'Your password has been reset.');
    }
}
