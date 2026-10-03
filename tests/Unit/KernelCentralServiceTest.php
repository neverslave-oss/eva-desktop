<?php

use App\Services\KernelCentralService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['kernel-desktop.central.url' => 'https://kernel-central.test']);
    config(['kernel-desktop.device.token_path' => storage_path('framework/testing/kernel-central-heartbeat-token.txt')]);
});

test('ping returns true when the health endpoint responds ok', function () {
    Http::fake([
        'kernel-central.test/api/health' => Http::response(['status' => 'ok'], 200),
    ]);

    expect((new KernelCentralService())->ping())->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://kernel-central.test/api/health');
});

test('ping returns false when the health endpoint is not ok', function () {
    Http::fake([
        'kernel-central.test/api/health' => Http::response(['status' => 'degraded'], 503),
    ]);

    expect((new KernelCentralService())->ping())->toBeFalse();
});

test('ping returns false when kernel-central is unreachable', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    expect((new KernelCentralService())->ping())->toBeFalse();
});

test('heartbeat posts the device id with the stored token', function () {
    file_put_contents(config('kernel-desktop.device.token_path'), 'desktop-token');

    Http::fake([
        'kernel-central.test/api/devices/heartbeat' => Http::response(['data' => ['status' => 'ok']], 200),
    ]);

    expect((new KernelCentralService())->heartbeat(42))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://kernel-central.test/api/devices/heartbeat'
            && $request->hasHeader('Authorization', 'Bearer desktop-token')
            && $request['device_id'] === 42;
    });

    @unlink(config('kernel-desktop.device.token_path'));
});

test('heartbeat returns false when no device token is stored', function () {
    Http::fake();

    expect((new KernelCentralService())->heartbeat(42))->toBeFalse();

    Http::assertNothingSent();
});

test('heartbeat returns false on a failed response', function () {
    file_put_contents(config('kernel-desktop.device.token_path'), 'desktop-token');

    Http::fake([
        'kernel-central.test/api/devices/heartbeat' => Http::response(['message' => 'unauthorized'], 401),
    ]);

    expect((new KernelCentralService())->heartbeat(42))->toBeFalse();

    @unlink(config('kernel-desktop.device.token_path'));
});
