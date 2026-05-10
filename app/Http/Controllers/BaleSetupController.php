<?php

namespace App\Http\Controllers;

use App\Services\BaleBotService;
use Illuminate\Http\Request;

class BaleSetupController extends Controller
{
    public function register(string $secret, BaleBotService $baleBot)
    {
        abort_unless(hash_equals((string) config('services.bale.webhook_secret'), (string) $secret), 403);

        $url = config('services.bale.webhook_url') ?: route('bale.webhook', ['secret' => $secret], absolute: true);

        return response()->json($baleBot->setWebhook($url, $secret));
    }
}