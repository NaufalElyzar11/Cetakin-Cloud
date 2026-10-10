<?php

namespace App\Access\Http\Controllers;

use App\Access\Models\Customer;
use App\Access\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AccountController
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return Inertia::render('Access/Account', [
            'actor' => $actor->only('id', 'name', 'email'),
            'customers' => $actor->customers()->wherePivotNull('revoked_at')->orderBy('customers.id')->get()->map(fn (Customer $customer) => [...$customer->only('id', 'name', 'email', 'phone'), 'detailUrl' => route('account.customer', $customer)]),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        abort_unless(Gate::allows('view', $customer), 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return Inertia::render('Access/Customer', ['actor' => $actor->only('id', 'name', 'email'), 'customer' => $customer->only('id', 'name', 'email', 'phone')]);
    }
}
