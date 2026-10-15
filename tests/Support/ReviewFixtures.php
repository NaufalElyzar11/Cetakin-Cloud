<?php

namespace Tests\Support;

use App\Access\Actions\RegisterCustomer;
use App\Access\Actions\StaffAccess;
use App\Access\Models\User;
use App\Files\DesignContent;
use App\Intake\Models\PrintRequest;
use App\Intake\RequestReview;
use App\Intake\SubmitPrintRequest;
use Illuminate\Support\Str;

trait ReviewFixtures
{
    use SyntheticDesigns;

    protected function reviewUser(string $email): User
    {
        return app(RegisterCustomer::class)->register('Synthetic '.strstr($email, '@', true), $email, '000', 'synthetic-password');
    }

    protected function submitted(User $customer): PrintRequest
    {
        $upload = app(DesignContent::class)->inspect($this->fixture($this->asciiStl()), 'files.0');
        $id = app(SubmitPrintRequest::class)->submit($customer, $customer->customers()->firstOrFail()->id, (string) Str::uuid(),
            ['material' => 'PETG', 'color' => 'orange', 'quantity' => '2', 'notes' => 'Synthetic customer note'], [$upload]);

        return PrintRequest::findOrFail($id);
    }

    protected function reviewOwner(string $email = 'owner@example.test'): User
    {
        $user = $this->reviewUser($email);
        app(StaffAccess::class)->bootstrap($user->id, 'Synthetic trusted source', 'Synthetic review Owner');

        return $user;
    }

    protected function assigned(User $owner, PrintRequest $print): void
    {
        app(RequestReview::class)->assign($owner->id, $print->id, ['revision' => 0, 'responsible_user_id' => $owner->id,
            'next_action' => 'Manually examine synthetic design', 'work_disposition' => 'ACTIONABLE', 'reason' => 'Take this actual review']);
    }

    protected function reviewInput(User $owner, PrintRequest $print): array
    {
        return ['revision' => app(RequestReview::class)->currentWork($print->id)->revision, 'basis' => app(RequestReview::class)->basis($print)['digest'],
            'complete' => true, 'material_available' => true, 'feasible' => true, 'sufficient' => true,
            'findings' => 'Internal synthetic feasibility and PETG availability confirmed manually.',
            'responsible_user_id' => $owner->id, 'next_action' => 'Await next approved workflow', 'work_disposition' => 'WAITING'];
    }
}
