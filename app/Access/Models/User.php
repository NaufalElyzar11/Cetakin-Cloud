<?php

namespace App\Access\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    public function hasResponsibility(string $responsibility): bool
    {
        return $this->currentResponsibilityGrantId($responsibility) !== null;
    }

    // Later work authorization must bind to this exact active Operator grant.
    // A new grant identity never revives authorization under a revoked one.
    public function currentResponsibilityGrantId(string $responsibility): ?int
    {
        $id = DB::table('staff_responsibility_grants')->where('user_id', $this->id)->where('responsibility', $responsibility)->whereNull('revoked_at')->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @return BelongsToMany<Customer, $this> */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)->withPivot('revoked_at')->withTimestamps();
    }
}
