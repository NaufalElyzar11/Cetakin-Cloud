<?php

namespace Tests\Database\Intake;

use App\Access\Actions\RegisterCustomer;
use App\Access\Actions\StaffAccess;
use App\Access\Models\Customer;
use App\Access\Models\User;
use App\Files\PrivateDesignFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\Support\PostgresTestCase;
use Tests\Support\SyntheticDesigns;

class OwnedSubmissionTest extends PostgresTestCase
{
    use RefreshDatabase, SyntheticDesigns;

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

    private function customer(string $email = 'synthetic-a@example.test'): User
    {
        return app(RegisterCustomer::class)->register('Synthetic customer', $email, '+62 800 000', 'synthetic-password');
    }

    private function submission(User $user, ?string $operation = null): array
    {
        return ['operation_id' => $operation ?? (string) Str::uuid(), 'customer_id' => $user->customers()->firstOrFail()->id,
            'material' => 'PETG', 'color' => 'sky blue', 'quantity' => '3', 'notes' => null, 'files' => [$this->fixture($this->asciiStl())]];
    }

    public function test_owned_multi_file_set_is_committed_with_exact_versions_private_bytes_and_truthful_confirmation(): void
    {
        $actor = $this->customer();
        $input = $this->submission($actor);
        $input['files'][] = $this->zipFixture($this->packageParts());
        $response = $this->actingAs($actor)->post('/requests', $input)->assertStatus(303);
        DB::statement('set constraints all immediate');
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 2);
        $print = DB::table('print_requests')->first();
        $this->assertSame(3, $print->quantity); // Three complete sets, not six products.
        $this->assertSame('SUBMITTED', $print->status);
        $this->assertSame($actor->id, $print->submitted_by);
        $this->assertDatabaseHas('submission_attempts', ['actor_id' => $actor->id, 'request_id' => $print->id]);
        $versions = DB::table('design_file_versions')->orderBy('position')->get();
        foreach ($versions as $position => $version) {
            $path = app(PrivateDesignFiles::class)->path($version->attempt_id, $version->id);
            $this->assertStringStartsWith($this->privateTestRoot, $path);
            $this->assertSame(file_get_contents($input['files'][$position]->getPathname()), file_get_contents($path));
            $this->assertSame(hash_file('sha256', $path), $version->digest);
            $this->assertNotSame($version->original_name, basename($path));
        }
        $this->get($response->headers->get('Location'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Intake/Show')->where('request.quantity', '3')->where('request.status', 'SUBMITTED')->has('request.files', 2)->where('request.files.0.available', true)->missing('request.files.0.digest')->missing('request.files.0.attempt_id')->missing('request.submitted_by'));
    }

    public function test_required_specification_file_selection_and_forged_provenance_fail_without_an_intent_or_request(): void
    {
        $actor = $this->customer();
        $this->actingAs($actor)->post('/requests', [...$this->submission($actor), 'material' => 'PLA', 'color' => 'red', 'quantity' => '0', 'files' => [], 'submitted_by' => 999, 'status' => 'APPROVED'])
            ->assertSessionHasErrors(['material', 'color', 'files', 'submitted_by', 'status']);
        $this->post('/requests', [...$this->submission($actor), 'quantity' => '1.5'])->assertSessionHasErrors('quantity');
        $this->post('/requests', [...$this->submission($actor), 'quantity' => '9223372036854775808'])->assertSessionHasErrors('quantity');
        $this->post('/requests', [...$this->submission($actor), 'files' => array_fill(0, 6, $this->fixture($this->asciiStl()))])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('submission_attempts', 0);
        $this->assertDatabaseCount('print_requests', 0);
    }

    public function test_customer_selection_is_explicit_supports_multiple_relationships_and_never_uses_foreign_or_revoked_owner(): void
    {
        $actor = $this->customer();
        $other = $this->customer('synthetic-b@example.test');
        $second = Customer::create(['name' => 'Second synthetic profile', 'email' => 'second@example.test', 'phone' => '000']);
        $actor->customers()->attach($second);
        $input = $this->submission($actor);
        $create = $this->actingAs($actor)->get('/requests/create')->assertRedirect();
        $this->get($create->headers->get('Location'))->assertInertia(fn (AssertableInertia $page) => $page->has('customers', 2)->missing('customers.0.email')->where('customers.1.id', $second->id));
        $this->post('/requests', [...$input, 'customer_id' => $second->id])->assertStatus(303);
        $this->assertDatabaseHas('print_requests', ['customer_id' => $second->id, 'submitted_by' => $actor->id]);
        $this->post('/requests', [...$this->submission($actor), 'customer_id' => $other->customers()->firstOrFail()->id])->assertNotFound();
        DB::table('customer_user')->where('user_id', $actor->id)->where('customer_id', $second->id)->update(['revoked_at' => now()]);
        $this->post('/requests', [...$this->submission($actor), 'customer_id' => $second->id])->assertNotFound();
        $this->assertDatabaseCount('print_requests', 1);
    }

    public function test_own_lists_details_and_attachments_deny_foreign_metadata_bytes_and_owner_role_bypass(): void
    {
        $a = $this->customer();
        $b = $this->customer('synthetic-b@example.test');
        $this->actingAs($b)->post('/requests', $this->submission($b))->assertStatus(303);
        $foreign = DB::table('print_requests')->first();
        $file = DB::table('design_file_versions')->first();
        app(StaffAccess::class)->bootstrap($a->id, 'Synthetic offline authority', 'Owner and customer overlap');
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($a)->get('/requests')->assertInertia(fn (AssertableInertia $page) => $page->has('requests', 0)->has('customers', 1));
        $this->get('/requests/'.$foreign->id)->assertNotFound()->assertDontSee($file->original_name);
        $this->get('/requests/'.$foreign->id.'/files/'.$file->id)->assertNotFound();
        $this->get('/requests/not-a-uuid')->assertNotFound();
        $this->get('/requests/'.$foreign->id.'/files/forged')->assertNotFound();
        $this->get('/requests/attempts/forged')->assertNotFound();
        $this->app['session']->forget('password_hash_web');
        $download = $this->actingAs($b)->get('/requests/'.$foreign->id.'/files/'.$file->id)->assertOk()->assertHeader('Content-Type', 'application/octet-stream')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        DB::table('customer_user')->where('user_id', $b->id)->update(['revoked_at' => now()]);
        $this->get('/requests/'.$foreign->id)->assertNotFound();
        $this->get('/requests/'.$foreign->id.'/files/'.$file->id)->assertNotFound();
    }

    public function test_same_attempt_payload_retry_returns_one_result_and_changed_payload_conflicts_but_new_attempt_is_allowed(): void
    {
        $actor = $this->customer();
        $input = $this->submission($actor);
        $first = $this->actingAs($actor)->post('/requests', $input)->assertStatus(303);
        $retry = $this->post('/requests', $input)->assertStatus(303);
        $this->assertSame($first->headers->get('Location'), $retry->headers->get('Location'));
        $this->post('/requests', [...$input, 'quantity' => '4'])->assertSessionHasErrors('operation_id');
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
        $this->post('/requests', [...$input, 'operation_id' => (string) Str::uuid()])->assertStatus(303);
        $this->assertDatabaseCount('print_requests', 2);
        $this->assertDatabaseCount('design_file_versions', 2);
        $this->assertCount(2, DB::table('design_file_versions')->pluck('id')->unique());
    }

    public function test_missing_or_changed_exact_file_is_truthfully_unavailable_without_erasing_confirmed_provenance(): void
    {
        $actor = $this->customer();
        $this->actingAs($actor)->post('/requests', $this->submission($actor))->assertStatus(303);
        $file = DB::table('design_file_versions')->first();
        $path = app(PrivateDesignFiles::class)->path($file->attempt_id, $file->id);
        file_put_contents($path, 'Synthetic tampering');
        $this->get('/requests/'.$file->request_id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.files.0.available', false)->where('request.status', 'SUBMITTED'));
        $this->get('/requests/'.$file->request_id.'/files/'.$file->id)->assertStatus(410)->assertDontSee($this->privateTestRoot);
        unlink($path);
        $this->get('/requests/'.$file->request_id.'/files/'.$file->id)->assertStatus(410);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseCount('design_file_versions', 1);
    }

    public function test_private_write_failure_and_published_bytes_before_sql_failure_remain_unconfirmed_and_retryable(): void
    {
        $actor = $this->customer();
        $input = $this->submission($actor);
        $this->app->bind(PrivateDesignFiles::class, fn () => new class extends PrivateDesignFiles
        {
            public function publish(string $attempt, array $file, string $source): void
            {
                throw new RuntimeException('Synthetic local write failure with sensitive/path');
            }
        });
        $this->actingAs($actor)->post('/requests', $input)->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('submission_attempts', 1);
        $this->assertDatabaseCount('print_requests', 0);
        $this->get('/requests/attempts/'.$input['operation_id'])->assertInertia(fn (AssertableInertia $page) => $page->component('Intake/Create')->where('attemptStatus', 'UNRESOLVED'));
        $this->app->bind(PrivateDesignFiles::class, fn () => new PrivateDesignFiles);
        DB::unprepared("create function synthetic_fail_association() returns trigger language plpgsql as \$\$ begin raise exception 'Synthetic failed SQL association'; end; \$\$; create trigger synthetic_fail_association before insert on print_requests for each row execute function synthetic_fail_association()");
        $this->post('/requests', $input)->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('print_requests', 0);
        $this->assertDatabaseCount('design_file_versions', 0);
        $attempt = DB::table('submission_attempts')->first();
        $manifest = json_decode($attempt->manifest, true);
        $path = app(PrivateDesignFiles::class)->path($attempt->id, $manifest[0]['id']);
        $this->assertFileExists($path);
        $this->assertSame($manifest[0]['digest'], hash_file('sha256', $path));
        DB::unprepared('drop trigger synthetic_fail_association on print_requests; drop function synthetic_fail_association()');
        $this->post('/requests', $input)->assertStatus(303);
        $this->assertDatabaseCount('print_requests', 1);
        $this->assertDatabaseHas('design_file_versions', ['id' => $manifest[0]['id']]);
        $this->assertSame($manifest[0]['digest'], hash_file('sha256', $path));
        $this->get('/requests/attempts/'.$input['operation_id'])->assertRedirect('/requests/'.DB::table('print_requests')->value('id'));
    }

    public function test_revocation_during_private_io_denies_confirmation_without_false_business_success(): void
    {
        $actor = $this->customer();
        $customer = $actor->customers()->firstOrFail();
        $this->app->bind(PrivateDesignFiles::class, fn () => new class($actor->id) extends PrivateDesignFiles
        {
            public function __construct(private int $actor) {}

            public function publish(string $attempt, array $file, string $source): void
            {
                parent::publish($attempt, $file, $source);
                DB::table('customer_user')->where('user_id', $this->actor)->update(['revoked_at' => now()]);
            }
        });
        $input = $this->submission($actor);
        $this->actingAs($actor)->post('/requests', $input)->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('print_requests', 0);
        $this->assertDatabaseCount('submission_attempts', 1);
        $this->get('/requests/attempts/'.$input['operation_id'])->assertNotFound();
        $this->assertDatabaseHas('customer_user', ['user_id' => $actor->id, 'customer_id' => $customer->id]);
    }

    public function test_failed_new_submission_cannot_damage_existing_confirmed_request_or_files(): void
    {
        $actor = $this->customer();
        $input = $this->submission($actor);
        $this->actingAs($actor)->post('/requests', $input)->assertStatus(303);
        $before = (array) DB::table('print_requests')->first();
        $file = DB::table('design_file_versions')->first();
        $digest = hash_file('sha256', app(PrivateDesignFiles::class)->path($file->attempt_id, $file->id));
        $this->post('/requests', [...$this->submission($actor), 'files' => [$this->fixture('invalid synthetic content')]])->assertSessionHasErrors('files.0');
        $this->assertSame($before, (array) DB::table('print_requests')->first());
        $this->assertSame($digest, hash_file('sha256', app(PrivateDesignFiles::class)->path($file->attempt_id, $file->id)));
    }

    public function test_actual_local_publication_never_overwrites_an_existing_conflicting_exact_version(): void
    {
        $storage = app(PrivateDesignFiles::class);
        $file = $this->fixture($this->asciiStl());
        $attempt = (string) Str::uuid();
        $descriptor = ['id' => (string) Str::uuid(), 'size' => filesize($file->getPathname()), 'digest' => hash_file('sha256', $file->getPathname())];
        $storage->publish($attempt, $descriptor, $file->getPathname());
        $storage->publish($attempt, $descriptor, $file->getPathname());
        $path = $storage->path($attempt, $descriptor['id']);
        file_put_contents($path, 'Synthetic conflicting prior bytes');
        try {
            $storage->publish($attempt, $descriptor, $file->getPathname());
            $this->fail('A conflicting generated identity cannot be overwritten.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic conflicting prior bytes', file_get_contents($path));
        }
    }

    public function test_real_75_mib_combined_boundary_and_five_selected_files_are_allowed_without_a_business_quantity_cap(): void
    {
        $actor = $this->customer();
        $input = $this->submission($actor);
        $input['quantity'] = '9223372036854775807';
        $input['files'] = [
            $this->fixture($this->asciiStl(), 'one.stl', 26_214_400),
            $this->fixture($this->asciiStl(), 'two.stl', 26_214_400),
            $this->fixture($this->asciiStl(), 'three.stl', 26_214_400),
        ];
        $this->actingAs($actor)->post('/requests', $input)->assertStatus(303);
        DB::statement('set constraints all immediate');
        $this->assertSame(78_643_200, DB::table('design_file_versions')->sum('size_bytes'));
        $this->assertSame('9223372036854775807', (string) DB::table('print_requests')->value('quantity'));
        DB::statement('set constraints all deferred');
        $over = [$input['files'][0], $input['files'][1],
            $this->fixture($this->asciiStl(), 'adjusted.stl', 26_214_400 - strlen($this->asciiStl()) + 1),
            $this->fixture($this->asciiStl(), 'four.stl')];
        $this->assertSame(78_643_201, array_sum(array_map(fn ($file) => filesize($file->getPathname()), $over)));
        $this->post('/requests', [...$input, 'operation_id' => (string) Str::uuid(), 'files' => $over])->assertSessionHasErrors('files');
        $small = $this->submission($actor);
        $small['files'] = [];
        for ($i = 0; $i < 5; $i++) {
            $small['files'][] = $this->fixture($this->asciiStl(), 'component'.$i.'.stl');
        }
        $this->post('/requests', $small)->assertStatus(303);
        $this->assertDatabaseCount('print_requests', 2);
        $this->assertDatabaseCount('design_file_versions', 8);
    }

    public function test_guest_and_real_csrf_middleware_deny_state_changes_without_weakening_authentication(): void
    {
        $this->get('/requests')->assertRedirect('/login');
        $this->post('/requests', [])->assertRedirect('/login');
        $actor = $this->customer();
        $input = $this->submission($actor);
        // Guarded DB targeting was established while testing. Exercise the real
        // middleware branch exactly as CET-004, without its testing bypass.
        $this->app->instance('env', 'production');
        $this->actingAs($actor)->get('/requests/attempts/'.$input['operation_id'])->assertOk();
        $token = $this->app['session']->token();
        $this->post('/requests', [...$input, '_token' => 'invalid'], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
        $this->assertDatabaseCount('submission_attempts', 0);
        $this->post('/requests', [...$input, '_token' => $token])->assertStatus(303);
        $this->assertDatabaseCount('print_requests', 1);
    }
}
