<?php

namespace App\Access\Http\Controllers;

use App\Access\Actions\RegisterCustomer;
use App\Access\Http\Requests\RegisterRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController
{
    public function create(): Response
    {
        return Inertia::render('Access/Register');
    }

    public function store(RegisterRequest $request, RegisterCustomer $register): RedirectResponse
    {
        try {
            $user = $register->register($request->string('name')->toString(), $request->string('email')->toString(), $request->string('phone')->toString(), $request->string('password')->toString());
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'This email is already registered.']);
        }
        Auth::login($user);

        return redirect()->route('account');
    }
}
