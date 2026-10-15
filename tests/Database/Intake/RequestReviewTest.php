<?php

namespace Tests\Database\Intake;

use App\Access\Actions\StaffAccess;
use App\Files\PrivateDesignFiles;
use App\Intake\RequestReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PDOException;
use Tests\Support\PostgresTestCase;
use Tests\Support\ReviewFixtures;

class RequestReviewTest extends PostgresTestCase
{
    use RefreshDatabase, ReviewFixtures;

    protected function setUp(): void
    {
        $this->mockConsoleOutput = false;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->removeDesignFixtures();
        File::deleteDirectory($this->privateTestRoot);
        parent::tearDown();
    }

    public function test_actual_work_list_and_explicit_assignment_scope_before_manual_review(): void
    {
        $owner = $this->reviewOwner();
        $customer = $this->reviewUser('customer@example.test');
        $print = $this->submitted($customer);
        $file = DB::table('design_file_versions')->first();
        $this->actingAs($owner)->get('/staff/requests')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Review/Index')
            ->has('requests', 1)->where('requests.0.status', 'SUBMITTED')->where('requests.0.responsible', null)
            ->where('requests.0.availableFileCount', null)->where('requests.0.reviewDisposition', 'NEEDS_REVIEW')->missing('requests.0.files'));
        $this->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->component('Review/Show')->where('request.canReview', false)->has('request.files', 0)->where('request.notes', null));
        $this->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertForbidden();
        $this->post('/staff/requests/'.$print->id.'/review', $this->reviewInput($owner, $print))->assertForbidden();
        $this->post('/staff/requests/'.$print->id.'/assignment', ['revision' => 0, 'responsible_user_id' => $owner->id, 'next_action' => 'Examine synthetic set', 'work_disposition' => 'ACTIONABLE', 'reason' => 'Owner takes review'])->assertRedirect();
        $this->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.canReview', true)->has('request.files', 1)->where('request.files.0.available', true)->where('request.responsible.current', true));
        $this->post('/staff/requests/'.$print->id.'/review', $this->reviewInput($owner, $print))->assertRedirect();
        DB::statement('set constraints all immediate');
        $this->assertDatabaseHas('request_review_events', ['request_id' => $print->id, 'kind' => 'REVIEW', 'actor_id' => $owner->id, 'sufficient' => true]);
        $event = DB::table('request_review_events')->where('kind', 'REVIEW')->first();
        $this->assertNotNull($event->occurred_at);
        $this->assertEquals(app(RequestReview::class)->basis($print)['snapshot'], json_decode($event->basis, true));
        $this->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.reviewDisposition', 'SUFFICIENT')->where('request.workDisposition', 'WAITING'));
        $this->get('/staff/requests')->assertInertia(fn (AssertableInertia $page) => $page->where('requests.0.reviewDisposition', 'NEEDS_VERIFICATION'));
        $this->assertSame('SUBMITTED', $print->fresh()->status);
        $this->assertDatabaseCount('design_file_versions', 1);
    }

    public function test_customer_operator_and_unassigned_owner_cannot_review_or_obtain_design_collection(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other-owner@example.test');
        $operator = $this->reviewUser('operator@example.test');
        $customer = $this->reviewUser('customer@example.test');
        $foreign = $this->reviewUser('foreign@example.test');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second review owner');
        app(StaffAccess::class)->grant($owner->id, $operator->id, 'operator', 'Operator only');
        $print = $this->submitted($customer);
        $this->assigned($owner, $print);
        $file = DB::table('design_file_versions')->first();
        foreach ([$customer, $operator] as $actor) {
            $this->app['session']->forget('password_hash_web');
            $this->actingAs($actor)->get('/staff/requests')->assertForbidden();
            $this->post('/staff/requests/'.$print->id.'/review', $this->reviewInput($owner, $print))->assertForbidden();
            $this->post('/staff/requests/'.$print->id.'/decline', ['revision' => 1, 'reason' => 'Forged internal', 'customer_reason' => 'Forged public'])->assertForbidden();
            $this->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertForbidden();
        }
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($other)->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.canReview', false)->has('request.files', 0)->where('request.latestReview', null));
        $this->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertForbidden();
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($foreign)->get('/requests/'.$print->id)->assertNotFound();
        $this->assertDatabaseCount('request_review_events', 1);
    }

    public function test_insufficient_missing_and_stale_reviews_never_report_sufficiency(): void
    {
        $owner = $this->reviewOwner();
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $input = $this->reviewInput($owner, $print);
        $this->actingAs($owner)->post('/staff/requests/'.$print->id.'/review', [...$input, 'complete' => false])->assertSessionHasErrors('review');
        $this->post('/staff/requests/'.$print->id.'/review', [...$input, 'basis' => str_repeat('0', 64)])->assertSessionHasErrors('review');
        $this->post('/staff/requests/'.$print->id.'/review', [...$input, 'complete' => false, 'sufficient' => false])->assertRedirect();
        $this->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.reviewDisposition', 'INSUFFICIENT'));
        $this->post('/staff/requests/'.$print->id.'/review', $input)->assertSessionHasErrors('review');
        $input = $this->reviewInput($owner, $print);
        $this->post('/staff/requests/'.$print->id.'/review', $input)->assertRedirect();
        $version = DB::table('design_file_versions')->first();
        unlink(app(PrivateDesignFiles::class)->path($version->attempt_id, $version->id));
        $this->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.reviewDisposition', 'UNAVAILABLE')->where('request.latestReview.sufficient', true)->where('request.files.0.available', false));
        $this->post('/staff/requests/'.$print->id.'/review', $this->reviewInput($owner, $print))->assertSessionHasErrors('review');
        $this->get('/staff/requests/'.$print->id.'/files/'.$version->id)->assertGone();
        $this->assertDatabaseCount('request_review_events', 3);
    }

    public function test_decline_is_terminal_customer_safe_attributable_and_exact_retry_is_idempotent(): void
    {
        $owner = $this->reviewOwner();
        $customer = $this->reviewUser('customer@example.test');
        $print = $this->submitted($customer);
        $this->assigned($owner, $print);
        $payload = ['revision' => 1, 'reason' => 'Internal machine capability finding', 'customer_reason' => 'This design is outside our current printing capability.'];
        $this->actingAs($owner)->post('/staff/requests/'.$print->id.'/decline', $payload)->assertRedirect();
        $this->post('/staff/requests/'.$print->id.'/decline', $payload)->assertRedirect();
        DB::statement('set constraints all immediate');
        $this->assertDatabaseHas('print_requests', ['id' => $print->id, 'status' => 'DECLINED']);
        $this->assertSame(1, DB::table('request_review_events')->where('kind', 'DECLINE')->count());
        $this->assertDatabaseHas('request_review_events', ['kind' => 'DECLINE', 'actor_id' => $owner->id, 'reason' => $payload['reason'], 'customer_reason' => $payload['customer_reason']]);
        $this->post('/staff/requests/'.$print->id.'/decline', [...$payload, 'customer_reason' => 'Changed reason'])->assertSessionHasErrors('review');
        $this->post('/staff/requests/'.$print->id.'/assignment', ['revision' => 2, 'responsible_user_id' => $owner->id, 'next_action' => 'Reopen', 'work_disposition' => 'ACTIONABLE', 'reason' => 'Cannot reopen'])->assertSessionHasErrors('review');
        $this->post('/staff/requests/'.$print->id.'/review', $this->reviewInput($owner, $print))->assertForbidden();
        $version = DB::table('design_file_versions')->first();
        $this->get('/staff/requests/'.$print->id.'/files/'.$version->id)->assertForbidden();
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($customer)->get('/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.status', 'DECLINED')->where('request.reviewOutcome.declineReason', $payload['customer_reason'])->missing('request.findings')->missing('request.history')->missing('request.responsible'))
            ->assertDontSee($payload['reason']);
        $this->get('/requests/'.$print->id.'/files/'.$version->id)->assertOk();
        $this->assertDatabaseCount('design_file_versions', 1);
    }

    public function test_old_model_and_positive_historical_review_cannot_make_a_declined_request_sufficient(): void
    {
        $owner = $this->reviewOwner();
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        app(RequestReview::class)->review($owner->id, $print->id, $this->reviewInput($owner, $print));
        $available = app(RequestReview::class)->availability($print);
        $this->assertSame('SUFFICIENT', app(RequestReview::class)->disposition($print, $available));
        $this->assertSame('UNAVAILABLE', app(RequestReview::class)->disposition($print, ['forged-version' => 'AVAILABLE']));
        app(RequestReview::class)->decline($owner->id, $print->id, ['revision' => 2, 'reason' => 'Later manual finding', 'customer_reason' => 'Cannot provide this work']);
        $this->assertSame('SUBMITTED', $print->status);
        $this->assertSame('TERMINAL', app(RequestReview::class)->disposition($print, $available));
        $this->actingAs($owner)->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.status', 'DECLINED')->where('request.reviewDisposition', 'TERMINAL')->where('request.workDisposition', 'TERMINAL'));
    }

    public function test_reassignment_revocation_and_regrant_remove_scope_without_erasing_review_truth(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other@example.test');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Coverage Owner');
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        app(RequestReview::class)->review($owner->id, $print->id, $this->reviewInput($owner, $print));
        $version = DB::table('design_file_versions')->first();
        $grant = $owner->currentResponsibilityGrantId('owner');
        $this->actingAs($owner)->get('/staff/requests/'.$print->id)->assertOk();
        app(StaffAccess::class)->revoke($other->id, $grant, 'Revoke old page access');
        $this->get('/staff/requests/'.$print->id.'/files/'.$version->id)->assertForbidden();
        app(StaffAccess::class)->grant($other->id, $owner->id, 'owner', 'Explicit responsibility regrant only');
        $this->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.canReview', false)->where('request.responsible.current', false)->where('request.reviewDisposition', 'NEEDS_VERIFICATION')->has('request.files', 0));
        $this->get('/staff/requests/'.$print->id.'/files/'.$version->id)->assertForbidden();
        $this->assertDatabaseHas('request_review_events', ['kind' => 'REVIEW', 'actor_grant_id' => $grant, 'sufficient' => true]);
        $this->post('/staff/requests/'.$print->id.'/assignment', ['revision' => 2, 'responsible_user_id' => $owner->id, 'next_action' => 'Resume review coverage', 'work_disposition' => 'ACTIONABLE', 'reason' => 'New explicit scope after regrant'])->assertRedirect();
        $this->get('/staff/requests/'.$print->id.'/files/'.$version->id)->assertOk();
    }

    public function test_operator_cannot_be_promoted_by_assignment_and_forged_provenance_is_rejected(): void
    {
        $owner = $this->reviewOwner();
        $operator = $this->reviewUser('operator@example.test');
        app(StaffAccess::class)->grant($owner->id, $operator->id, 'operator', 'Operator only');
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $payload = ['revision' => 0, 'responsible_user_id' => $operator->id, 'next_action' => 'Manual review', 'work_disposition' => 'ACTIONABLE', 'reason' => 'Invalid assignment'];
        $this->actingAs($owner)->post('/staff/requests/'.$print->id.'/assignment', $payload)->assertSessionHasErrors('responsible_user_id');
        $this->post('/staff/requests/'.$print->id.'/assignment', [...$payload, 'actor_id' => $owner->id])->assertSessionHasErrors('actor_id');
        $this->assertDatabaseCount('request_review_events', 0);
        $this->assertFalse($operator->hasResponsibility('owner'));
    }

    public function test_postgresql_rejects_destructive_history_state_bypass_and_inconsistent_review(): void
    {
        $owner = $this->reviewOwner();
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $event = (array) DB::table('request_review_events')->first();
        $nullReview = [...$event, 'id' => $event['id'] + 2000, 'revision' => 2, 'kind' => 'REVIEW', 'reason' => null,
            'complete' => true, 'material_available' => true, 'feasible' => true, 'sufficient' => true, 'files_available' => true,
            'files_checked_at' => now(), 'findings' => null];
        $nullDecline = [...$event, 'id' => $event['id'] + 3000, 'revision' => 2, 'kind' => 'DECLINE', 'responsible_grant_id' => null,
            'next_action' => null, 'work_disposition' => 'TERMINAL', 'reason' => null, 'customer_reason' => null];
        foreach ([
            fn () => DB::table('request_review_events')->where('id', $event['id'])->delete(),
            fn () => DB::table('request_review_events')->where('id', $event['id'])->update(['reason' => 'Rewrite']),
            fn () => DB::table('request_review_work')->where('request_id', $print->id)->update(['revision' => 7]),
            fn () => DB::table('print_requests')->where('id', $print->id)->update(['status' => 'DECLINED']),
            fn () => DB::table('print_requests')->where('id', $print->id)->update(['quantity' => 99]),
            fn () => DB::table('request_review_events')->insert([...$event, 'id' => $event['id'] + 1000, 'revision' => 2, 'basis_digest' => str_repeat('0', 64)]),
            fn () => DB::table('request_review_events')->insert([...$event, 'id' => $event['id'] + 1000, 'revision' => 2, 'reason' => null]),
            fn () => DB::table('request_review_events')->insert([...$event, 'id' => $event['id'] + 1000, 'revision' => 2, 'next_action' => null]),
            fn () => DB::table('request_review_events')->insert($nullReview),
            fn () => DB::table('request_review_events')->insert($nullDecline),
        ] as $operation) {
            try {
                DB::transaction($operation);
                $this->fail('PostgreSQL must preserve request provenance and immutable consequential history.');
            } catch (PDOException $exception) {
                $this->assertSame('23514', (string) $exception->getCode());
            }
        }
        $this->assertDatabaseCount('request_review_events', 1);
        $this->assertSame('SUBMITTED', $print->fresh()->status);
        $attempt = (array) DB::table('submission_attempts')->first();
        $newAttempt = (string) Str::uuid();
        DB::table('submission_attempts')->insert([...$attempt, 'id' => $newAttempt, 'operation_id' => (string) Str::uuid(), 'request_id' => null, 'confirmed_at' => null]);
        try {
            DB::transaction(fn () => DB::table('print_requests')->insert([...$print->getAttributes(), 'id' => (string) Str::uuid(), 'attempt_id' => $newAttempt, 'status' => 'DECLINED']));
            $this->fail('A new request cannot begin declined without a genuine subsequent decision.');
        } catch (PDOException $exception) {
            $this->assertSame('23514', (string) $exception->getCode());
        }
    }

    public function test_failed_decision_rolls_back_without_changing_prior_files_or_history(): void
    {
        $owner = $this->reviewOwner();
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $before = DB::table('request_review_events')->get()->toJson();
        DB::unprepared("create function synthetic_review_failure() returns trigger language plpgsql as \$\$ begin raise exception 'synthetic write failure'; end; \$\$; create trigger synthetic_review_failure before update on request_review_work for each row execute function synthetic_review_failure()");
        try {
            app(RequestReview::class)->decline($owner->id, $print->id, ['revision' => 1, 'reason' => 'Synthetic internal', 'customer_reason' => 'Synthetic decline']);
            $this->fail('Injected failure must not confirm decline.');
        } catch (PDOException) {
            $this->assertSame($before, DB::table('request_review_events')->get()->toJson());
            $this->assertSame('SUBMITTED', $print->fresh()->status);
            $this->assertDatabaseCount('design_file_versions', 1);
            $file = DB::table('design_file_versions')->first();
            $this->assertSame($file->digest, hash_file('sha256', app(PrivateDesignFiles::class)->path($file->attempt_id, $file->id)));
        } finally {
            DB::unprepared('drop trigger synthetic_review_failure on request_review_work; drop function synthetic_review_failure()');
        }
    }
}
