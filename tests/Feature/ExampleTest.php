<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Root `/` is the SetupWizard, guarded by the `setup.completed` middleware
 * (EnsureSetupCompleted). That middleware reads `app_settings`, so the request
 * needs a migrated database to succeed. RefreshDatabase runs the migrations
 * against the test `:memory:` connection.
 *
 * On a fresh database setup is NOT complete, so the middleware lets the wizard
 * render and `/` returns 200. (Once setup IS complete, the middleware redirects
 * the wizard to `/chat` with a 302 — that behavior is covered separately.)
 */
uses(RefreshDatabase::class);

test('the root route renders the setup wizard on a fresh database', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSeeLivewire('setup-wizard');
});
