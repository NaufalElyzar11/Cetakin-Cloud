<?php

namespace Tests\Database\Access;

use App\Access\Actions\RegisterCustomer;
use App\Access\Actions\StaffAccess;
use App\Access\Models\User;
use Closure;
use Illuminate\Auth\Events\Validated;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use ReflectionFunction;
use SensitiveParameterValue;
use Tests\Support\PostgresTestCase;

class StaffResponsibilitiesTest extends PostgresTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->mockConsoleOutput = false;
        parent::setUp();
        config(['session.driver' => 'database', 'cache.default' => 'database']);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    private function user(string $email): User
    {
        return app(RegisterCustomer::class)->register('Synthetic User', $email, '+62 800 000', 'old-password');
    }

    private function owner(): User
    {
        $owner = $this->user('owner@example.test');
        app(StaffAccess::class)->bootstrap($owner->id, 'Synthetic trusted offline source', 'Initial Owner test');

        return $owner;
    }

    private function evidence(string $procedure = 'ordinary-account-v1'): array
    {
        return ['procedure' => $procedure, 'source' => 'Synthetic trusted executor', 'account' => 'Synthetic account-level procedure A', 'privileged' => 'Synthetic separate staff procedure B', 'infrastructure' => 'Synthetic infrastructure approval C', 'business' => 'Synthetic business approval D', 'reason' => 'Synthetic recovery exercise'];
    }

    private function rejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected authorization rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function freshRequest(?string $sessionId = null): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance('redirect');
        $this->defaultCookies = [];
        if ($sessionId !== null) {
            $this->withCookie(config('session.cookie'), $sessionId);
        }
    }

    public function test_offline_bootstrap_records_real_source_without_fabricated_admin_and_cannot_be_reused(): void
    {
        $owner = $this->owner();
        $other = $this->user('other@example.test');
        $this->assertDatabaseHas('access_control', ['id' => 1, 'initial_owner_user_id' => $owner->id]);
        $this->assertDatabaseHas('staff_responsibility_grants', ['user_id' => $owner->id, 'granted_by' => null, 'authorization_source' => 'Synthetic trusted offline source']);
        $this->rejected(fn () => app(StaffAccess::class)->bootstrap($other->id, 'Second offline source', 'Reuse attempt'));
        $this->assertFalse($other->hasResponsibility('owner'));
        $this->get('/access/bootstrap-owner')->assertNotFound();
        $this->post('/staff/bootstrap-owner')->assertNotFound();
        $this->assertSame(1, $this->artisan('access:bootstrap-owner', ['user' => $other->id, '--source' => 'Reuse source', '--reason' => 'Reuse']));
    }

    public function test_only_current_owner_manages_roles_and_customer_operator_cannot_promote_themselves(): void
    {
        $owner = $this->owner();
        $customer = $this->user('customer@example.test');
        $this->actingAs($customer)->get('/staff')->assertForbidden();
        $this->post('/staff/responsibilities', ['user_id' => $customer->id, 'responsibility' => 'owner', 'reason' => 'Self promotion'])->assertForbidden();
        $this->freshRequest();
        $this->actingAs($owner)->post('/staff/responsibilities', ['user_id' => $customer->id, 'responsibility' => 'operator', 'reason' => 'Assigned operator responsibility'])->assertRedirect('/staff');
        $this->assertTrue($customer->hasResponsibility('operator'));
        $this->assertFalse($customer->hasResponsibility('owner'));
        $this->freshRequest();
        $this->actingAs($customer)->get('/staff')->assertForbidden();
        $this->rejected(fn () => app(StaffAccess::class)->grant($customer->id, $customer->id, 'owner', 'Self promotion'));
        $this->freshRequest();
        $this->actingAs($owner)->post('/staff/responsibilities', ['user_id' => $customer->id, 'responsibility' => 'owner', 'reason' => 'Forged provenance', 'acting_user_id' => $customer->id])->assertSessionHasErrors('acting_user_id');
        $this->assertFalse($customer->hasResponsibility('owner'));
    }

    public function test_multiple_responsibilities_overlap_customer_without_cross_customer_access(): void
    {
        $owner = $this->owner();
        $other = $this->user('other@example.test');
        app(StaffAccess::class)->grant($owner->id, $owner->id, 'operator', 'Owner also operates');
        $this->assertTrue($owner->hasResponsibility('owner'));
        $this->assertTrue($owner->hasResponsibility('operator'));
        $ownCustomer = $owner->customers()->firstOrFail();
        $otherCustomer = $other->customers()->firstOrFail();
        $this->actingAs($owner)->get('/account/customers/'.$ownCustomer->id)->assertOk();
        $this->get('/account/customers/'.$otherCustomer->id)->assertNotFound();
        $this->get('/account')->assertInertia(fn (AssertableInertia $page) => $page->has('customers', 1)->where('customers.0.id', $ownCustomer->id)->missing('grants')->missing('users'));
    }

    public function test_revoked_owner_old_page_and_cached_user_object_cannot_grant_or_read_management(): void
    {
        $owner = $this->owner();
        $otherOwner = $this->user('other-owner@example.test');
        $grant = app(StaffAccess::class)->grant($owner->id, $otherOwner->id, 'owner', 'Backup Owner');
        $this->actingAs($otherOwner)->get('/staff')->assertOk();
        app(StaffAccess::class)->revoke($owner->id, $grant, 'Remove responsibility');
        $this->assertFalse($otherOwner->hasResponsibility('owner'));
        $this->get('/staff')->assertForbidden();
        $this->post('/staff/responsibilities', ['user_id' => $otherOwner->id, 'responsibility' => 'owner', 'reason' => 'Stale page'])->assertForbidden();
        $this->assertTrue($owner->hasResponsibility('owner'));
        $this->assertNotNull(DB::table('staff_responsibility_grants')->where('id', $grant)->value('revoked_at'));
    }

    public function test_last_owner_is_protected_and_self_revocation_requires_another_active_owner(): void
    {
        $owner = $this->owner();
        $initial = $owner->currentResponsibilityGrantId('owner');
        $this->rejected(fn () => app(StaffAccess::class)->revoke($owner->id, $initial, 'Self revoke last Owner'));
        $this->assertTrue($owner->hasResponsibility('owner'));
        $other = $this->user('other@example.test');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Additional Owner');
        app(StaffAccess::class)->revoke($owner->id, $initial, 'Self revoke with coverage');
        $this->assertFalse($owner->hasResponsibility('owner'));
        $this->assertTrue($other->hasResponsibility('owner'));
    }

    public function test_operator_regrant_has_new_identity_and_never_restores_the_revoked_generation(): void
    {
        $owner = $this->owner();
        $operator = $this->user('operator@example.test');
        $prior = app(StaffAccess::class)->grant($owner->id, $operator->id, 'operator', 'First role grant');
        app(StaffAccess::class)->revoke($owner->id, $prior, 'Role revoked; later work scopes invalid');
        $this->assertNull($operator->currentResponsibilityGrantId('operator'));
        $current = app(StaffAccess::class)->grant($owner->id, $operator->id, 'operator', 'Explicit fresh responsibility grant');
        $this->assertNotSame($prior, $current);
        $this->assertSame($current, $operator->currentResponsibilityGrantId('operator'));
        $this->assertNotNull(DB::table('staff_responsibility_grants')->where('id', $prior)->value('revoked_at'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('tasks'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('work_authorizations'));
    }

    public function test_grant_history_and_provisioning_marker_cannot_be_rewritten(): void
    {
        $owner = $this->owner();
        foreach ([
            fn () => DB::table('staff_responsibility_grants')->update(['grant_reason' => 'Rewrite']),
            fn () => DB::table('staff_responsibility_grants')->delete(),
            fn () => DB::table('access_control')->update(['initial_owner_user_id' => null]),
            fn () => DB::table('staff_responsibility_grants')->where('responsibility', 'owner')->update(['revoked_at' => now(), 'revoked_by' => $owner->id, 'revoke_reason' => 'Direct last Owner removal']),
        ] as $operation) {
            try {
                DB::transaction($operation);
                $this->fail('Expected database integrity rejection.');
            } catch (QueryException) {
                $this->assertTrue($owner->hasResponsibility('owner'));
            }
        }
    }

    public function test_ordinary_recovery_preserves_identity_ownership_and_invalidates_all_old_sessions_including_late_rewrite(): void
    {
        $owner = $this->owner();
        $target = $this->user('target@example.test');
        $beforeRelationship = DB::table('customer_user')->where('user_id', $target->id)->first();
        $this->post('/login', ['email' => $target->email, 'password' => 'old-password'])->assertRedirect('/account');
        $sessionId = $this->app['session']->driver()->getId();
        $priorSession = (array) DB::table('sessions')->where('id', $sessionId)->first();
        DB::table('sessions')->insert([...$priorSession, 'id' => 'second-prior-session']);
        app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, 'new-password', $this->evidence());
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertSame((array) $beforeRelationship, (array) DB::table('customer_user')->where('user_id', $target->id)->first());
        $this->assertSame('target@example.test', $target->fresh()->email);
        $this->assertFalse(Hash::check('old-password', $target->fresh()->password));
        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
        $this->freshRequest($sessionId);
        $this->get('/account')->assertRedirect('/login');
        // A request/login that read the old credential before reset may persist
        // its old session after deletion. The framework hash stamp must reject it.
        DB::table('sessions')->where('id', $sessionId)->delete();
        DB::table('sessions')->insert($priorSession);
        $this->freshRequest($sessionId);
        $this->get('/account')->assertRedirect('/login');
        $this->freshRequest();
        $this->post('/login', ['email' => $target->email, 'password' => 'old-password'])->assertSessionHasErrors('email');
        $this->freshRequest();
        $this->post('/login', ['email' => $target->email, 'password' => 'new-password'])->assertRedirect('/account');
        $this->assertDatabaseHas('credential_recovery_history', ['user_id' => $target->id, 'acting_user_id' => $owner->id, 'procedure' => 'ordinary-account-v1', 'outcome' => 'completed']);
        try {
            DB::transaction(fn () => DB::table('credential_recovery_history')->update(['reason' => 'Rewrite']));
            $this->fail('Expected retained recovery evidence.');
        } catch (QueryException) {
            $this->assertDatabaseCount('credential_recovery_history', 1);
        }
    }

    public function test_recovery_denies_wrong_current_authority_credentials_and_historical_staff_ordinary_path(): void
    {
        $owner = $this->owner();
        $target = $this->user('target@example.test');
        $role = app(StaffAccess::class)->grant($owner->id, $target->id, 'operator', 'Historical staff');
        app(StaffAccess::class)->revoke($owner->id, $role, 'Revoke before recovery');
        foreach ([
            fn () => app(StaffAccess::class)->recover($target->id, 'old-password', $owner->id, 'new-password', $this->evidence()),
            fn () => app(StaffAccess::class)->recover($owner->id, 'wrong-password', $target->id, 'new-password', $this->evidence('privileged-account-v1')),
            fn () => app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, 'new-password', $this->evidence()),
            fn () => app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, 'new-password', [...$this->evidence('privileged-account-v1'), 'privileged' => '']),
        ] as $operation) {
            $this->rejected($operation);
        }
        $this->assertTrue(Hash::check('old-password', $target->fresh()->password));
        app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, 'new-password', $this->evidence('privileged-account-v1'));
        $this->assertFalse($target->hasResponsibility('operator'));
        $this->assertNotNull(DB::table('staff_responsibility_grants')->where('id', $role)->value('revoked_at'));
        $this->assertSame(4, DB::table('credential_recovery_history')->where('outcome', 'denied')->count());
        $this->get('/recovery')->assertNotFound();
        $this->post('/staff/recovery')->assertNotFound();
    }

    public function test_sole_owner_offline_recovery_requires_both_authorities_and_separate_verification_without_new_owner(): void
    {
        $owner = $this->owner();
        foreach (['infrastructure', 'business', 'privileged'] as $missing) {
            $this->rejected(fn () => app(StaffAccess::class)->recover(null, null, $owner->id, 'new-password', [...$this->evidence('sole-owner-offline-v1'), $missing => '']));
        }
        $this->assertTrue(Hash::check('old-password', $owner->fresh()->password));
        app(StaffAccess::class)->recover(null, null, $owner->id, 'new-password', $this->evidence('sole-owner-offline-v1'));
        $this->assertTrue(Hash::check('new-password', $owner->fresh()->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('staff_responsibility_grants', 1);
        $this->assertTrue($owner->hasResponsibility('owner'));
        $this->assertDatabaseHas('credential_recovery_history', ['acting_user_id' => null, 'user_id' => $owner->id, 'outcome' => 'completed', 'procedure' => 'sole-owner-offline-v1']);
        $this->assertSame(1, $this->artisan('access:recover-credentials', ['user' => $owner->id, '--mode' => 'sole-owner', '--no-interaction' => true]));
    }

    public function test_failed_session_invalidation_rolls_back_credentials_and_records_no_false_success(): void
    {
        $owner = $this->owner();
        $target = $this->user('target@example.test');
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        DB::unprepared("create function cetakin_fail_session_delete() returns trigger language plpgsql as $$ begin raise exception 'Synthetic session persistence failure'; end; $$; create trigger cetakin_fail_session before delete on sessions for each row execute function cetakin_fail_session_delete()");
        try {
            try {
                app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, 'new-password', $this->evidence());
                $this->fail('Expected recovery transaction failure.');
            } catch (QueryException) {
                $this->assertTrue(Hash::check('old-password', $target->fresh()->password));
                $this->assertDatabaseHas('sessions', ['id' => 'old-session']);
                $this->assertDatabaseMissing('credential_recovery_history', ['outcome' => 'completed']);
                $this->assertDatabaseHas('credential_recovery_history', ['user_id' => $target->id, 'outcome' => 'failed']);
            }
        } finally {
            DB::unprepared('drop trigger cetakin_fail_session on sessions; drop function cetakin_fail_session_delete()');
        }
    }

    public function test_login_validated_before_reset_cannot_establish_continued_access_after_reset(): void
    {
        $owner = $this->owner();
        $target = $this->user('target@example.test');
        $target->password = Hash::make('old-password', ['rounds' => 4]);
        $target->save();
        $this->assertTrue(Hash::needsRehash($target->password));
        // SessionGuard validates the old password, then a reset completes before
        // it stores the login. Its User snapshot carries the old hash stamp.
        Event::listen(Validated::class, function (Validated $event) use ($owner, $target): void {
            if ($event->user->getAuthIdentifier() === $target->id) {
                app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, 'new-password', $this->evidence());
            }
        });
        try {
            $this->post('/login', ['email' => $target->email, 'password' => 'old-password'])->assertRedirect('/account');
            $sessionId = $this->app['session']->driver()->getId();
            $this->freshRequest($sessionId);
            $this->get('/account')->assertRedirect('/login');
            $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
            $this->assertFalse(Hash::check('old-password', $target->fresh()->password));
        } finally {
            Event::forget(Validated::class);
        }
    }

    public function test_ordinary_login_still_upgrades_an_old_supported_password_hash(): void
    {
        $target = $this->user('target@example.test');
        $target->password = Hash::make('old-password', ['rounds' => 4]);
        $target->save();
        $priorHash = $target->password;
        $this->assertTrue(Hash::needsRehash($priorHash));
        $this->post('/login', ['email' => $target->email, 'password' => 'old-password'])->assertRedirect('/account');
        $this->assertNotSame($priorHash, $target->fresh()->password);
        $this->assertFalse(Hash::needsRehash($target->fresh()->password));
        $this->assertTrue(Hash::check('old-password', $target->fresh()->password));
        $this->freshRequest($this->app['session']->driver()->getId());
        $this->get('/account')->assertOk();
    }

    public function test_unicode_recovery_matches_registration_character_minimum_and_bcrypt_byte_limit(): void
    {
        $owner = $this->owner();
        $twoCharacters = '🙂🙃';
        $this->assertSame(8, strlen($twoCharacters));
        $this->assertSame(2, mb_strlen($twoCharacters));
        $input = ['name' => 'Synthetic Unicode customer', 'email' => 'unicode@example.test', 'phone' => '+62 800 000', 'password' => $twoCharacters, 'password_confirmation' => $twoCharacters];
        $this->post('/register', $input)->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'unicode@example.test']);
        $original = str_repeat('🙂', 8);
        $this->post('/register', [...$input, 'password' => $original, 'password_confirmation' => $original])->assertRedirect('/account');
        $target = User::where('email', 'unicode@example.test')->firstOrFail();
        $this->rejected(fn () => app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, $twoCharacters, $this->evidence()));
        $this->assertTrue(Hash::check($original, $target->fresh()->password));
        $overlong = str_repeat('🙂', 19);
        $this->rejected(fn () => app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, $overlong, $this->evidence()));
        $this->assertTrue(Hash::check($original, $target->fresh()->password));
        $replacement = str_repeat('🙃', 8);
        app(StaffAccess::class)->recover($owner->id, 'old-password', $target->id, $replacement, $this->evidence());
        $this->assertTrue(Hash::check($replacement, $target->fresh()->password));
        $this->freshRequest();
        $this->post('/login', ['email' => $target->email, 'password' => $replacement])->assertRedirect('/account');
        $this->get('/account')->assertOk();
    }

    public function test_recovery_credentials_are_redacted_from_exception_arguments(): void
    {
        $owner = $this->owner();
        $target = $this->user('target@example.test');
        $previous = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            app(StaffAccess::class)->recover($owner->id, 'synthetic-owner-secret', $target->id, 'synthetic-new-secret', $this->evidence());
            $this->fail('Expected input rejection.');
        } catch (ValidationException $exception) {
            $trace = $exception->getTraceAsString();
            $this->assertStringNotContainsString('synthetic-owner-secret', $trace);
            $this->assertStringNotContainsString('synthetic-new-secret', $trace);
            $this->assertStringContainsString('SensitiveParameterValue', $trace);
            $protectedClosureFound = false;
            foreach ($exception->getTrace() as $frame) {
                $callback = $frame['args'][0] ?? null;
                if ($callback instanceof Closure) {
                    $captures = (new ReflectionFunction($callback))->getStaticVariables();
                    if (isset($captures['protectedActorPassword'])) {
                        $protectedClosureFound = true;
                        $this->assertInstanceOf(SensitiveParameterValue::class, $captures['protectedActorPassword']);
                        $this->assertInstanceOf(SensitiveParameterValue::class, $captures['protectedHash']);
                        $this->assertNotContains('synthetic-owner-secret', $captures);
                    }
                }
            }
            $this->assertTrue($protectedClosureFound, 'Exercise redaction inside the real recovery transaction.');
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }
}
