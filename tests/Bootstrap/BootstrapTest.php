<?php

namespace Tests\Bootstrap;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\Process\Process;

class BootstrapTest extends TestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set('session.driver', 'file');
        $app['config']->set('cache.default', 'array');

        return $app;
    }

    public function test_neutral_page_serves_the_inertia_browser_entry(): void
    {
        $initial = $this->get('/')
            ->assertOk()
            ->assertSee('/build/assets/', false)
            ->assertSee('data-page=', false);

        preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $initial->getContent(), $matches);
        $page = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        $this->get('/', [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => $page['version'],
        ])
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Bootstrap')
            ->assertJsonPath('url', '/');

        $this->assertFalse(config('inertia.ssr.enabled'));

        $this->get('/', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale-build'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
    }

    public function test_health_exposes_only_a_literal_non_sensitive_status(): void
    {
        config(['app.key' => 'private-test-marker', 'app.debug' => true]);

        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertDontSee('private-test-marker');
    }

    public function test_unknown_route_returns_not_found(): void
    {
        $this->get('/not-an-application-route')->assertNotFound();
    }

    public function test_secure_cookie_defaults_match_the_application_environment_without_loading_local_dotenv(): void
    {
        $probe = <<<'PHP'
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$application = require 'config/app.php';
$session = require 'config/session.php';
echo json_encode([$application['env'], $session['secure']], JSON_THROW_ON_ERROR);
PHP;
        foreach ([[false, false, 'production', true], ['local', false, 'local', false], ['production', false, 'production', true], ['local', 'true', 'local', true]] as [$environment, $secure, $expectedEnvironment, $expectedSecure]) {
            $process = new Process([PHP_BINARY, '-r', $probe], base_path(), ['APP_ENV' => $environment, 'SESSION_SECURE_COOKIE' => $secure]);
            $process->mustRun();
            $this->assertSame([$expectedEnvironment, $expectedSecure], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
        }
    }

    public function test_only_bootstrap_and_customer_identity_routes_are_exposed(): void
    {
        $this->assertFalse(config('inertia.devtools.enabled'));
        $this->assertFalse(config('filesystems.disks.local.serve'));

        $routes = app('router')->getRoutes()->getRoutes();
        $uris = array_map(fn ($route) => $route->uri(), $routes);
        sort($uris);
        $this->assertSame(['/', 'account', 'account/customers/{customer}', 'login', 'login', 'logout', 'register', 'register', 'up'], $uris);

        foreach (['_inertia/devtools/entries', '_inertia/devtools/entries/unknown', 'storage/bootstrap.txt'] as $path) {
            $this->get('/'.$path)->assertNotFound();
        }

        $this->put('/storage/bootstrap.txt')->assertNotFound();
    }
}
