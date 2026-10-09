<?php

namespace Tests\Database\Access;

use App\Access\Models\Customer;
use App\Access\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\PostgresTestCase;

class CustomerIdentityTest extends PostgresTestCase
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

    private function registration(string $email = 'customer@example.test'): array
    {
        return ['name' => 'Synthetic Customer', 'email' => $email, 'phone' => '+62 812 1234 5678', 'password' => 'valid-password', 'password_confirmation' => 'valid-password'];
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

    public function test_registration_atomically_creates_contacts_hashed_credentials_and_current_ownership(): void
    {
        $this->get('/register')->assertOk();
        $anonymousSession = $this->app['session']->driver()->getId();
        $anonymousToken = $this->app['session']->driver()->token();
        $this->freshRequest($anonymousSession);
        $this->post('/register', $this->registration('  CUSTOMER@EXAMPLE.TEST  '))->assertRedirect('/account');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_user', 1);
        $user = User::firstOrFail();
        $customer = Customer::firstOrFail();
        $this->assertSame('customer@example.test', $user->email);
        $this->assertSame('+62 812 1234 5678', $customer->phone);
        $this->assertNotSame('valid-password', $user->password);
        $this->assertTrue(Hash::check('valid-password', $user->password));
        $this->assertDatabaseHas('customer_user', ['user_id' => $user->id, 'customer_id' => $customer->id, 'revoked_at' => null]);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);
        $this->assertNotSame($anonymousSession, $this->app['session']->driver()->getId());
        $this->assertDatabaseMissing('sessions', ['id' => $anonymousSession]);
        $this->assertNotSame($anonymousToken, $this->app['session']->driver()->token());
        $this->assertNotNull(DB::table('customer_user')->where('user_id', $user->id)->where('customer_id', $customer->id)->value('created_at'));
    }

    public function test_invalid_contact_password_and_forged_privileges_leave_no_account(): void
    {
        $this->post('/register', [...$this->registration(), 'phone' => ' ', 'password' => 'short', 'password_confirmation' => 'different', 'customer_id' => 999, 'role' => 'administrator'])->assertSessionHasErrors(['phone', 'password', 'customer_id', 'role']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_user', 0);
        $this->assertGuest();
    }

    public function test_duplicate_normalized_identifier_cannot_create_another_profile(): void
    {
        $this->post('/register', $this->registration())->assertRedirect('/account');
        $this->freshRequest();
        $this->post('/register', $this->registration('CUSTOMER@EXAMPLE.TEST'))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_user', 1);
    }

    /** @return array<string, array{string, string}> */
    public static function supportedUnicodeEmails(): array
    {
        return [
            'Latin' => ['Ä@EXAMPLE.TEST', 'ä@example.test'],
            'dotted I' => ['İ@EXAMPLE.TEST', "i\u{0307}@example.test"],
            'final sigma' => ['ΟΣ@EXAMPLE.TEST', 'ος@example.test'],
        ];
    }

    #[DataProvider('supportedUnicodeEmails')]
    public function test_supported_unicode_identifiers_remain_canonical_and_can_log_in(string $input, string $canonical): void
    {
        $this->post('/register', $this->registration('  '.$input.'  '))->assertRedirect('/account');
        $this->assertDatabaseHas('users', ['email' => $canonical]);
        $this->assertDatabaseHas('customers', ['email' => $canonical]);
        $this->freshRequest();
        $this->post('/register', $this->registration($canonical))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->freshRequest();
        $this->post('/login', ['email' => $input, 'password' => 'valid-password'])->assertRedirect('/account');
        $this->assertAuthenticatedAs(User::firstOrFail());
    }

    public function test_transaction_failure_rolls_back_user_customer_and_ownership(): void
    {
        Customer::created(function (): void {
            throw new RuntimeException('Synthetic failure after customer creation.');
        });
        try {
            $this->withoutExceptionHandling()->post('/register', $this->registration());
            $this->fail('Registration must not claim success after a failed transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic failure after customer creation.', $exception->getMessage());
        } finally {
            Customer::flushEventListeners();
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_user', 0);
        $this->assertGuest();
    }

    public function test_login_rotates_session_and_persistent_throttle_rejects_valid_credentials_until_decay(): void
    {
        $this->post('/register', $this->registration());
        $this->freshRequest();
        $this->get('/login')->assertOk();
        $anonymousSession = $this->app['session']->driver()->getId();
        $anonymousToken = $this->app['session']->driver()->token();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->freshRequest($anonymousSession);
            $this->post('/login', ['email' => 'customer@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }
        $this->assertGreaterThan(0, DB::table('cache')->count());
        $this->freshRequest($anonymousSession);
        $this->post('/login', ['email' => 'customer@example.test', 'password' => 'valid-password'])->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again later.']);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->freshRequest($anonymousSession);
        $this->post('/login', ['email' => 'CUSTOMER@EXAMPLE.TEST', 'password' => 'valid-password'])->assertRedirect('/account');
        $this->assertAuthenticated();
        $this->assertNotSame($anonymousSession, $this->app['session']->driver()->getId());
        $this->assertDatabaseMissing('sessions', ['id' => $anonymousSession]);
        $this->assertNotSame($anonymousToken, $this->app['session']->driver()->token());
    }

    public function test_wrong_password_and_unknown_identifier_have_the_same_public_login_error(): void
    {
        $this->post('/register', $this->registration());
        foreach (['customer@example.test', 'unknown@example.test'] as $email) {
            $this->freshRequest();
            $this->post('/login', ['email' => $email, 'password' => 'wrong-password'])->assertSessionHasErrors(['email' => 'These credentials could not be authenticated.']);
            $this->assertGuest();
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_current_relationship_controls_list_detail_and_prevents_email_or_payload_authority(): void
    {
        $this->post('/register', $this->registration('a@example.test'));
        $userA = User::firstOrFail();
        $customerA = Customer::firstOrFail();
        $sessionA = $this->app['session']->driver()->getId();
        $this->freshRequest();
        $this->post('/register', $this->registration('b@example.test'));
        $customerB = Customer::orderByDesc('id')->firstOrFail();

        $this->freshRequest($sessionA);
        $this->get('/account?customer_id='.$customerB->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (AssertableInertia $page) => $page->component('Access/Account')->has('customers', 1)->where('customers.0.id', $customerA->id)->where('actor.id', $userA->id)->missing('actor.password')->missing('customers.0.pivot')->missing('permissions'));
        $this->get('/account/customers/'.$customerB->id)->assertNotFound()->assertDontSee('b@example.test');
        $this->get('/account/customers/'.$customerA->id.'?customer_id='.$customerB->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('customer.id', $customerA->id));
        $this->post('/account/customers/'.$customerB->id, ['customer_id' => $customerA->id])->assertStatus(405);

        DB::table('customer_user')->where('user_id', $userA->id)->where('customer_id', $customerA->id)->update(['revoked_at' => now()]);
        $this->freshRequest($sessionA);
        $this->get('/account/customers/'.$customerA->id)->assertNotFound();
        $this->get('/account')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('customers', 0));
        $this->assertDatabaseHas('customer_user', ['user_id' => $userA->id, 'customer_id' => $customerA->id]);
    }

    public function test_multiple_relationships_are_allowed_but_duplicate_pairs_and_orphan_links_are_rejected(): void
    {
        $this->post('/register', $this->registration());
        $user = User::firstOrFail();
        $firstCustomer = Customer::firstOrFail();
        $secondCustomer = Customer::create(['name' => 'Second synthetic customer', 'email' => $user->email, 'phone' => '080012345']);
        $user->customers()->attach($secondCustomer);
        $this->assertSame(2, $user->customers()->count());
        $otherUser = User::create(['name' => 'Second actor', 'email' => 'second@example.test', 'password' => Hash::make('valid-password')]);
        $otherUser->customers()->attach($firstCustomer);
        $this->assertSame(2, $firstCustomer->users()->count());
        foreach ([['user_id' => $user->id, 'customer_id' => $firstCustomer->id], ['user_id' => $user->id, 'customer_id' => 999999]] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('customer_user')->insert($invalid));
                $this->fail('Invalid relationship must fail at the PostgreSQL boundary.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->getCode(), ['23505', '23503']);
            }
        }
        $this->assertDatabaseCount('customer_user', 3);
    }

    public function test_postgresql_rejects_duplicate_and_noncanonical_login_identifiers(): void
    {
        $this->post('/register', $this->registration());
        foreach ([['customer@example.test', '23505'], ['UPPERCASE@EXAMPLE.TEST', '23514']] as [$email, $code]) {
            try {
                DB::transaction(fn () => User::create(['name' => 'Invalid duplicate', 'email' => $email, 'password' => Hash::make('valid-password')]));
                $this->fail('Invalid identifier must be rejected by PostgreSQL.');
            } catch (QueryException $exception) {
                $this->assertSame($code, $exception->getCode());
            }
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_multibyte_passwords_over_bcrypt_byte_limit_are_rejected_before_hashing_or_login(): void
    {
        $password = str_repeat('é', 37);
        $this->post('/register', [...$this->registration(), 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
        $this->freshRequest();
        $this->post('/login', ['email' => 'customer@example.test', 'password' => $password])->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_logout_deleted_or_expired_server_sessions_deny_an_old_browser_session(): void
    {
        $this->post('/register', $this->registration());
        $oldSession = $this->app['session']->driver()->getId();
        $this->freshRequest($oldSession);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertDatabaseMissing('sessions', ['id' => $oldSession]);
        $this->freshRequest($oldSession);
        $this->get('/account')->assertRedirect('/login');

        $this->freshRequest();
        $this->post('/login', ['email' => 'customer@example.test', 'password' => 'valid-password']);
        $revokedSession = $this->app['session']->driver()->getId();
        DB::table('sessions')->where('id', $revokedSession)->delete();
        $this->freshRequest($revokedSession);
        $this->get('/account')->assertRedirect('/login');

        $this->freshRequest();
        $this->post('/login', ['email' => 'customer@example.test', 'password' => 'valid-password']);
        $expiredSession = $this->app['session']->driver()->getId();
        DB::table('sessions')->where('id', $expiredSession)->update(['last_activity' => now()->subMinutes(config('session.lifetime') + 1)->timestamp]);
        $this->freshRequest($expiredSession);
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_csrf_security_without_the_framework_testing_bypass_and_cookie_settings(): void
    {
        // The database guard ran while testing; only HTTP behavior now uses the
        // real middleware branch. No same-origin metadata masks a bad token.
        $this->app->instance('env', 'production');
        config(['session.secure' => true]);
        $response = $this->get('/register')->assertOk();
        $sessionId = $this->app['session']->driver()->getId();
        $token = $this->app['session']->driver()->token();
        $cookies = $response->headers->getCookies();
        $sessionCookie = array_values(array_filter($cookies, fn ($cookie) => $cookie->getName() === config('session.cookie')))[0];
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertSame('lax', $sessionCookie->getSameSite());
        $this->assertNull(config('session.domain'));
        $this->freshRequest($sessionId);
        $this->post('/register', [...$this->registration(), '_token' => 'invalid'], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
        $this->freshRequest($sessionId);
        $this->post('/register', $this->registration(), ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
        $this->freshRequest($sessionId);
        $this->post('/register', [...$this->registration(), '_token' => $token])->assertRedirect('/account');
        $this->assertDatabaseCount('users', 1);
    }
}
