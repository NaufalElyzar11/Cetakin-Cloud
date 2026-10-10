<?php

namespace App\Access\Http\Requests;

use App\Access\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(User::class)],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', Password::min(8), 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('The password must not exceed 72 bytes.');
                }
            }],
            'user_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'role' => ['prohibited'],
            'roles' => ['prohibited'],
            'is_admin' => ['prohibited'],
            'permissions' => ['prohibited'],
        ];
    }
}
