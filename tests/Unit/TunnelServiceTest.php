<?php

use App\Services\KernelCentralService;
use App\Services\TunnelService;

function wsEndpoint(TunnelService $tunnel): string
{
    $method = new ReflectionMethod(TunnelService::class, 'wsEndpoint');
    $method->setAccessible(true);

    return $method->invoke($tunnel);
}

test('standard https port 443 is omitted from url', function () {
    config(['kernel-desktop.central.url' => 'https://kernel-central.test']);
    config(['kernel-desktop.reverb.host' => '']);
    config(['kernel-desktop.reverb.port' => 443]);
    config(['kernel-desktop.reverb.app_key' => 'appkey']);

    $tunnel = new TunnelService(new KernelCentralService());

    expect(wsEndpoint($tunnel))->toBe('wss://kernel-central.test/app/appkey');
});

test('standard http port 80 is omitted from url', function () {
    config(['kernel-desktop.central.url' => 'http://kernel-central.test']);
    config(['kernel-desktop.reverb.host' => '']);
    config(['kernel-desktop.reverb.port' => 80]);
    config(['kernel-desktop.reverb.app_key' => 'appkey']);

    $tunnel = new TunnelService(new KernelCentralService());

    expect(wsEndpoint($tunnel))->toBe('ws://kernel-central.test/app/appkey');
});

test('custom port is appended to url', function () {
    config(['kernel-desktop.central.url' => 'https://kernel-central.test']);
    config(['kernel-desktop.reverb.host' => '']);
    config(['kernel-desktop.reverb.port' => 8080]);
    config(['kernel-desktop.reverb.app_key' => 'appkey']);

    $tunnel = new TunnelService(new KernelCentralService());

    expect(wsEndpoint($tunnel))->toBe('wss://kernel-central.test:8080/app/appkey');
});

test('explicit reverb host takes precedence', function () {
    config(['kernel-desktop.central.url' => 'https://kernel-central.test']);
    config(['kernel-desktop.reverb.host' => 'reverb.local']);
    config(['kernel-desktop.reverb.port' => 8080]);
    config(['kernel-desktop.reverb.scheme' => 'ws']);
    config(['kernel-desktop.reverb.app_key' => 'appkey']);

    $tunnel = new TunnelService(new KernelCentralService());

    expect(wsEndpoint($tunnel))->toBe('ws://reverb.local:8080/app/appkey');
});
