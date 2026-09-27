<?php
namespace App\Http\Controllers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
class AuthController extends Controller
{
    public function create(): Response { return Inertia::render('Auth/Login'); }
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) throw ValidationException::withMessages(['email'=>'The provided credentials do not match our records.']);
        if (! $request->user()?->isStaff()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email'=>'This sign-in is reserved for RAOZA staff.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
