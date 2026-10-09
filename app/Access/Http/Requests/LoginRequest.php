<?php

namespace App\Access\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('The password must not exceed 72 bytes.');
                }
            }],
        ];
    }

    public function authenticate(): void
    {
        $key = 'login:'.hash('sha256', $this->string('email')->toString().'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many login attempts. Please try again later.']);
        }
        if (! Auth::attempt($this->only('email', 'password'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials could not be authenticated.']);
        }
        RateLimiter::clear($key);
    }
}
