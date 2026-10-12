<?php

namespace App\Access\Http\Controllers;

use App\Access\Actions\StaffAccess;
use App\Access\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class StaffController
{
    public function index(Request $request): Response
    {
        $actor = $this->owner($request);

        return Inertia::render('Access/Staff', [
            'actor' => $actor->only('id', 'name', 'email'),
            // Identity lookup, not a Customer directory or business data feed.
            'users' => User::query()->orderBy('id')->get(['id', 'name', 'email']),
            'grants' => DB::table('staff_responsibility_grants')->orderBy('id')->get(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function grant(Request $request, StaffAccess $access): RedirectResponse
    {
        $actor = $this->owner($request);
        $input = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'responsibility' => ['required', Rule::in(['owner', 'operator'])],
            'reason' => ['required', 'string', 'max:500'],
            'acting_user_id' => ['prohibited'], 'authorization_source' => ['prohibited'],
        ]);
        $access->grant($actor->id, (int) $input['user_id'], $input['responsibility'], $input['reason']);

        return redirect()->route('staff')->with('status', 'Responsibility granted.');
    }

    public function revoke(Request $request, int $grant, StaffAccess $access): RedirectResponse
    {
        $actor = $this->owner($request);
        $input = $request->validate(['reason' => ['required', 'string', 'max:500'], 'acting_user_id' => ['prohibited']]);
        $access->revoke($actor->id, $grant, $input['reason']);

        return redirect()->route('staff')->with('status', 'Responsibility revoked. Physical work was not changed.');
    }

    private function owner(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasResponsibility('owner'), 403);

        return $actor;
    }
}
