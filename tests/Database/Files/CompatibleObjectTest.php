<?php

namespace Tests\Database\Files;

use App\Access\Actions\RegisterCustomer;
use App\Files\CompatibleDesignObjects;
use App\Files\DesignObjectStores;
use App\Files\ExactDesignObjects;
use App\Files\ObjectUnavailable;
use App\Files\PrivateDesignFiles;
use App\Files\SubmissionObjects;
use App\Files\SyntheticDisposition;
use App\Files\UnusedDesignObjects;
use Aws\Command;
use Aws\MockHandler;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response as ProviderResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CompatibleObjects;
use Tests\Support\FaultyDesignObjects;
use Tests\Support\PostgresTestCase;
use Tests\Support\SyntheticDesigns;

class CompatibleObjectTest extends PostgresTestCase
{
    use CompatibleObjects, RefreshDatabase, SyntheticDesigns;

    protected function setUp(): void
    {
        $this->mockConsoleOutput = false;
        parent::setUp();
        $this->configureObjects();
    }

    protected function tearDown(): void
    {
        $this->removeSyntheticObjects();
        $this->removeDesignFixtures();
        File::deleteDirectory($this->privateTestRoot);
        parent::tearDown();
    }

    private function input(): array
    {
        $actor = app(RegisterCustomer::class)->register('Synthetic adapter customer', Str::uuid().'@example.test', '000', 'synthetic-password');

        return [$actor, ['operation_id' => (string) Str::uuid(), 'customer_id' => $actor->customers()->firstOrFail()->id,
            'material' => 'PETG', 'color' => 'white', 'quantity' => '2', 'notes' => null, 'files' => [$this->fixture($this->asciiStl())]]];
    }

    private function fault(string $mode): void
    {
        $this->app->bind(DesignObjectStores::class, fn () => new class($mode) extends DesignObjectStores
        {
            public function __construct(private string $mode) {}

            public function forAttempt(object $attempt): ExactDesignObjects
            {
                return new FaultyDesignObjects(parent::forAttempt($attempt), $this->mode);
            }
        });
    }

    public function test_real_private_conditional_objects_complete_owned_intake_and_stream_exact_attachment(): void
    {
        [$actor, $input] = $this->input();
        $response = $this->actingAs($actor)->post('/requests', $input)->assertStatus(303);
        DB::statement('set constraints all immediate');
        $attempt = DB::table('submission_attempts')->first();
        $file = DB::table('design_file_versions')->first();
        $this->assertSame('s3', $attempt->object_driver);
        $this->assertSame(hash('sha256', 'http://objectstore:8333'), $attempt->object_endpoint);
        $this->get($response->headers->get('Location'))->assertInertia(fn (AssertableInertia $page) => $page->where('request.status', 'SUBMITTED')->where('request.files.0.available', true)->missing('request.files.0.object_namespace')->missing('request.files.0.digest'));
        $download = $this->get('/requests/'.$file->request_id.'/files/'.$file->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($this->asciiStl(), $download->streamedContent());
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $store = app(DesignObjectStores::class)->forAttempt($attempt);
        $this->assertInstanceOf(CompatibleDesignObjects::class, $store);
        $anonymous = (new Client)->get($this->objectSettings['endpoint'].'/'.$this->objectSettings['bucket'].'/'.$store->key($attempt->id, $file->id), ['http_errors' => false]);
        $this->assertSame(403, $anonymous->getStatusCode());
        $this->assertDatabaseHas('submission_object_publications', ['attempt_id' => $attempt->id, 'outcome' => 'SUCCEEDED']);
    }

    public function test_conditional_creation_never_overwrites_same_identity_different_bytes_and_etag_is_not_digest(): void
    {
        $attempt = (string) Str::uuid();
        $file = ['id' => (string) Str::uuid(), 'size' => strlen($this->asciiStl()), 'digest' => hash('sha256', $this->asciiStl())];
        $store = new CompatibleDesignObjects($this->objectClient, $this->objectSettings['bucket'], $this->objectPrefix);
        $source = $this->fixture($this->asciiStl());
        $store->publish($attempt, $file, $source->getPathname());
        $store->publish($attempt, $file, $source->getPathname());
        $changed = $this->fixture(str_replace('synthetic', 'different', $this->asciiStl()));
        $other = [...$file, 'size' => $changed->getSize(), 'digest' => hash_file('sha256', $changed->getPathname())];
        try {
            $store->publish($attempt, $other, $changed->getPathname());
            $this->fail('A competing different object cannot replace exact bytes.');
        } catch (ObjectUnavailable $exception) {
            $this->assertSame('CORRUPT', $exception->outcome);
        }
        $read = $store->read($attempt, $file);
        $this->assertSame($this->asciiStl(), stream_get_contents($read->stream()));
        $read->close();
        $head = $this->objectClient->headObject(['Bucket' => $this->objectSettings['bucket'], 'Key' => $store->key($attempt, $file['id'])]);
        $this->assertNotSame($file['digest'], trim($head['ETag'], '"'));
    }

    public function test_failed_write_and_lost_publication_response_leave_durable_truth_then_same_intent_reconciliation_recovers(): void
    {
        [$actor, $input] = $this->input();
        $this->fault('before-write');
        $this->actingAs($actor)->post('/requests', $input)->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('submission_attempts', 1);
        $this->assertDatabaseCount('print_requests', 0);
        $this->assertDatabaseHas('submission_object_publications', ['outcome' => 'REJECTED']);
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertSessionHasErrors('submission');
        $this->fault('after-write');
        $this->post('/requests', $input)->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('print_requests', 0);
        $this->assertDatabaseHas('submission_object_publications', ['outcome' => 'UNKNOWN']);
        $this->get('/requests/attempts/'.$input['operation_id'])->assertInertia(fn (AssertableInertia $page) => $page->where('attemptStatus', 'UNRESOLVED')->where('reconcileUrl', url('/requests/attempts/'.$input['operation_id'].'/reconcile')));
        $this->app->bind(DesignObjectStores::class, fn () => new DesignObjectStores);
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertStatus(303);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
        $retry = $this->post('/requests', $input)->assertStatus(303);
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertRedirect($retry->headers->get('Location'));
        $this->assertDatabaseCount('print_requests', 1);
    }

    public function test_real_publication_sql_rollback_and_interrupted_reconciliation_preserve_one_recoverable_attempt(): void
    {
        [$actor, $input] = $this->input();
        DB::unprepared("create function synthetic_object_sql_failure() returns trigger language plpgsql as \$\$ begin raise exception 'synthetic'; end; \$\$; create trigger synthetic_object_sql_failure before insert on print_requests for each row execute function synthetic_object_sql_failure()");
        $this->actingAs($actor)->post('/requests', $input)->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('print_requests', 0);
        $this->assertDatabaseCount('design_file_versions', 0);
        $this->assertDatabaseHas('submission_object_publications', ['outcome' => 'SUCCEEDED']);
        $this->fault('read-unknown');
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('print_requests', 0);
        $this->app->bind(DesignObjectStores::class, fn () => new DesignObjectStores);
        DB::unprepared('drop trigger synthetic_object_sql_failure on print_requests; drop function synthetic_object_sql_failure()');
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertStatus(303);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('submission_attempts', 1);
    }

    public function test_confirmed_missing_corrupt_and_provider_unknown_objects_never_become_pending_or_serve_replacements(): void
    {
        [$actor, $input] = $this->input();
        $response = $this->actingAs($actor)->post('/requests', $input)->assertStatus(303);
        $attempt = DB::table('submission_attempts')->first();
        $file = DB::table('design_file_versions')->first();
        $store = app(DesignObjectStores::class)->forAttempt($attempt);
        $this->assertInstanceOf(CompatibleDesignObjects::class, $store);
        $url = '/requests/'.$file->request_id.'/files/'.$file->id;
        $this->fault('read-unknown');
        $this->get($url)->assertStatus(503)->assertDontSee('objectstore');
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertRedirect($response->headers->get('Location'));
        $this->app->bind(DesignObjectStores::class, fn () => new DesignObjectStores);
        $this->objectClient->putObject(['Bucket' => $this->objectSettings['bucket'], 'Key' => $store->key($attempt->id, $file->id), 'Body' => str_repeat('x', $file->size_bytes)]);
        $this->get($url)->assertStatus(410);
        $this->get($response->headers->get('Location'))->assertInertia(fn (AssertableInertia $page) => $page->where('request.status', 'SUBMITTED')->where('request.files.0.available', false));
        $this->objectClient->deleteObject(['Bucket' => $this->objectSettings['bucket'], 'Key' => $store->key($attempt->id, $file->id)]);
        $this->get($url)->assertStatus(410);
        $this->get('/requests/attempts/'.$input['operation_id'])->assertRedirect($response->headers->get('Location'));
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
    }

    public function test_foreign_revoked_and_anonymous_actors_cannot_read_or_reconcile_private_owned_attempts(): void
    {
        [$a, $input] = $this->input();
        [$b] = $this->input();
        $this->actingAs($a)->post('/requests', $input)->assertStatus(303);
        $file = DB::table('design_file_versions')->first();
        $url = '/requests/'.$file->request_id.'/files/'.$file->id;
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($b)->get($url)->assertNotFound();
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertNotFound();
        DB::table('customer_user')->where('user_id', $a->id)->update(['revoked_at' => now()]);
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($a)->get($url)->assertNotFound();
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertNotFound();
        $this->post('/logout')->assertRedirect();
        $this->get($url)->assertRedirect('/login');
    }

    public function test_storage_denial_and_nonexistent_bucket_are_truthfully_distinct_from_exact_absence(): void
    {
        $settings = [...$this->objectSettings, 'key' => getenv('CETAKIN_OBJECT_TEST_DENIED_KEY'), 'secret' => getenv('CETAKIN_OBJECT_TEST_DENIED_SECRET')];
        $client = app(DesignObjectStores::class)->client($settings);
        $store = new CompatibleDesignObjects($client, $settings['bucket'], $this->objectPrefix);
        $attempt = (string) Str::uuid();
        $source = $this->fixture($this->asciiStl());
        $file = ['id' => (string) Str::uuid(), 'size' => $source->getSize(), 'digest' => hash_file('sha256', $source->getPathname())];
        try {
            $store->publish($attempt, $file, $source->getPathname());
            $this->fail('Real readonly credentials must deny PutObject.');
        } catch (ObjectUnavailable $exception) {
            $this->assertSame('REJECTED', $exception->outcome);
        }
        $allowed = new CompatibleDesignObjects($this->objectClient, $settings['bucket'], $this->objectPrefix);
        $this->assertTrue($allowed->absent($attempt, $file['id']));
        $wrong = new CompatibleDesignObjects($this->objectClient, 'cetakin-test-missing-'.Str::uuid(), $this->objectPrefix);
        try {
            $wrong->absent($attempt, $file['id']);
            $this->fail('NoSuchBucket must not certify deletion.');
        } catch (ObjectUnavailable $exception) {
            $this->assertSame('UNKNOWN', $exception->outcome);
        }
    }

    public function test_store_endpoint_change_and_default_switch_do_not_reinterpret_exact_history(): void
    {
        [$actor, $input] = $this->input();
        $response = $this->actingAs($actor)->post('/requests', $input)->assertStatus(303);
        config(['filesystems.design_objects.driver' => 'local']);
        $this->get($response->headers->get('Location'))->assertInertia(fn (AssertableInertia $page) => $page->where('request.files.0.available', true));
        config(['filesystems.design_objects.s3.endpoint' => 'http://different-synthetic-endpoint:8333']);
        $file = DB::table('design_file_versions')->first();
        $this->get('/requests/'.$file->request_id.'/files/'.$file->id)->assertStatus(503);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertFileDoesNotExist(app(PrivateDesignFiles::class)->path($file->attempt_id, $file->id));
    }

    public function test_positive_synthetic_unused_claim_blocks_association_and_verifies_interrupted_deletion_on_retry(): void
    {
        [$actor, $input] = $this->input();
        DB::unprepared("create function synthetic_object_sql_failure() returns trigger language plpgsql as \$\$ begin raise exception 'synthetic'; end; \$\$; create trigger synthetic_object_sql_failure before insert on print_requests for each row execute function synthetic_object_sql_failure()");
        $this->actingAs($actor)->post('/requests', $input)->assertSessionHasErrors('submission');
        DB::unprepared('drop trigger synthetic_object_sql_failure on print_requests; drop function synthetic_object_sql_failure()');
        $attempt = DB::table('submission_attempts')->first();
        $permit = SyntheticDisposition::authorize($attempt, 'Synthetic test fixture owner', 'Explicit disposable isolated fixture, not retention');
        $unused = app(UnusedDesignObjects::class);
        $unused->classify($permit);
        $claim = $unused->claim($permit);
        $this->assertSame($claim, $unused->claim($permit));
        $this->post('/requests/attempts/'.$input['operation_id'].'/reconcile')->assertSessionHasErrors('submission');
        $this->post('/requests', $input)->assertSessionHasErrors('submission');
        $this->fault('after-delete');
        try {
            $unused->remove($permit, $claim);
            $this->fail('Lost deletion response cannot become verified deletion.');
        } catch (ObjectUnavailable $exception) {
            $this->assertSame('UNKNOWN', $exception->outcome);
        }
        $this->assertDatabaseHas('submission_object_guards', ['attempt_id' => $attempt->id, 'state' => 'DELETE_CLAIMED', 'deleted_at' => null]);
        $this->app->bind(DesignObjectStores::class, fn () => new DesignObjectStores);
        $unused->remove($permit, $claim);
        $this->assertDatabaseHas('submission_object_guards', ['attempt_id' => $attempt->id, 'state' => 'DELETED']);
        $this->assertDatabaseCount('print_requests', 0);
    }

    public function test_confirmed_inflight_and_ambiguous_publications_cannot_be_classified_unused(): void
    {
        foreach (['confirmed', 'IN_FLIGHT', 'UNKNOWN'] as $kind) {
            [$actor, $input] = $this->input();
            $this->app['session']->forget('password_hash_web');
            if ($kind !== 'confirmed') {
                $this->fault('before-write');
            }
            $response = $this->actingAs($actor)->post('/requests', $input);
            $kind === 'confirmed' ? $response->assertStatus(303) : $response->assertSessionHasErrors('submission');
            $attempt = DB::table('submission_attempts')->where('operation_id', $input['operation_id'])->first();
            if ($kind !== 'confirmed') {
                $token = app(SubmissionObjects::class)->begin($attempt->id);
                if ($kind === 'UNKNOWN') {
                    app(SubmissionObjects::class)->settle($token, 'UNKNOWN');
                }
            }
            try {
                DB::transaction(fn () => app(UnusedDesignObjects::class)->classify(SyntheticDisposition::authorize($attempt, 'Synthetic fixture owner', 'Must remain protected')));
                $this->fail('Uncertain/in-flight/confirmed content cannot be unused.');
            } catch (\PDOException $exception) {
                $this->assertSame('23514', (string) $exception->getCode());
            }
            $this->assertDatabaseHas('submission_object_guards', ['attempt_id' => $attempt->id, 'state' => 'OPEN']);
            $this->app->bind(DesignObjectStores::class, fn () => new DesignObjectStores);
        }
    }

    public function test_deterministic_sdk_http_503_errors_are_unknown_not_missing_corrupt_or_successful(): void
    {
        $source = $this->fixture($this->asciiStl());
        $attempt = (string) Str::uuid();
        $file = ['id' => (string) Str::uuid(), 'size' => $source->getSize(), 'digest' => hash_file('sha256', $source->getPathname())];
        foreach (['publish', 'read', 'absent', 'remove'] as $operation) {
            $handler = new MockHandler([new S3Exception('Synthetic provider transient', new Command('PutObject'), ['response' => new ProviderResponse(503), 'code' => 'ServiceUnavailable'])]);
            $client = new S3Client(['region' => 'us-east-1', 'version' => '2006-03-01', 'credentials' => ['key' => 'synthetic', 'secret' => 'synthetic'], 'handler' => $handler, 'retries' => 0]);
            $store = new CompatibleDesignObjects($client, $this->objectSettings['bucket'], $this->objectPrefix);
            try {
                match ($operation) {
                    'publish' => $store->publish($attempt, $file, $source->getPathname()),
                    'read' => $store->read($attempt, $file),
                    'absent' => $store->absent($attempt, $file['id']),
                    'remove' => $store->remove($attempt, $file['id']),
                };
                $this->fail('An explicit provider HTTP503 cannot be acknowledged as success/absence.');
            } catch (ObjectUnavailable $exception) {
                $this->assertSame('UNKNOWN', $exception->outcome);
            }
        }
    }

    public function test_failed_distinct_attempt_keeps_prior_confirmed_object_intact_then_recovers_once_and_rejects_changed_payload(): void
    {
        [$actor, $firstInput] = $this->input();
        $this->actingAs($actor)->post('/requests', $firstInput)->assertStatus(303);
        $firstFile = DB::table('design_file_versions')->first();
        $firstAttempt = DB::table('submission_attempts')->first();
        $beforeVersion = (array) $firstFile;
        $beforeRequest = (array) DB::table('print_requests')->first();
        $next = [...$firstInput, 'operation_id' => (string) Str::uuid(), 'quantity' => '3', 'files' => [$this->fixture($this->binaryStl(), 'second.stl')]];
        DB::unprepared("create function synthetic_object_sql_failure() returns trigger language plpgsql as \$\$ begin raise exception 'synthetic'; end; \$\$; create trigger synthetic_object_sql_failure before insert on print_requests for each row execute function synthetic_object_sql_failure()");
        $this->post('/requests', $next)->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('submission_attempts', 2);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
        $this->assertSame($beforeVersion, (array) DB::table('design_file_versions')->where('id', $firstFile->id)->first());
        $this->assertSame($beforeRequest, (array) DB::table('print_requests')->where('id', $firstFile->request_id)->first());
        $download = $this->get('/requests/'.$firstFile->request_id.'/files/'.$firstFile->id)->assertOk();
        $this->assertSame($this->asciiStl(), $download->streamedContent());
        $read = app(DesignObjectStores::class)->forAttempt($firstAttempt)->read($firstAttempt->id, ['id' => $firstFile->id, 'size' => $firstFile->size_bytes, 'digest' => $firstFile->digest]);
        $this->assertSame($firstFile->digest, hash('sha256', stream_get_contents($read->stream())));
        $read->close();
        DB::unprepared('drop trigger synthetic_object_sql_failure on print_requests; drop function synthetic_object_sql_failure()');
        $confirmed = $this->post('/requests/attempts/'.$next['operation_id'].'/reconcile')->assertStatus(303);
        $this->post('/requests', $next)->assertRedirect($confirmed->headers->get('Location'));
        $this->post('/requests', [...$next, 'quantity' => '4'])->assertSessionHasErrors('operation_id');
        $this->post('/requests', [...$next, 'files' => [$this->fixture($this->asciiStl(), 'changed.stl')]])->assertSessionHasErrors('operation_id');
        $this->assertDatabaseCount('print_requests', 2);
        $this->assertDatabaseCount('design_file_versions', 2);
        $second = DB::table('design_file_versions')->where('id', '<>', $firstFile->id)->first();
        $this->assertSame($this->binaryStl(), $this->get('/requests/'.$second->request_id.'/files/'.$second->id)->assertOk()->streamedContent());
        $this->assertSame($beforeVersion, (array) DB::table('design_file_versions')->where('id', $firstFile->id)->first());
    }

    public function test_synthetic_disposition_is_denied_outside_exact_test_bucket_prefix_and_environment(): void
    {
        [$actor, $input] = $this->input();
        $this->fault('before-write');
        $this->actingAs($actor)->post('/requests', $input)->assertSessionHasErrors('submission');
        $attempt = DB::table('submission_attempts')->first();
        foreach (['scope', 'environment'] as $case) {
            $candidate = clone $attempt;
            if ($case === 'scope') {
                $candidate->object_namespace = $this->objectSettings['bucket'].'/designs';
            } else {
                $this->app['env'] = 'local';
            }
            try {
                SyntheticDisposition::authorize($candidate, 'test', 'test');
                $this->fail('An environment flag cannot authorize arbitrary operational deletion.');
            } catch (ObjectUnavailable $exception) {
                $this->assertSame('REJECTED', $exception->outcome);
            } finally {
                $this->app['env'] = 'testing';
            }
        }
    }
}
