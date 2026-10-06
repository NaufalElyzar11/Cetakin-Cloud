<?php

namespace Tests\Bootstrap;

use Illuminate\Foundation\Testing\TestCase;

class BootstrapTest extends TestCase
{
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

    public function test_only_the_bootstrap_page_and_health_routes_are_exposed(): void
    {
        $this->assertFalse(config('inertia.devtools.enabled'));
        $this->assertFalse(config('filesystems.disks.local.serve'));

        $routes = app('router')->getRoutes()->getRoutes();
        $uris = array_map(fn ($route) => $route->uri(), $routes);
        sort($uris);
        $this->assertSame(['/', 'up'], $uris);

        foreach (['_inertia/devtools/entries', '_inertia/devtools/entries/unknown', 'storage/bootstrap.txt'] as $path) {
            $this->get('/'.$path)->assertNotFound();
        }

        $this->put('/storage/bootstrap.txt')->assertNotFound();
    }
}
