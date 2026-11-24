<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CustomerAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('account');
        }

        return view('pages.account', [
            'authMode' => 'login',
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt([
            'email'     => $credentials['email'],
            'password'  => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'user'    => $this->safeUser(Auth::user()),
            ]);
        }

        return redirect()->intended(route('account'));
    }

    if ($request->wantsJson()) {
        return response()->json([
            'success' => false,
            'message' => 'The provided credentials do not match our records or the account is deactivated.',
        ], 422);
    }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records or the account is deactivated.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('account');
        }

        return view('pages.account', [
            'authMode' => 'register',
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'role'              => User::ROLE_CUSTOMER,
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'user'    => $this->safeUser($user),
            ], 201);
        }

        return redirect()->route('account');
    }

    /**
     * Return only the fields safe to expose to the client.
     * Never return the full model: role, is_active, email_verified_at etc.
     * are internal state the client does not need.
     */
    protected function safeUser(?\App\Models\User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('home');
    }
}
