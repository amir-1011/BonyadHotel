<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BaleBotService
{
    public function miniAppUrl(): string
    {
        $url = config('services.bale.miniapp_url') ?: route('miniapp.bale.index', absolute: true);

        return Str::startsWith($url, ['http://', 'https://']) ? $url : route('miniapp.bale.index', absolute: true);
    }

    public function sendMessage(int|string $chatId, string $text, array $replyMarkup = []): array
    {
        $response = $this->post('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup ?: null,
        ], fn ($value) => !is_null($value)));

        return $response->json();
    }

    public function replyWithMiniAppButton(int|string $chatId, string $text = 'برای ورود، دکمه زیر را لمس کنید.'): array
    {
        return $this->sendMessage($chatId, $text, [
            'inline_keyboard' => [[
                [
                    'text' => 'باز کردن مینی‌اپ',
                    'web_app' => ['url' => $this->miniAppUrl()],
                ],
            ]],
        ]);
    }

    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $payload = ['url' => $url];

        if ($secretToken) {
            $payload['secret_token'] = $secretToken;
        }

        return $this->post('setWebhook', $payload)->json();
    }

    public function deleteWebhook(): array
    {
        return $this->post('deleteWebhook', [])->json();
    }

    public function getWebhookInfo(): array
    {
        return $this->post('getWebhookInfo', [])->json();
    }

    protected function post(string $method, array $payload)
    {
        $token = config('services.bale.bot_token');

        abort_if(!$token, 500, 'BALE_BOT_TOKEN is not configured.');

        return Http::asJson()->post("https://tapi.bale.ai/bot{$token}/{$method}", $payload)
            ->throw();
    }
}