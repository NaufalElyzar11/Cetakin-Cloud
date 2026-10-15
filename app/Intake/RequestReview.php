<?php

namespace App\Intake;

use App\Access\Models\User;
use App\Files\DesignObjectStores;
use App\Files\ObjectUnavailable;
use App\Intake\Models\PrintRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Manual review of actual submitted work; assignment is not a staff grant. */
class RequestReview
{
    public function __construct(private DesignObjectStores $stores) {}

    public function owner(int $actor): int
    {
        $grant = User::findOrFail($actor)->currentResponsibilityGrantId('owner');
        abort_if($grant === null, 403);

        return $grant;
    }

    public function currentWork(string $request): object
    {
        return DB::table('request_review_work as w')->leftJoin('request_review_events as e', 'e.id', '=', 'w.event_id')
            ->where('w.request_id', $request)->select('w.revision', 'e.responsible_grant_id', 'e.next_action', 'e.work_disposition')->firstOrFail();
    }

    /** @phpstan-impure Fresh grants, assignment and state may change during object I/O. */
    public function canReview(int $actor, PrintRequest $request): bool
    {
        $grant = User::findOrFail($actor)->currentResponsibilityGrantId('owner');
        $work = $this->currentWork($request->id);

        return $request->fresh()->status === 'SUBMITTED' && $grant !== null && $work->responsible_grant_id === $grant;
    }

    public function basis(PrintRequest $request): array
    {
        $attempt = DB::table('submission_attempts')->where('id', $request->attempt_id)->firstOrFail();

        return ['digest' => $attempt->payload_digest, 'snapshot' => ['specification' => json_decode($attempt->specification, true, flags: JSON_THROW_ON_ERROR),
            'files' => json_decode($attempt->manifest, true, flags: JSON_THROW_ON_ERROR)]];
    }

    /** Remote checks are bounded by the already-approved 1–5 exact files, outside SQL locks. */
    public function availability(PrintRequest $request): array
    {
        $attempt = DB::table('submission_attempts')->where('id', $request->attempt_id)->firstOrFail();
        $files = DB::table('design_file_versions')->where('request_id', $request->id)->orderBy('position')->get();
        $result = [];
        foreach ($files as $file) {
            try {
                $this->stores->forAttempt($attempt)->read($file->attempt_id, ['id' => $file->id, 'size' => $file->size_bytes, 'digest' => $file->digest])->close();
                $result[$file->id] = 'AVAILABLE';
            } catch (RuntimeException $exception) {
                $result[$file->id] = $exception instanceof ObjectUnavailable && $exception->outcome === 'UNKNOWN' ? 'UNKNOWN' : 'UNAVAILABLE';
            }
        }

        return $result;
    }

    /** Historical sufficiency is never a cached authorization for a later quotation. */
    public function disposition(PrintRequest $request, ?array $availability = null): string
    {
        $request = $request->fresh();
        if ($request->status !== 'SUBMITTED') {
            return 'TERMINAL';
        }
        $event = DB::table('request_review_events')->where('request_id', $request->id)->where('kind', 'REVIEW')->orderByDesc('revision')->first();
        if ($event === null) {
            return 'NEEDS_REVIEW';
        }
        $basis = $this->basis($request);
        if (! hash_equals($basis['digest'], $event->basis_digest) || $basis['snapshot'] != json_decode($event->basis, true, flags: JSON_THROW_ON_ERROR)) {
            return 'STALE';
        }
        $expected = DB::table('design_file_versions')->where('request_id', $request->id)->pluck('id')->all();
        $provided = array_keys($availability ?? []);
        sort($expected);
        sort($provided);
        if ($availability !== null && ($provided !== $expected || count($expected) === 0 || count(array_filter($availability, fn ($state) => $state !== 'AVAILABLE')) > 0)) {
            return 'UNAVAILABLE';
        }
        if (! $event->sufficient) {
            return 'INSUFFICIENT';
        }

        return $availability === null ? 'NEEDS_VERIFICATION' : 'SUFFICIENT';
    }

    public function assign(int $actor, string $request, array $input): void
    {
        DB::transaction(function () use ($actor, $request, $input): void {
            [$print, $work, $grant] = $this->lock($actor, $request, (int) $input['revision']);
            $target = $this->target($input['responsible_user_id'] ?? null);
            $this->append($print, $work, $actor, $grant, 'ASSIGN', [
                'reason' => trim($input['reason']), 'responsible_grant_id' => $target,
                'next_action' => trim($input['next_action']), 'work_disposition' => $input['work_disposition'],
            ]);
        });
    }

    public function review(int $actor, string $request, array $input): void
    {
        $print = PrintRequest::findOrFail($request);
        $this->owner($actor);
        abort_unless($this->canReview($actor, $print), 403);
        $basis = $this->basis($print);
        $availability = $this->availability($print);
        $available = count($availability) > 0 && count(array_filter($availability, fn ($state) => $state !== 'AVAILABLE')) === 0;
        if ($input['sufficient'] && (! $input['complete'] || ! $input['material_available'] || ! $input['feasible'] || ! $available)) {
            $this->conflict('A sufficient review requires positive manual findings and all exact files verified available.');
        }
        DB::transaction(function () use ($actor, $request, $input, $basis, $available): void {
            [$print, $work, $grant] = $this->lock($actor, $request, (int) $input['revision']);
            $this->assigned($work, $grant);
            if (! hash_equals($basis['digest'], $input['basis']) || ! hash_equals($this->basis($print)['digest'], $basis['digest'])) {
                $this->conflict('The exact review basis changed. Reload and examine the current files/specification.');
            }
            $this->append($print, $work, $actor, $grant, 'REVIEW', [
                'complete' => (bool) $input['complete'], 'material_available' => (bool) $input['material_available'],
                'feasible' => (bool) $input['feasible'], 'sufficient' => (bool) $input['sufficient'], 'findings' => trim($input['findings']),
                'files_available' => $available, 'files_checked_at' => now(),
                'responsible_grant_id' => $this->target($input['responsible_user_id'] ?? null),
                'next_action' => trim($input['next_action']), 'work_disposition' => $input['work_disposition'],
            ]);
        });
    }

    public function decline(int $actor, string $request, array $input): void
    {
        DB::transaction(function () use ($actor, $request, $input): void {
            // Retry checks still require current authority; terminal status never grants file access.
            DB::table('access_control')->where('id', 1)->lockForUpdate()->firstOrFail();
            $grant = $this->owner($actor);
            $print = PrintRequest::query()->lockForUpdate()->findOrFail($request);
            $work = $this->currentWork($request);
            if ($print->status === 'DECLINED') {
                $previous = DB::table('request_review_events')->where('request_id', $request)->where('kind', 'DECLINE')->firstOrFail();
                if ($previous->actor_id === $actor && $previous->reason === trim($input['reason']) && $previous->customer_reason === trim($input['customer_reason']) && (int) $input['revision'] === $previous->revision - 1) {
                    return;
                }
                $this->conflict('This request is already declined; its reason and history cannot be replaced.');
            }
            $this->openRevision($print, $work, (int) $input['revision']);
            $this->assigned($work, $grant);
            $this->append($print, $work, $actor, $grant, 'DECLINE', ['reason' => trim($input['reason']), 'customer_reason' => trim($input['customer_reason']),
                'responsible_grant_id' => null, 'next_action' => null, 'work_disposition' => 'TERMINAL']);
            DB::table('print_requests')->where('id', $request)->update(['status' => 'DECLINED']);
        });
    }

    private function lock(int $actor, string $request, int $revision): array
    {
        // Staff changes already use this mutex. Never acquire it after a Request lock.
        DB::table('access_control')->where('id', 1)->lockForUpdate()->firstOrFail();
        $grant = $this->owner($actor);
        $print = PrintRequest::query()->lockForUpdate()->findOrFail($request);
        $work = $this->currentWork($request);
        $this->openRevision($print, $work, $revision);

        return [$print, $work, $grant];
    }

    private function openRevision(PrintRequest $print, object $work, int $revision): void
    {
        if ($print->status !== 'SUBMITTED' || $work->revision !== $revision) {
            $this->conflict('This work changed or is terminal. Reload before recording another decision.');
        }
    }

    private function assigned(object $work, int $grant): void
    {
        abort_unless($work->responsible_grant_id === $grant, 403);
    }

    private function target(mixed $actor): ?int
    {
        if ($actor === null) {
            return null;
        }
        $grant = User::findOrFail((int) $actor)->currentResponsibilityGrantId('owner');
        if ($grant === null) {
            throw ValidationException::withMessages(['responsible_user_id' => 'An active Owner is required for manual review responsibility.']);
        }

        return $grant;
    }

    private function append(PrintRequest $print, object $work, int $actor, int $grant, string $kind, array $values): void
    {
        $basis = $this->basis($print);
        $revision = $work->revision + 1;
        $event = DB::table('request_review_events')->insertGetId(['request_id' => $print->id, 'revision' => $revision, 'kind' => $kind,
            'actor_id' => $actor, 'actor_grant_id' => $grant, 'basis_digest' => $basis['digest'], 'basis' => json_encode($basis['snapshot'], JSON_THROW_ON_ERROR),
            'occurred_at' => now(), ...$values]);
        DB::table('request_review_work')->where('request_id', $print->id)->update(['revision' => $revision, 'event_id' => $event]);
    }

    private function conflict(string $message): never
    {
        throw ValidationException::withMessages(['review' => $message]);
    }
}
