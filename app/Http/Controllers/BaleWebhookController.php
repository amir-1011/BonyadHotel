<?php

namespace App\Http\Controllers;

use App\Services\BaleBotService;
use Illuminate\Http\Request;

class BaleWebhookController extends Controller
{
    public function handle(Request $request, string $secret, BaleBotService $baleBot)
    {
        abort_unless(hash_equals((string) config('services.bale.webhook_secret'), (string) $secret), 403);

        $update = $request->all();

        $message = $update['message'] ?? null;
        $text = data_get($message, 'text');
        $chatId = data_get($message, 'chat.id');

        if ($chatId && is_string($text) && str_starts_with($text, '/start')) {
            $baleBot->replyWithMiniAppButton((int) $chatId, 'خوش آمدید. برای ورود و ثبت‌نام، مینی‌اپ را باز کنید.');
        }

        return response()->json(['ok' => true]);
    }
}