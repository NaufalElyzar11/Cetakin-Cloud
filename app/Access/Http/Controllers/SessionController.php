<?php

namespace App\Access\Http\Controllers;

use App\Access\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController
{
    public function create(): Response
    {
        return Inertia::render('Access/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        return redirect()->route('account');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
