<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Account/Settings', [
            'profile' => $request->user()->only('name', 'email', 'phone'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'current_password' => ['nullable', 'string'],
        ]);
        $user = $request->user();
        $emailChanged = $data['email'] !== mb_strtolower($user->email);

        if ($emailChanged && (! $data['current_password'] || ! Hash::check($data['current_password'], $user->password))) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is required to change the email address.']);
        }
        if ($emailChanged && User::query()->whereKeyNot($user->id)->whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
        }

        $user->forceFill([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'phone' => ! empty($data['phone']) ? trim($data['phone']) : null,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('success', 'Profile updated. Verify the new email address to reopen your account portal.');
        }

        return back()->with('success', 'Account details updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from the current password.']);
        }

        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();

        return back()->with('success', 'Password updated securely.');
    }
}
