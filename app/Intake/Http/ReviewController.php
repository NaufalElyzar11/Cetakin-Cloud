<?php

namespace App\Intake\Http;

use App\Access\Models\Customer;
use App\Access\Models\User;
use App\Files\DesignFileVersion;
use App\Files\DesignObjectStores;
use App\Files\ObjectUnavailable;
use App\Intake\Models\PrintRequest;
use App\Intake\RequestReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReviewController
{
    public function __construct(private RequestReview $reviews) {}

    public function index(Request $request): Response
    {
        return DB::transaction(function () use ($request): Response {
            DB::table('access_control')->where('id', 1)->lockForUpdate()->firstOrFail();
            $actor = $this->actor($request);
            // The list is real work, not a customer/design directory. No remote
            // reads or sensitive file names for every row merely to render a list.
            $prints = PrintRequest::query()->orderByRaw("case when status = 'SUBMITTED' then 0 else 1 end")->orderBy('submitted_at')->orderBy('id')->simplePaginate(20);

            return Inertia::render('Review/Index', ['actor' => $actor->only('id', 'name', 'email'),
                'requests' => $prints->getCollection()->map(fn (PrintRequest $print) => $this->projection($print)),
                'pagination' => ['previousUrl' => $prints->previousPageUrl(), 'nextUrl' => $prints->nextPageUrl()]]);
        });
    }

    public function show(Request $request, PrintRequest $printRequest): Response
    {
        $actor = $this->actor($request);
        $canReview = $this->reviews->canReview($actor->id, $printRequest);
        $availability = $canReview ? $this->reviews->availability($printRequest) : null;

        return DB::transaction(function () use ($request, $actor, $printRequest, $canReview, $availability): Response {
            // Assemble a short coherent SQL projection only after all external I/O.
            DB::table('access_control')->where('id', 1)->lockForUpdate()->firstOrFail();
            $printRequest = PrintRequest::query()->lockForUpdate()->findOrFail($printRequest->id);
            // Even a page request cannot finish with a scope revoked during I/O.
            $this->reviews->owner($actor->id);
            abort_if($canReview && ! $this->reviews->canReview($actor->id, $printRequest), 403);
            $files = $canReview ? DesignFileVersion::query()->where('request_id', $printRequest->id)->orderBy('position')->get()->map(fn (DesignFileVersion $file) => [
                'id' => $file->id, 'name' => $file->original_name, 'format' => $file->format, 'sizeBytes' => $file->size_bytes,
                'available' => ($availability[$file->id] ?? null) === 'AVAILABLE',
                'downloadUrl' => route('review.file', ['printRequest' => $printRequest, 'file' => $file]),
            ]) : [];
            $latest = $canReview ? DB::table('request_review_events')->where('request_id', $printRequest->id)->where('kind', 'REVIEW')->orderByDesc('revision')->first() : null;
            $history = $canReview ? DB::table('request_review_events as e')->join('users as u', 'u.id', '=', 'e.actor_id')->where('e.request_id', $printRequest->id)->orderByDesc('e.revision')->get(['e.kind', 'e.occurred_at', 'e.reason', 'u.name'])->map(fn ($event) => ['kind' => $event->kind,
                'actorName' => $event->name, 'occurredAt' => $event->occurred_at, 'summary' => $event->reason ?? 'Manual review recorded.']) : [];

            return Inertia::render('Review/Show', ['actor' => $actor->only('id', 'name', 'email'),
                'request' => [...$this->projection($printRequest, $availability), 'notes' => $canReview ? $printRequest->notes : null,
                    'files' => $files, 'canReview' => $canReview, 'revision' => $this->reviews->currentWork($printRequest->id)->revision,
                    'basis' => $this->reviews->basis($printRequest)['digest'],
                    'latestReview' => $latest === null ? null : ['complete' => $latest->complete, 'materialAvailable' => $latest->material_available,
                        'feasible' => $latest->feasible, 'sufficient' => $latest->sufficient, 'findings' => $latest->findings, 'reviewedAt' => $latest->occurred_at],
                    'history' => $history, 'reviewUrl' => route('review.record', $printRequest),
                    'assignmentUrl' => route('review.assign', $printRequest), 'declineUrl' => route('review.decline', $printRequest)],
                'responsibleOptions' => User::query()->whereIn('id', DB::table('staff_responsibility_grants')->where('responsibility', 'owner')->whereNull('revoked_at')->select('user_id'))->orderBy('id')->get(['id', 'name']),
                'status' => $request->session()->get('status')]);
        });
    }

    public function review(Request $request, PrintRequest $printRequest): RedirectResponse
    {
        $actor = $this->actor($request);
        $input = $request->validate([...$this->workRules(), 'basis' => ['required', 'regex:/^[0-9a-f]{64}$/D'],
            'complete' => ['required', 'boolean'], 'material_available' => ['required', 'boolean'], 'feasible' => ['required', 'boolean'],
            'sufficient' => ['required', 'boolean'], 'findings' => ['required', 'string', 'max:4000']]);
        foreach (['complete', 'material_available', 'feasible', 'sufficient'] as $field) {
            $input[$field] = $request->boolean($field);
        }
        $this->reviews->review($actor->id, $printRequest->id, $input);

        return redirect()->route('review.show', $printRequest)->with('status', 'Manual review recorded. This creates no quotation or production approval.');
    }

    public function assign(Request $request, PrintRequest $printRequest): RedirectResponse
    {
        $actor = $this->actor($request);
        $input = $request->validate([...$this->workRules(), 'reason' => ['required', 'string', 'max:1000']]);
        $this->reviews->assign($actor->id, $printRequest->id, $input);

        return redirect()->route('review.show', $printRequest)->with('status', 'Review responsibility and next action recorded. No staff privilege was granted.');
    }

    public function decline(Request $request, PrintRequest $printRequest): RedirectResponse
    {
        $actor = $this->actor($request);
        $input = $request->validate(['revision' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:1000'],
            'customer_reason' => ['required', 'string', 'max:1000'], ...$this->prohibited()]);
        $this->reviews->decline($actor->id, $printRequest->id, $input);

        return redirect()->route('review.show', $printRequest)->with('status', 'Request declined. Its submitted files and history remain retained.');
    }

    public function download(Request $request, PrintRequest $printRequest, DesignFileVersion $file, DesignObjectStores $stores): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($file->request_id === $printRequest->id && $this->reviews->canReview($actor->id, $printRequest), 403);
        $attempt = DB::table('submission_attempts')->where('id', $file->attempt_id)->firstOrFail();
        try {
            $read = $stores->forAttempt($attempt)->read($file->attempt_id, ['id' => $file->id, 'size' => $file->size_bytes, 'digest' => $file->digest]);
        } catch (RuntimeException $exception) {
            if ($exception instanceof ObjectUnavailable && $exception->outcome === 'UNKNOWN') {
                abort(503, 'The exact design cannot be verified right now.');
            }
            abort(410, 'The exact submitted design is unavailable; its history is retained.');
        }
        // Close the verified spool if authority/scope vanished while retrieving it.
        if (! $this->reviews->canReview($actor->id, $printRequest)) {
            $read->close();
            abort(403);
        }

        return response()->stream(function () use ($read): void {
            try {
                fpassthru($read->stream());
            } finally {
                $read->close();
            }
        }, 200, ['Content-Length' => (string) $file->size_bytes, 'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $file->original_name, 'design.'.strtolower($file->format)),
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $this->reviews->owner($actor->id);

        return $actor;
    }

    private function projection(PrintRequest $print, ?array $availability = null): array
    {
        $customer = Customer::findOrFail($print->customer_id);
        $work = $this->reviews->currentWork($print->id);
        $responsible = $work->responsible_grant_id === null ? null : DB::table('staff_responsibility_grants as g')->join('users as u', 'u.id', '=', 'g.user_id')->where('g.id', $work->responsible_grant_id)->first(['u.id', 'u.name', 'g.revoked_at']);

        return ['id' => $print->id, 'customer' => $customer->only('id', 'name', 'email', 'phone'),
            'submittedAt' => $print->submitted_at->toIso8601String(), 'status' => $print->status,
            'material' => $print->material, 'color' => $print->color, 'quantity' => (string) $print->quantity,
            'fileCount' => DB::table('design_file_versions')->where('request_id', $print->id)->count(),
            'availableFileCount' => $availability === null ? null : count(array_filter($availability, fn ($state) => $state === 'AVAILABLE')),
            'reviewDisposition' => $this->reviews->disposition($print, $availability),
            'workDisposition' => $print->status === 'DECLINED' ? 'TERMINAL' : ($work->work_disposition ?? 'ACTIONABLE'),
            'responsible' => $responsible === null ? null : ['id' => $responsible->id, 'name' => $responsible->name, 'current' => $responsible->revoked_at === null],
            'nextAction' => $work->next_action, 'detailUrl' => route('review.show', $print)];
    }

    private function workRules(): array
    {
        return ['revision' => ['required', 'integer', 'min:0'], 'responsible_user_id' => ['present', 'nullable', 'integer', 'min:1'],
            'next_action' => ['required', 'string', 'max:500'], 'work_disposition' => ['required', Rule::in(['ACTIONABLE', 'WAITING', 'BLOCKED'])], ...$this->prohibited()];
    }

    private function prohibited(): array
    {
        return ['actor_id' => ['prohibited'], 'actor_grant_id' => ['prohibited'], 'customer_id' => ['prohibited'],
            'request_id' => ['prohibited'], 'status' => ['prohibited'], 'files_available' => ['prohibited'], 'basis_snapshot' => ['prohibited']];
    }
}
