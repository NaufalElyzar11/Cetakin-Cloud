<?php

namespace App\Access\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    /** @return BelongsToMany<Customer, $this> */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)->withPivot('revoked_at')->withTimestamps();
    }
}
