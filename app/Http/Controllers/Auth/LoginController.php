<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'nullable|in:admin,enseignant,etudiant',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            $user = Auth::user();
            
            // Vérifier le rôle si fourni
            if ($request->filled('role') && $user->role !== $request->role) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => ['Le rôle sélectionné ne correspond pas à votre compte.'],
                ]);
            }
            
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => ['Les identifiants fournis ne correspondent pas.'],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
