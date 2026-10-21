<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * bKash PGW Tokenized Checkout (v1.2.0-beta).
 *
 * Flow: grantToken -> createPayment (redirect user to bkashURL)
 *       -> bKash redirects back to callbackURL?paymentID=..&status=..
 *       -> executePayment (or queryPayment if execute times out).
 */
class BkashService
{
    private const TOKEN_CACHE_KEY = 'bkash.id_token';

    public function grantToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function () {
            $response = Http::acceptJson()
                ->timeout(config('bkash.timeout'))
                ->withHeaders([
                    'username' => config('bkash.username'),
                    'password' => config('bkash.password'),
                ])
                ->post($this->url('/tokenized/checkout/token/grant'), [
                    'app_key' => config('bkash.app_key'),
                    'app_secret' => config('bkash.app_secret'),
                ]);

            $token = $response->json('id_token');

            if (! $response->successful() || ! $token) {
                Log::error('bKash grant token failed', ['body' => $response->json() ?? $response->body()]);
                throw new RuntimeException('bKash authentication failed. Please try again later.');
            }

            return $token;
        });
    }

    /** @return array{paymentID:string,bkashURL:string} plus the rest of bKash's response */
    public function createPayment(string $amount, string $invoiceNo, string $payerReference, string $callbackUrl): array
    {
        $data = $this->call('/tokenized/checkout/create', [
            'mode' => '0011',
            'payerReference' => $payerReference,
            'callbackURL' => $callbackUrl,
            'amount' => $amount,
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $invoiceNo,
        ]);

        if (($data['statusCode'] ?? null) !== '0000' || empty($data['bkashURL']) || empty($data['paymentID'])) {
            Log::error('bKash create payment failed', $data);
            throw new RuntimeException($data['statusMessage'] ?? $data['errorMessage'] ?? 'Could not start bKash payment.');
        }

        return $data;
    }

    public function executePayment(string $paymentId): array
    {
        return $this->call('/tokenized/checkout/execute', ['paymentID' => $paymentId]);
    }

    public function queryPayment(string $paymentId): array
    {
        return $this->call('/tokenized/checkout/payment/status', ['paymentID' => $paymentId]);
    }

    private function call(string $path, array $payload): array
    {
        $response = $this->client()->post($this->url($path), $payload);

        // Token may have been revoked early; refresh once and retry.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->client()->post($this->url($path), $payload);
        }

        return $response->json() ?? ['statusMessage' => 'Invalid response from bKash', 'raw' => $response->body()];
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(config('bkash.timeout'))
            ->withHeaders([
                'Authorization' => $this->grantToken(),
                'X-APP-Key' => config('bkash.app_key'),
            ]);
    }

    private function url(string $path): string
    {
        return rtrim(config('bkash.base_url'), '/').$path;
    }
}
