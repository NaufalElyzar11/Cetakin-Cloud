<?php

namespace App\Intake\Http;

use App\Access\Models\Customer;
use App\Access\Models\User;
use App\Files\DesignContent;
use App\Files\DesignFileVersion;
use App\Files\FileSelection;
use App\Files\PrivateDesignFiles;
use App\Intake\Models\PrintRequest;
use App\Intake\SubmissionSpecification;
use App\Intake\SubmitPrintRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class RequestController
{
    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $customers = $actor->customers()->wherePivotNull('revoked_at')->orderBy('customers.id')->get();
        $requests = PrintRequest::query()->whereIn('customer_id', $customers->pluck('id'))->orderByDesc('submitted_at')->orderBy('id')->simplePaginate(20);

        return Inertia::render('Intake/Index', [
            'actor' => $actor->only('id', 'name', 'email'),
            'customers' => $customers->map(fn (Customer $customer) => $customer->only('id', 'name')),
            'requests' => $requests->getCollection()->map(fn (PrintRequest $print) => $this->projection($print, $customers->firstWhere('id', $print->customer_id))),
            'pagination' => ['previousUrl' => $requests->previousPageUrl(), 'nextUrl' => $requests->nextPageUrl()],
            'createUrl' => route('requests.create'),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->actor($request);

        // A bookmarkable operation reference survives a reload/login. This
        // URL is not a durable intent or saved draft until validated submission.
        return redirect()->route('requests.attempt', ['operationId' => (string) Str::uuid()]);
    }

    public function attempt(Request $request, string $operationId): Response|RedirectResponse
    {
        abort_unless(Str::isUuid($operationId), 404);
        $actor = $this->actor($request);
        $attempt = DB::table('submission_attempts')->where('actor_id', $actor->id)->where('operation_id', $operationId)->first();
        if ($attempt !== null) {
            $customer = Customer::findOrFail($attempt->customer_id);
            abort_unless(Gate::allows('view', $customer), 404);
            if ($attempt->request_id !== null) {
                return redirect()->route('requests.show', ['printRequest' => $attempt->request_id]);
            }
        }

        return $this->form($request, $operationId, $attempt === null ? 'NOT_RECEIVED' : 'UNRESOLVED');
    }

    private function form(Request $request, string $operation, ?string $status = null): Response
    {
        $actor = $this->actor($request);

        return Inertia::render('Intake/Create', [
            'actor' => $actor->only('id', 'name', 'email'),
            'customers' => $actor->customers()->wherePivotNull('revoked_at')->orderBy('customers.id')->get()->map(fn (Customer $customer) => $customer->only('id', 'name')),
            'operationId' => $operation, 'storeUrl' => route('requests.store'),
            'checkUrl' => route('requests.attempt', ['operationId' => $operation]),
            'attemptStatus' => $status,
            'limits' => ['maxFiles' => FileSelection::MAX_FILES, 'maxFileBytes' => FileSelection::MAX_FILE_BYTES, 'maxTotalBytes' => FileSelection::MAX_TOTAL_BYTES],
        ]);
    }

    public function store(Request $request, DesignContent $content, SubmitPrintRequest $submit): RedirectResponse
    {
        $actor = $this->actor($request);
        $validated = $request->validate([
            'operation_id' => ['required', 'uuid'], 'customer_id' => ['required', 'integer', 'min:1'],
            'material' => ['required', Rule::in(['PETG'])], 'color' => ['required', Rule::in(SubmissionSpecification::COLORS)],
            'quantity' => ['required'], 'notes' => ['nullable', 'string', 'max:4000'],
            'files' => ['required', 'array', 'min:1', 'max:5'], 'files.*' => ['required', 'file'],
            'submitted_by' => ['prohibited'], 'owner_id' => ['prohibited'], 'status' => ['prohibited'],
            'request_id' => ['prohibited'], 'file_ids' => ['prohibited'],
        ]);
        $quantity = SubmissionSpecification::quantity($validated['quantity']);
        $customer = Customer::find((int) $validated['customer_id']);
        abort_unless($customer !== null && Gate::allows('view', $customer), 404);
        $uploads = [];
        foreach (array_values($request->file('files', [])) as $index => $file) {
            $uploads[] = $content->inspect($file, 'files.'.$index);
        }
        FileSelection::validateSizes(array_column($uploads, 'size'));
        $specification = ['material' => 'PETG', 'color' => $validated['color'], 'quantity' => $quantity, 'notes' => $validated['notes'] ?? null];
        try {
            $id = $submit->submit($actor, $customer->id, $validated['operation_id'], $specification, $uploads);
        } catch (RuntimeException $exception) {
            // No internal SQL, object path, secret or raw exception is projected.
            throw ValidationException::withMessages(['submission' => 'Submission is not confirmed. The result may be unresolved. Check this attempt, or retry it with exactly the same files and specifications.']);
        }

        return redirect()->route('requests.show', ['printRequest' => $id], 303);
    }

    public function show(Request $request, PrintRequest $printRequest, PrivateDesignFiles $storage): Response
    {
        $actor = $this->actor($request);
        $customer = Customer::findOrFail($printRequest->customer_id);
        abort_unless(Gate::allows('view', $customer), 404);
        $files = DesignFileVersion::query()->where('request_id', $printRequest->id)->orderBy('position')->get();

        return Inertia::render('Intake/Show', [
            'actor' => $actor->only('id', 'name', 'email'),
            'request' => [...$this->projection($printRequest, $customer), 'notes' => $printRequest->notes,
                'files' => $files->map(function (DesignFileVersion $file) use ($storage, $printRequest): array {
                    $available = true;
                    try {
                        $storage->verify($storage->path($file->attempt_id, $file->id), $file->size_bytes, $file->digest);
                    } catch (RuntimeException $exception) {
                        $available = false;
                    }

                    return ['id' => $file->id, 'name' => $file->original_name, 'format' => $file->format, 'sizeBytes' => $file->size_bytes,
                        'available' => $available, 'downloadUrl' => route('requests.file', ['printRequest' => $printRequest, 'file' => $file])];
                }),
            ],
        ]);
    }

    public function download(Request $request, PrintRequest $printRequest, DesignFileVersion $file, PrivateDesignFiles $storage): BinaryFileResponse
    {
        $this->actor($request);
        $customer = Customer::findOrFail($printRequest->customer_id);
        abort_unless($file->request_id === $printRequest->id && Gate::allows('view', $customer), 404);
        $path = $storage->path($file->attempt_id, $file->id);
        try {
            $storage->verify($path, $file->size_bytes, $file->digest);
        } catch (RuntimeException $exception) {
            abort(410, 'The exact submitted file is currently unavailable. The request and version history remain retained.');
        }
        // A prior browser page or already-resolved metadata is not authority.
        abort_unless(Gate::allows('view', $customer), 404);

        return response()->file($path, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $file->original_name, 'design.'.strtolower($file->format)),
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function projection(PrintRequest $print, ?Customer $customer): array
    {
        return ['id' => $print->id, 'customerId' => $print->customer_id, 'customerName' => $customer?->name,
            'material' => $print->material, 'color' => $print->color, 'quantity' => (string) $print->quantity,
            'status' => $print->status, 'submittedAt' => $print->submitted_at->toIso8601String(),
            'detailUrl' => route('requests.show', ['printRequest' => $print])];
    }
}
