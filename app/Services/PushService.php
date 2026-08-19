<?php

namespace App\Services;

use App\Enums\SettingKey;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushService
{
    public function __construct(private readonly SettingService $settings) {}

    public function isConfigured(): bool
    {
        return (bool) $this->settings->get(SettingKey::ONESIGNAL_APP_ID)
            && (bool) $this->settings->get(SettingKey::ONESIGNAL_REST_API_KEY);
    }

    /**
     * Targets by external_user_id so no player_id is ever stored on our side
     * (plan §8.1). $externalId is the "<morph>:<key>" of the recipient.
     */
    public function send(string $externalId, string $title, string $body, array $data = []): bool
    {
        if (! $this->isConfigured()) {
            Log::info('Push skipped: OneSignal not configured', ['to' => $externalId]);

            return false;
        }

        $response = $this->request([
            'app_id' => $this->settings->get(SettingKey::ONESIGNAL_APP_ID),
            'include_aliases' => ['external_id' => [$externalId]],
            'target_channel' => 'push',
            'headings' => ['en' => $title, 'ar' => $title],
            'contents' => ['en' => $body, 'ar' => $body],
            'data' => $data,
        ]);

        if ($response === null) {
            return false;
        }

        $errors = $response->json('errors');

        if ($response->failed() || ! empty($errors)) {
            Log::warning('Push rejected by OneSignal', [
                'status' => $response->status(),
                'errors' => $errors,
                'to' => $externalId,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Sends to every subscribed device instead of one person. OneSignal keeps
     * the subscriber list, so nothing has to be fanned out from our side.
     */
    public function broadcast(string $title, string $body, array $data = []): bool
    {
        if (! $this->isConfigured()) {
            Log::info('Broadcast skipped: OneSignal not configured', ['title' => $title]);

            return false;
        }

        $response = $this->request([
            'app_id' => $this->settings->get(SettingKey::ONESIGNAL_APP_ID),
            'included_segments' => ['Subscribed Users'],
            'target_channel' => 'push',
            'headings' => ['en' => $title, 'ar' => $title],
            'contents' => ['en' => $body, 'ar' => $body],
            'data' => $data,
        ]);

        if ($response === null) {
            return false;
        }

        $errors = $response->json('errors');

        if ($response->failed() || ! empty($errors)) {
            Log::warning('Broadcast rejected by OneSignal', [
                'status' => $response->status(),
                'errors' => $errors,
            ]);

            return false;
        }

        return true;
    }

    private function request(array $payload): ?Response
    {
        try {
            // Short on purpose: this now runs inside the request, so a stalled
            // OneSignal must not hold an admin's action open for long.
            return Http::withToken($this->settings->get(SettingKey::ONESIGNAL_REST_API_KEY))
                ->acceptJson()
                ->timeout(8)
                ->connectTimeout(4)
                ->post(config('services.onesignal.endpoint'), $payload);
        } catch (\Throwable $e) {
            Log::warning('Push transport failed', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
