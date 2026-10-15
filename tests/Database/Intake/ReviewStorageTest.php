<?php

namespace Tests\Database\Intake;

use App\Access\Actions\StaffAccess;
use App\Files\DesignObjectStores;
use App\Files\ExactDesignObjects;
use App\Files\ObjectUnavailable;
use App\Files\VerifiedDesignRead;
use App\Intake\RequestReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CompatibleObjects;
use Tests\Support\PostgresTestCase;
use Tests\Support\ReviewFixtures;

class ReviewStorageTest extends PostgresTestCase
{
    use CompatibleObjects, RefreshDatabase, ReviewFixtures;

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

    public function test_real_compatible_staff_read_is_exact_scoped_current_and_customer_isolation_remains(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other@example.test');
        $customer = $this->reviewUser('customer@example.test');
        $foreignCustomer = $this->reviewUser('foreign@example.test');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second Owner');
        $print = $this->submitted($customer);
        $foreign = $this->submitted($foreignCustomer);
        $this->assigned($owner, $print);
        $file = DB::table('design_file_versions')->where('request_id', $print->id)->first();
        $foreignFile = DB::table('design_file_versions')->where('request_id', $foreign->id)->first();
        $this->actingAs($owner)->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertOk()->assertHeader('Content-Type', 'application/octet-stream')->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($this->asciiStl());
        $this->get('/staff/requests/'.$print->id.'/files/'.$foreignFile->id)->assertForbidden();
        $this->get('/staff/requests/'.$foreign->id.'/files/'.$foreignFile->id)->assertForbidden();
        $this->get('/requests/'.$foreign->id)->assertNotFound(); // Staff is not customer authority.
        $this->get('/staff/requests/'.$print->id)->assertOk();
        app(StaffAccess::class)->revoke($other->id, $owner->currentResponsibilityGrantId('owner'), 'Revoke after loaded review page');
        $this->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertForbidden();
        $this->app['session']->forget('password_hash_web');
        $this->actingAs($customer)->get('/requests/'.$print->id.'/files/'.$file->id)->assertOk()->assertStreamedContent($this->asciiStl());
        DB::table('customer_user')->where('user_id', $customer->id)->update(['revoked_at' => now()]);
        $this->get('/requests/'.$print->id.'/files/'.$file->id)->assertNotFound();
    }

    public function test_real_missing_or_corrupt_objects_keep_review_and_confirmed_request_truthful(): void
    {
        $owner = $this->reviewOwner();
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        app(RequestReview::class)->review($owner->id, $print->id, $this->reviewInput($owner, $print));
        $file = DB::table('design_file_versions')->first();
        $key = $this->objectPrefix.'/intake/'.$file->attempt_id.'/'.$file->id;
        $this->objectClient->putObject(['Bucket' => $this->objectSettings['bucket'], 'Key' => $key, 'Body' => 'Synthetic corrupted exact object']);
        $this->actingAs($owner)->get('/staff/requests/'.$print->id)->assertInertia(fn (AssertableInertia $page) => $page->where('request.reviewDisposition', 'UNAVAILABLE')->where('request.files.0.available', false)->where('request.latestReview.sufficient', true));
        $this->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertGone();
        $this->objectClient->deleteObject(['Bucket' => $this->objectSettings['bucket'], 'Key' => $key]);
        $this->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertGone();
        $this->assertSame('SUBMITTED', $print->fresh()->status);
        $this->assertDatabaseCount('request_review_events', 2);
        $this->assertDatabaseCount('design_file_versions', 1);
    }

    public function test_provider_uncertainty_blocks_sufficiency_and_does_not_expose_diagnostics(): void
    {
        $owner = $this->reviewOwner();
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $this->app->bind(DesignObjectStores::class, fn () => new class extends DesignObjectStores
        {
            public function forAttempt(object $attempt): ExactDesignObjects
            {
                throw new ObjectUnavailable('UNKNOWN');
            }
        });
        $file = DB::table('design_file_versions')->first();
        $this->actingAs($owner)->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertStatus(503)->assertDontSee($this->objectSettings['bucket']);
        $this->post('/staff/requests/'.$print->id.'/review', $this->reviewInput($owner, $print))->assertSessionHasErrors('review');
        $this->assertDatabaseCount('request_review_events', 1);
    }

    public function test_revocation_during_real_verified_read_denies_downloaded_bytes(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other@example.test');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second Owner');
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $grant = $owner->currentResponsibilityGrantId('owner');
        $this->revokeDuringRead($grant, $other->id);
        $file = DB::table('design_file_versions')->first();
        $this->actingAs($owner)->get('/staff/requests/'.$print->id.'/files/'.$file->id)->assertForbidden()->assertDontSee($this->asciiStl());
        $this->assertDatabaseCount('request_review_events', 1);
    }

    public function test_revocation_during_real_object_check_prevents_manual_review_commit(): void
    {
        $owner = $this->reviewOwner();
        $other = $this->reviewUser('other@example.test');
        app(StaffAccess::class)->grant($owner->id, $other->id, 'owner', 'Second Owner');
        $print = $this->submitted($this->reviewUser('customer@example.test'));
        $this->assigned($owner, $print);
        $this->revokeDuringRead($owner->currentResponsibilityGrantId('owner'), $other->id);
        $this->actingAs($owner)->post('/staff/requests/'.$print->id.'/review', $this->reviewInput($owner, $print))->assertForbidden();
        $this->assertDatabaseCount('request_review_events', 1);
        $this->assertSame(1, app(RequestReview::class)->currentWork($print->id)->revision);
        $this->assertSame('SUBMITTED', $print->fresh()->status);
    }

    private function revokeDuringRead(int $grant, int $other): void
    {
        $real = app(DesignObjectStores::class);
        $this->app->bind(DesignObjectStores::class, fn () => new class($real, $grant, $other) extends DesignObjectStores
        {
            public function __construct(private DesignObjectStores $real, private int $grant, private int $other) {}

            public function forAttempt(object $attempt): ExactDesignObjects
            {
                return new class($this->real->forAttempt($attempt), $this->grant, $this->other) implements ExactDesignObjects
                {
                    public function __construct(private ExactDesignObjects $real, private int $grant, private int $other) {}

                    public function read(string $attempt, array $file): VerifiedDesignRead
                    {
                        $result = $this->real->read($attempt, $file);
                        app(StaffAccess::class)->revoke($this->other, $this->grant, 'Permission removed while exact object was spooled');

                        return $result;
                    }

                    public function publish(string $attempt, array $file, string $source): void
                    {
                        $this->real->publish($attempt, $file, $source);
                    }

                    public function remove(string $attempt, string $file): void
                    {
                        $this->real->remove($attempt, $file);
                    }

                    public function absent(string $attempt, string $file): bool
                    {
                        return $this->real->absent($attempt, $file);
                    }
                };
            }
        });
    }
}
