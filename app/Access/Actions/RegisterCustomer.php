<?php

namespace App\Access\Actions;

use App\Access\Models\Customer;
use App\Access\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class RegisterCustomer
{
    public function register(string $name, string $email, string $phone, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $phone, $password): User {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
            $customer = Customer::create(['name' => $name, 'email' => $email, 'phone' => $phone]);
            $user->customers()->attach($customer);

            return $user;
        });
    }
}
