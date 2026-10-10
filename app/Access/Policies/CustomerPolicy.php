<?php

namespace App\Access\Policies;

use App\Access\Models\Customer;
use App\Access\Models\User;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $user->customers()->whereKey($customer->getKey())->wherePivotNull('revoked_at')->exists();
    }
}
