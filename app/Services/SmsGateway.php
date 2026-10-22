<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Alpha SMS (sms.net.bd) bulk SMS API: POST /sendsms with comma-separated numbers, GET /user/balance/.
 * Errors come back as {"error": <code>, "msg": "..."}; we surface their message to the admin.
 */
class SmsGateway
{
    /** Numbers per API request; one request covers a whole small group. */
    public const NUMBERS_PER_REQUEST = 100;

    public static function isConfigured(): bool
    {
        return filled(config('sms.api_key'));
    }

    /**
     * Send one message to every number and return the gateway's request IDs.
     *
     * @param  list<string>  $numbers  Local 01XXXXXXXXX numbers (the gateway accepts them as is).
     * @return list<string>
     */
    public function send(array $numbers, string $message): array
    {
        $requestIds = [];

        foreach (array_chunk($numbers, self::NUMBERS_PER_REQUEST) as $chunk) {
            $data = $this->post('/sendsms', array_filter([
                'msg' => $message,
                'to' => implode(',', $chunk),
                'sender_id' => config('sms.sender_id'),
                'content_id' => config('sms.content_id'),
            ]));

            $requestIds[] = (string) data_get($data, 'data.request_id');
        }

        return $requestIds;
    }

    /** Remaining balance in taka, or null when the gateway can't be reached. */
    public function balance(): ?float
    {
        try {
            $response = Http::acceptJson()->timeout(config('sms.timeout'))
                ->get($this->url('/user/balance/'), ['api_key' => config('sms.api_key')]);
        } catch (\Throwable) {
            return null;
        }

        return $response->json('error') === 0 ? (float) $response->json('data.balance') : null;
    }

    /** @param array<string, string> $payload */
    private function post(string $path, array $payload): array
    {
        $response = Http::asForm()->acceptJson()->timeout(config('sms.timeout'))
            ->post($this->url($path), ['api_key' => config('sms.api_key')] + $payload);

        $data = $response->json() ?? [];

        if (($data['error'] ?? null) !== 0) {
            Log::error('SMS send failed', ['status' => $response->status(), 'body' => $data ?: $response->body()]);

            throw new RuntimeException('The SMS gateway refused the message: '.($data['msg'] ?? 'no response').
                (isset($data['error']) ? " (error {$data['error']})" : '').'.');
        }

        return $data;
    }

    private function url(string $path): string
    {
        return rtrim(config('sms.base_url'), '/').$path;
    }
}
