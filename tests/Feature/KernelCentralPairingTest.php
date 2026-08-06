<?php

use App\Services\KernelCentralService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['kernel-desktop.central.url' => 'https://kernel-central.test']);
});

test('pair returns pair token and secret on success', function () {
    Http::fake([
        'kernel-central.test/api/devices/pair' => Http::response([
            'data' => [
                'pair_token' => 'ptoken',
                'pair_secret' => 'psecret',
                'id' => 42,
            ],
        ], 200),
    ]);

    $result = (new KernelCentralService())->pair('desktop', 'desktop', 'api-token');

    expect($result['success'])->toBeTrue()
        ->and($result['pair_token'])->toBe('ptoken')
        ->and($result['pair_secret'])->toBe('psecret')
        ->and($result['device_id'])->toBe(42);
});

test('pair fails gracefully when response has no data', function () {
    Http::fake([
        'kernel-central.test/api/devices/pair' => Http::response(['message' => 'ok'], 200),
    ]);

    $result = (new KernelCentralService())->pair('desktop', 'desktop', 'api-token');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('Unexpected API response format');
});

test('pair returns error on failed response', function () {
    Http::fake([
        'kernel-central.test/api/devices/pair' => Http::response(['message' => 'Invalid token'], 401),
    ]);

    $result = (new KernelCentralService())->pair('desktop', 'desktop', 'bad-token');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('Invalid token');
});

test('confirm returns token on success', function () {
    Http::fake([
        'kernel-central.test/api/devices/confirm' => Http::response([
            'data' => [
                'token' => 'sanctum-token',
                'device_id' => 42,
            ],
        ], 200),
    ]);

    $result = (new KernelCentralService())->confirm('ptoken', 'psecret');

    expect($result['success'])->toBeTrue()
        ->and($result['token'])->toBe('sanctum-token')
        ->and($result['device_id'])->toBe(42);
});

test('confirm fails gracefully when response has no data', function () {
    Http::fake([
        'kernel-central.test/api/devices/confirm' => Http::response(['message' => 'ok'], 200),
    ]);

    $result = (new KernelCentralService())->confirm('ptoken', 'psecret');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('Unexpected API response format');
});

test('pair handles connection exception', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    $result = (new KernelCentralService())->pair('desktop', 'desktop', 'api-token');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('Could not connect to kernel-central. Check the URL and try again.');
});
