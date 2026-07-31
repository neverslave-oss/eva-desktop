<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * KernelCentralService
 *
 * Handles communication with the kernel-central server for device pairing,
 * API token management, heartbeat, and message relay.
 *
 * The desktop app registers itself as a "device" on kernel-central:
 *   1. Initiate pairing  → POST /api/devices/pair (auth'd via API token)
 *   2. Confirm pairing   → POST /api/devices/confirm (public, uses pair_secret)
 *   3. Heartbeat         → POST /api/devices/heartbeat (auth'd via Sanctum token)
 */
class KernelCentralService
{
    /**
     * The timeout for HTTP requests to kernel-central (seconds).
     */
    public const TIMEOUT = 10;

    /**
     * Build the full API URL from the base central URL.
     */
    protected function apiUrl(string $path): string
    {
        $base = config('kernel-desktop.central.url', 'https://kernel-central.neverslave.dev');
        return rtrim($base, '/') . '/api' . $path;
    }

    /**
     * Test connectivity to kernel-central.
     * Returns true if the health endpoint responds.
     */
    public function ping(): bool
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get($this->apiUrl('/health'));
            return $response->successful();
        } catch (ConnectionException $e) {
            return false;
        }
    }

    /**
     * Initiate device pairing.
     *
     * Requires an API token from kernel-central (created in Settings → API Tokens).
     * Returns the pair_token and pair_secret needed to confirm pairing.
     *
     * @return array{success: bool, pair_token?: string, pair_secret?: string, device_id?: int, error?: string}
     */
    public function pair(string $deviceName, string $deviceType, string $apiToken): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withToken($apiToken)
                ->post($this->apiUrl('/devices/pair'), [
                    'name' => $deviceName,
                    'type' => $deviceType,
                ]);

            if ($response->successful()) {
                $data = $response->json('data');

                return [
                    'success' => true,
                    'pair_token' => $data['pair_token'],
                    'pair_secret' => $data['pair_secret'],
                    'device_id' => $data['id'],
                ];
            }

            $message = $response->json('message', 'Pairing request failed');
            Log::warning('KernelCentral pairing failed', [
                'status' => $response->status(),
                'message' => $message,
            ]);

            return [
                'success' => false,
                'error' => $message,
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'error' => 'Could not connect to kernel-central. Check the URL and try again.',
            ];
        } catch (\Exception $e) {
            Log::error('KernelCentral pairing exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm device pairing.
     *
     * Uses the pair_token and pair_secret obtained from initiatePairing().
     * Returns a Sanctum token that the device uses for subsequent API calls.
     *
     * @return array{success: bool, token?: string, device_id?: int, error?: string}
     */
    public function confirm(string $pairToken, string $pairSecret): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->post($this->apiUrl('/devices/confirm'), [
                    'pair_token' => $pairToken,
                    'pair_secret' => $pairSecret,
                ]);

            if ($response->successful()) {
                $data = $response->json('data');

                return [
                    'success' => true,
                    'token' => $data['token'],
                    'device_id' => $data['device_id'],
                ];
            }

            $message = $response->json('message', 'Confirmation failed');
            Log::warning('KernelCentral confirm failed', [
                'status' => $response->status(),
                'message' => $message,
            ]);

            return [
                'success' => false,
                'error' => $message,
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'error' => 'Could not connect to kernel-central during confirmation.',
            ];
        } catch (\Exception $e) {
            Log::error('KernelCentral confirm exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a device heartbeat to kernel-central.
     * Should be called every 30 seconds after pairing.
     */
    public function heartbeat(int $deviceId): bool
    {
        $token = $this->getStoredToken();
        if (! $token) {
            return false;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withToken($token)
                ->post($this->apiUrl('/devices/heartbeat'), [
                    'device_id' => $deviceId,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Relay a message to kernel-central.
     */
    public function relayMessage(string $message): array
    {
        $token = $this->getStoredToken();
        if (! $token) {
            return ['success' => false, 'error' => 'Device not paired. No token available.'];
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withToken($token)
                ->post($this->apiUrl('/messages/relay'), [
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('message', 'Relay failed'),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    //  Token persistence
    // -----------------------------------------------------------------------

    /**
     * Store the device Sanctum token locally after successful pairing.
     */
    public function storeToken(string $token): void
    {
        $path = config('kernel-desktop.device.token_path', storage_path('app/device-token.txt'));
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $token);
        Log::info('Device token stored', ['path' => $path]);
    }

    /**
     * Retrieve the stored device token.
     */
    public function getStoredToken(): ?string
    {
        $path = config('kernel-desktop.device.token_path', storage_path('app/device-token.txt'));

        if (file_exists($path)) {
            $token = trim(file_get_contents($path));

            return $token !== '' ? $token : null;
        }

        return null;
    }

    /**
     * Remove the stored device token (unpair).
     */
    public function clearToken(): void
    {
        $path = config('kernel-desktop.device.token_path', storage_path('app/device-token.txt'));

        if (file_exists($path)) {
            unlink($path);
            Log::info('Device token cleared');
        }
    }

    /**
     * Check whether this device has been paired (has a stored token).
     */
    public function isPaired(): bool
    {
        return $this->getStoredToken() !== null;
    }
}
