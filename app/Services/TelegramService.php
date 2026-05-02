<?php

namespace App\Services;

use App\Models\TelegramSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $telegramSetting;
    protected ?string $lastError = null;

    public function __construct(?TelegramSetting $telegramSetting = null)
    {
        $this->telegramSetting = $telegramSetting ?? TelegramSetting::first();
    }

    public function isEnabled(): bool
    {
        return $this->telegramSetting && $this->telegramSetting->is_active;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function sendMessage(string $message): bool
    {
        if (!$this->isEnabled()) {
            $this->lastError = 'Telegram notifications are disabled.';
            Log::info('Telegram notifications are disabled');
            return false;
        }

        if (!$this->telegramSetting->bot_token || !$this->telegramSetting->chat_id) {
            $this->lastError = 'Telegram bot token or chat ID is not configured.';
            Log::error('Telegram bot token or chat ID is not configured');
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->telegramSetting->bot_token}/sendMessage";

            $response = Http::withOptions([
                'verify' => app()->environment('local') ? false : config('services.telegram.verify_ssl', true),
            ])->post($url, [
                'chat_id' => $this->telegramSetting->chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($response->successful()) {
                Log::info('Telegram message sent successfully');
                return true;
            }

            $body = $response->body();
            if (str_contains($body, 'chat not found')) {
                $this->lastError = 'Telegram API error: chat not found. Make sure the bot is started, has been added to the chat, and the chat ID is correct.';
            } else {
                $this->lastError = 'Telegram API error: ' . $body . ' (status ' . $response->status() . ')';
            }

            Log::error('Failed to send Telegram message', [
                'response' => $body,
                'status' => $response->status(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->lastError = 'Exception while sending Telegram message: ' . $e->getMessage();
            Log::error('Exception while sending Telegram message', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function testConnection(): array
    {
        if (!$this->telegramSetting) {
            return [
                'success' => false,
                'message' => 'Telegram settings not found',
            ];
        }

        if (!$this->telegramSetting->bot_token || !$this->telegramSetting->chat_id) {
            return [
                'success' => false,
                'message' => 'Bot token and chat ID are required',
            ];
        }

        $testMessage = "🧪 Test message from Web Monitor\n\n" . now()->format('Y-m-d H:i:s');

        if ($this->sendMessage($testMessage)) {
            return [
                'success' => true,
                'message' => 'Test message sent successfully!',
            ];
        }

        return [
            'success' => false,
            'message' => $this->getLastError() ?? 'Failed to send test message. Please check your bot token and chat ID.',
        ];
    }

    public function fetchChatIdFromUpdates(): array
    {
        if (!$this->telegramSetting || !$this->telegramSetting->bot_token) {
            return [
                'success' => false,
                'message' => 'Bot token is required to fetch chat ID.',
            ];
        }

        try {
            $url = "https://api.telegram.org/bot{$this->telegramSetting->bot_token}/getUpdates";
            $response = Http::withOptions([
                'verify' => app()->environment('local') ? false : config('services.telegram.verify_ssl', true),
            ])->get($url);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Telegram API error while fetching updates: ' . $response->body(),
                ];
            }

            $payload = $response->json();
            if (! ($payload['ok'] ?? false)) {
                return [
                    'success' => false,
                    'message' => 'Telegram returned an error: ' . ($payload['description'] ?? 'Unknown error'),
                ];
            }

            $updates = $payload['result'] ?? [];
            if (empty($updates)) {
                return [
                    'success' => false,
                    'message' => 'No updates available. Send a message to the bot or add it to the chat first.',
                ];
            }

            foreach ($updates as $update) {
                $chat = $update['message']['chat'] ?? $update['edited_message']['chat'] ?? $update['channel_post']['chat'] ?? null;
                if (is_array($chat) && isset($chat['id'])) {
                    return [
                        'success' => true,
                        'chat_id' => $chat['id'],
                        'message' => 'Detected chat ID: ' . $chat['id'] . '. If this is the chat you want, save the settings now.',
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Could not find a chat ID in Telegram updates. Send a message to the bot and retry.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception while fetching Telegram updates: ' . $e->getMessage(),
            ];
        }
    }

    public function clearFetchedUpdates(): array
    {
        if (!$this->telegramSetting || !$this->telegramSetting->bot_token) {
            return [
                'success' => false,
                'message' => 'Bot token is required to clear fetched updates.',
            ];
        }

        try {
            $url = "https://api.telegram.org/bot{$this->telegramSetting->bot_token}/getUpdates";
            $response = Http::withOptions([
                'verify' => app()->environment('local') ? false : config('services.telegram.verify_ssl', true),
            ])->get($url);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Telegram API error while fetching updates: ' . $response->body(),
                ];
            }

            $payload = $response->json();
            if (! ($payload['ok'] ?? false)) {
                return [
                    'success' => false,
                    'message' => 'Telegram returned an error: ' . ($payload['description'] ?? 'Unknown error'),
                ];
            }

            $updates = $payload['result'] ?? [];
            if (empty($updates)) {
                return [
                    'success' => true,
                    'message' => 'No pending Telegram updates to clear.',
                ];
            }

            $lastUpdateId = collect($updates)->pluck('update_id')->max();
            $clearResponse = Http::withOptions([
                'verify' => app()->environment('local') ? false : config('services.telegram.verify_ssl', true),
            ])->get($url, ['offset' => $lastUpdateId + 1]);

            if (! $clearResponse->successful()) {
                return [
                    'success' => false,
                    'message' => 'Telegram API error while clearing updates: ' . $clearResponse->body(),
                ];
            }

            return [
                'success' => true,
                'message' => 'Cleared ' . count($updates) . ' pending Telegram updates.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception while clearing Telegram updates: ' . $e->getMessage(),
            ];
        }
    }
}